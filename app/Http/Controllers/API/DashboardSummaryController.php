<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardSummaryController extends Controller
{
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'range'       => 'nullable|in:today,week,month,year,custom',
            'start_date'  => 'required_if:range,custom|date',
            'end_date'    => 'required_if:range,custom|date|after_or_equal:start_date',
            'status'      => 'nullable|string', // CSV: paid,completed
            'hot_by'      => 'nullable|in:qty,revenue',
            'hot_limit'   => 'nullable|integer|min:1|max:50',
        ]);

        $range     = $validated['range'] ?? 'today';
        $tz        = config('app.timezone', 'UTC');
        $hotBy     = $validated['hot_by'] ?? 'qty';
        $hotLimit  = $validated['hot_limit'] ?? 10;

        [$from, $to] = $this->resolveDateRange(
            $range,
            $request->input('start_date'),
            $request->input('end_date'),
            $tz
        );

        $statuses = null;
        if (!empty($validated['status'])) {
            $statuses = collect(explode(',', $validated['status']))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->values()
                ->all();
        }

        // ----- ORDERS in window (optionally filtered by status)
        $ordersQ = Order::query()->whereBetween('created_at', [$from, $to]);
        if ($statuses) {
            $ordersQ->whereIn('status', $statuses);
        }

        $ordersCount = (clone $ordersQ)->count();

        // Customers by distinct phone (swap to user_id if you prefer)
        $customers = (clone $ordersQ)
            ->whereNotNull('phone')
            ->distinct('phone')
            ->count('phone');

        // ----- REVENUE (sum of order_items.totalPrice inside the window)
        $revenue = OrderItem::whereHas('order', function ($q) use ($from, $to, $statuses) {
                $q->whereBetween('created_at', [$from, $to]);
                if ($statuses) $q->whereIn('status', $statuses);
            })
            ->sum('totalPrice');

        // ----- ITEMS (for both COGS + Hot Products)
        // Include fields we need for aggregation & display
        $items = OrderItem::with(['selectedVariant:id,product_id,value'])
            ->whereHas('order', function ($q) use ($from, $to, $statuses) {
                $q->whereBetween('created_at', [$from, $to]);
                if ($statuses) $q->whereIn('status', $statuses);
            })
            ->get([
                'id',
                'product_id',
                'product_variant_id',
                'title',        // from your schema
                'qty',
                'totalPrice',
            ]);

        // ----- LATEST STOCK purchase_price per (product_id, product_variant_id)
        $productIds = $items->pluck('product_id')->unique()->values();

        $allStocks = ProductStock::whereIn('product_id', $productIds)
            ->get(['id','product_id','product_variant_id','purchase_price']);

        $latestStockByKey = $allStocks
            ->groupBy(fn ($row) => $row->product_id.'#'.($row->product_variant_id ?? 'null'))
            ->map(fn (Collection $group) => $group->sortByDesc('id')->first());

        // ----- COGS (estimated) = sum(latest_purchase_price * qty)
        $estimatedCogs = 0.0;
        foreach ($items as $it) {
            $key      = $it->product_id.'#'.($it->product_variant_id ?? 'null');
            $stockRow = $latestStockByKey->get($key);
            $purchase = $stockRow?->purchase_price ?? 0;
            $estimatedCogs += ((float)$purchase) * (int)$it->qty;
        }

        $estimatedProfit = (float)$revenue - (float)$estimatedCogs;

        // ----- HOT PRODUCTS (grouped by product + variant)
        // group by product_id + variant + title + variant_value (from selectedVariant->value)
        $grouped = $items->groupBy(function ($it) {
            $variantKey = $it->product_variant_id ?? 'null';
            $variantVal = optional($it->selectedVariant)->value ?? '';
            $title      = $it->title ?? '';
            return "{$it->product_id}#{$variantKey}#{$title}#{$variantVal}";
        });

        $hotProducts = $grouped->map(function (Collection $group) use ($latestStockByKey) {
                /** @var \App\Models\OrderItem $first */
                $first = $group->first();
                $productId  = $first->product_id;
                $variantId  = $first->product_variant_id;
                $variantVal = optional($first->selectedVariant)->value; // <- from relation
                $title      = $first->title ?? null;

                $qtySold = (int) $group->sum('qty');
                $revenue = (float) $group->sum('totalPrice');

                // compute cogs for this group via latest purchase price
                $key      = $productId.'#'.($variantId ?? 'null');
                $stockRow = $latestStockByKey->get($key);
                $purchase = $stockRow?->purchase_price ?? 0;
                $cogs     = (float) $purchase * $qtySold;
                $profit   = (float) $revenue - $cogs;

                return [
                    'product_id'        => $productId,
                    'product_variant_id'=> $variantId,
                    'title'             => $title,
                    'variant_value'     => $variantVal, // from ProductVariant.value
                    'qty_sold'          => $qtySold,
                    'revenue'           => number_format($revenue, 2, '.', ''),
                    'estimated_cogs'    => number_format($cogs, 2, '.', ''),
                    'estimated_profit'  => number_format($profit, 2, '.', ''),
                ];
            })
            ->sortByDesc(function ($row) use ($hotBy) {
                return $hotBy === 'revenue'
                    ? (float) $row['revenue']
                    : (int) $row['qty_sold'];
            })
            ->values()
            ->take($hotLimit)
            ->all();

        return response()->json([
            'range' => [
                'from' => $from->toDateTimeString(),
                'to'   => $to->toDateTimeString(),
            ],
            'totals' => [
                'orders'           => (int) $ordersCount,
                'sales_amount'     => number_format((float)$revenue, 2, '.', ''),
                'revenue'          => number_format((float)$revenue, 2, '.', ''),
                'estimated_cogs'   => number_format((float)$estimatedCogs, 2, '.', ''),
                'estimated_profit' => number_format((float)$estimatedProfit, 2, '.', ''),
                'customers'        => (int) $customers,
            ],
            'hot_products' => $hotProducts,
        ]);
    }

    private function resolveDateRange(string $range, ?string $start, ?string $end, string $tz): array
    {
        $now = Carbon::now($tz);

        return match ($range) {
            'week'   => [$now->copy()->startOfWeek(),  $now->copy()->endOfDay()],
            'month'  => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'year'   => [$now->copy()->startOfYear(),  $now->copy()->endOfDay()],
            'custom' => [Carbon::parse($start, $tz)->startOfDay(), Carbon::parse($end, $tz)->endOfDay()],
            default  => [$now->copy()->startOfDay(),   $now->copy()->endOfDay()], // today
        };
    }
}
