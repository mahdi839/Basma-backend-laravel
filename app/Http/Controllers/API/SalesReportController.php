<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:150'],
            'sort' => ['nullable', 'in:revenue,quantity,orders,title'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $timezone = config('app.business_timezone', 'Asia/Dhaka');
        $localFrom = !empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'], $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfMonth();
        $localTo = !empty($validated['end_date'])
            ? Carbon::parse($validated['end_date'], $timezone)->endOfDay()
            : Carbon::now($timezone)->endOfDay();
        $from = $localFrom->copy()->utc();
        $to = $localTo->copy()->utc();
        $statuses = $this->statuses($validated['status'] ?? null);

        $orders = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses));

        $orderRows = (clone $orders)->get([
            'id', 'phone', 'status', 'subtotal', 'shipping_cost', 'total', 'created_at',
        ]);

        $itemStats = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($statuses, fn ($query) => $query->whereIn('orders.status', $statuses))
            ->selectRaw('order_items.product_id, MAX(order_items.title) as title')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as order_count')
            ->selectRaw('COUNT(DISTINCT orders.phone) as customer_count')
            ->selectRaw('SUM(order_items.qty) as quantity_sold')
            ->selectRaw('SUM(order_items.totalPrice) as gross_sales')
            ->selectRaw('AVG(order_items.unitPrice) as average_unit_price')
            ->selectRaw('MAX(orders.created_at) as last_ordered_at')
            ->groupBy('order_items.product_id')
            ->get()
            ->keyBy(fn ($row) => (string) ($row->product_id ?? ''));

        $products = Product::query()
            ->select(['id', 'title', 'sku', 'status'])
            ->orderBy('title')
            ->get()
            ->map(function ($product) use ($itemStats) {
                $stat = $itemStats->get((string) $product->id);
                return $this->productRow($product, $stat);
            });

        // Keep historical sales visible even when a product has since been deleted.
        $knownIds = $products->pluck('product_id')->map(fn ($id) => (string) $id)->all();
        $historical = $itemStats
            ->reject(fn ($row) => in_array((string) $row->product_id, $knownIds, true))
            ->map(fn ($row) => $this->productRow(null, $row));

        $productRows = $products->concat($historical);
        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $productRows = $productRows->filter(fn ($row) =>
                str_contains(mb_strtolower($row['title']), $needle)
                || str_contains(mb_strtolower((string) $row['sku']), $needle)
            );
        }

        $sort = $validated['sort'] ?? 'revenue';
        $direction = $validated['direction'] ?? 'desc';
        $sortKey = [
            'revenue' => 'gross_sales',
            'quantity' => 'quantity_sold',
            'orders' => 'order_count',
            'title' => 'title',
        ][$sort];
        $productRows = ($direction === 'asc'
            ? $productRows->sortBy($sortKey, SORT_NATURAL | SORT_FLAG_CASE)
            : $productRows->sortByDesc($sortKey, SORT_NATURAL | SORT_FLAG_CASE))
            ->values();

        $statusBreakdown = $orderRows
            ->groupBy('status')
            ->map(fn ($rows, $status) => [
                'status' => $status,
                'orders' => $rows->count(),
                'sales' => round((float) $rows->sum('total'), 2),
            ])
            ->sortByDesc('orders')
            ->values();

        $dailySales = $orderRows
            ->groupBy(fn ($order) => $order->created_at->copy()->setTimezone($timezone)->format('Y-m-d'))
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'orders' => $rows->count(),
                'sales' => round((float) $rows->sum('total'), 2),
                'customers' => $rows->pluck('phone')->filter()->unique()->count(),
            ])
            ->sortBy('date')
            ->values();

        $unitsSold = (int) $productRows->sum('quantity_sold');
        $grossSales = (float) $orderRows->sum('total');
        $ordersCount = $orderRows->count();

        return response()->json([
            'range' => [
                'from' => $localFrom->toDateString(),
                'to' => $localTo->toDateString(),
                'timezone' => $timezone,
            ],
            'summary' => [
                'gross_sales' => round($grossSales, 2),
                'orders' => $ordersCount,
                'units_sold' => $unitsSold,
                'customers' => $orderRows->pluck('phone')->filter()->unique()->count(),
                'average_order_value' => $ordersCount ? round($grossSales / $ordersCount, 2) : 0,
                'shipping_collected' => round((float) $orderRows->sum('shipping_cost'), 2),
                'products_ordered' => $productRows->where('order_count', '>', 0)->count(),
            ],
            'products' => $productRows,
            'daily_sales' => $dailySales,
            'status_breakdown' => $statusBreakdown,
        ]);
    }

    public function productOptions()
    {
        $catalog = Product::query()
            ->select(['id', 'title', 'sku'])
            ->orderBy('title')
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'title' => $product->title,
                'sku' => $product->sku,
            ]);

        $known = $catalog->pluck('id')->map(fn ($id) => (string) $id)->all();
        $historical = OrderItem::query()
            ->select(['product_id', 'title'])
            ->whereNotNull('title')
            ->distinct()
            ->get()
            ->unique(fn ($item) => ($item->product_id ?? 'deleted') . '|' . $item->title)
            ->reject(fn ($item) => $item->product_id && in_array((string) $item->product_id, $known, true))
            ->map(fn ($item) => [
                'id' => $item->product_id,
                'title' => $item->title,
                'sku' => null,
            ]);

        return response()->json(['data' => $catalog->concat($historical)->values()]);
    }

    private function statuses(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($status) => trim($status))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function productRow($product, $stat): array
    {
        return [
            'product_id' => $product?->id ?? $stat?->product_id,
            'title' => $product?->title ?? $stat?->title ?? 'Deleted product',
            'sku' => $product?->sku,
            'product_status' => $product?->status ?? 'archived',
            'order_count' => (int) ($stat?->order_count ?? 0),
            'customer_count' => (int) ($stat?->customer_count ?? 0),
            'quantity_sold' => (int) ($stat?->quantity_sold ?? 0),
            'gross_sales' => round((float) ($stat?->gross_sales ?? 0), 2),
            'average_unit_price' => round((float) ($stat?->average_unit_price ?? 0), 2),
            'last_ordered_at' => $stat?->last_ordered_at,
        ];
    }
}
