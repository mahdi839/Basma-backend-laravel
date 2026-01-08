<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardSummaryController extends Controller
{
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'range'       => 'nullable|in:today,week,month,year,custom',
            'start_date'  => 'date',
            'end_date'    => 'date',
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
                ->map(fn($s) => trim($s))
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

        // Customers by distinct phone
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
        // ✅ Changed: Load 'size' relationship instead of 'selectedVariant'
        $items = OrderItem::with(['size:id,size', 'product:id,title'])
            ->whereHas('order', function ($q) use ($from, $to, $statuses) {
                $q->whereBetween('created_at', [$from, $to]);
                if ($statuses) $q->whereIn('status', $statuses);
            })
            ->get([
                'id',
                'product_id',
                'selected_size',  // This is size_id
                'title',
                'qty',
                'totalPrice',
                'unitPrice',
            ]);

        // ----- LATEST STOCK/PRICE per (product_id, size_id)
        $productIds = $items->pluck('product_id')->unique()->values();

        // ✅ Use product_sizes table for purchase price (or use 'price' from pivot)
        $allStocks = DB::table('product_sizes')
            ->whereIn('product_id', $productIds)
            ->get(['id', 'product_id', 'size_id', 'price', 'stock']);

        $latestStockByKey = $allStocks
            ->groupBy(fn($row) => $row->product_id . '#' . ($row->size_id ?? 'null'))
            ->map(fn($group) => $group->sortByDesc('id')->first());

        // ----- COGS (estimated) = sum(price * qty)
        $estimatedCogs = 0.0;
        foreach ($items as $it) {
            $key = $it->product_id . '#' . ($it->selected_size ?? 'null');
            $stockRow = $latestStockByKey->get($key);
            $purchase = $stockRow->price ?? $it->unitPrice ?? 0;
            $estimatedCogs += ((float)$purchase) * (int)$it->qty;
        }

        $estimatedProfit = (float)$revenue - (float)$estimatedCogs;

        // ----- HOT PRODUCTS (grouped by product + size)
        $grouped = $items->groupBy(function ($it) {
            $sizeKey = $it->selected_size ?? 'null';
            $sizeName = optional($it->size)->size ?? '';
            $title = $it->title ?? '';
            return "{$it->product_id}#{$sizeKey}#{$title}#{$sizeName}";
        });

        $hotProducts = $grouped->map(function ($group) use ($latestStockByKey) {
            $first = $group->first();
            $productId = $first->product_id;
            $sizeId = $first->selected_size;
            $sizeName = optional($first->size)->size;
            $title = $first->title ?? null;

            $qtySold = (int) $group->sum('qty');
            $revenue = (float) $group->sum('totalPrice');

            // Compute COGS for this group
            $key = $productId . '#' . ($sizeId ?? 'null');
            $stockRow = $latestStockByKey->get($key);
            $purchase = $stockRow->price ?? $first->unitPrice ?? 0;
            $cogs = (float) $purchase * $qtySold;
            $profit = (float) $revenue - $cogs;

            return [
                'product_id'     => $productId,
                'size_id'        => $sizeId,
                'title'          => $title,
                'size_name'      => $sizeName, // ✅ Changed from variant_value
                'qty_sold'       => $qtySold,
                'revenue'        => number_format($revenue, 2, '.', ''),
                'estimated_cogs' => number_format($cogs, 2, '.', ''),
                'estimated_profit' => number_format($profit, 2, '.', ''),
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
