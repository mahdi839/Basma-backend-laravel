<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardSummaryController extends Controller
{
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:today,week,month,year,custom'],
            'start_date' => ['nullable', 'required_if:range,custom', 'date'],
            'end_date' => ['nullable', 'required_if:range,custom', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string'],
            'hot_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $timezone = config('app.business_timezone', 'Asia/Dhaka');
        [$localFrom, $localTo] = $this->resolveDateRange(
            $validated['range'] ?? 'today',
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $timezone
        );
        $from = $localFrom->copy()->utc();
        $to = $localTo->copy()->utc();
        $statuses = collect(explode(',', $validated['status'] ?? ''))
            ->map(fn ($status) => trim($status))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $limit = $validated['hot_limit'] ?? 8;

        $ordersQuery = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses));
        $orders = (clone $ordersQuery)->get([
            'id', 'phone', 'status', 'subtotal', 'shipping_cost', 'total', 'created_at',
        ]);

        $items = OrderItem::query()
            ->whereHas('order', function ($query) use ($from, $to, $statuses) {
                $query->whereBetween('created_at', [$from, $to])
                    ->when($statuses, fn ($orderQuery) => $orderQuery->whereIn('status', $statuses));
            })
            ->get(['product_id', 'title', 'qty', 'totalPrice']);

        $ordersCount = $orders->count();
        $grossSales = (float) $orders->sum('total');
        $unitsSold = (int) $items->sum('qty');
        $customerCount = $orders->pluck('phone')->filter()->unique()->count();

        $trend = $orders
            ->groupBy(fn ($order) => $order->created_at->copy()->setTimezone($timezone)->format('Y-m-d'))
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'sales' => round((float) $rows->sum('total'), 2),
                'orders' => $rows->count(),
            ])
            ->sortBy('date')
            ->values();

        $statusBreakdown = $orders
            ->groupBy('status')
            ->map(fn ($rows, $status) => [
                'status' => $status,
                'orders' => $rows->count(),
                'sales' => round((float) $rows->sum('total'), 2),
                'percentage' => $ordersCount ? round(($rows->count() / $ordersCount) * 100, 1) : 0,
            ])
            ->sortByDesc('orders')
            ->values();

        $topProducts = $items
            ->groupBy(fn ($item) => ($item->product_id ?? 'deleted') . '|' . $item->title)
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'product_id' => $first->product_id,
                    'title' => $first->title,
                    'quantity_sold' => (int) $rows->sum('qty'),
                    'order_lines' => $rows->count(),
                    'sales' => round((float) $rows->sum('totalPrice'), 2),
                ];
            })
            ->sortByDesc('quantity_sold')
            ->take($limit)
            ->values();

        $previousFrom = $from->copy()->subSeconds($to->diffInSeconds($from) + 1);
        $previousTo = $from->copy()->subSecond();
        $previousOrders = Order::query()
            ->whereBetween('created_at', [$previousFrom, $previousTo])
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->get(['id', 'phone', 'total']);
        $previousOrderIds = $previousOrders->pluck('id');
        $previousUnits = $previousOrderIds->isEmpty()
            ? 0
            : (int) OrderItem::whereIn('order_id', $previousOrderIds)->sum('qty');

        return response()->json([
            'range' => [
                'from' => $localFrom->toDateTimeString(),
                'to' => $localTo->toDateTimeString(),
                'timezone' => $timezone,
            ],
            'totals' => [
                'gross_sales' => round($grossSales, 2),
                'orders' => $ordersCount,
                'units_sold' => $unitsSold,
                'customers' => $customerCount,
                'average_order_value' => $ordersCount ? round($grossSales / $ordersCount, 2) : 0,
                'shipping_collected' => round((float) $orders->sum('shipping_cost'), 2),
            ],
            'changes' => [
                'gross_sales' => $this->percentageChange($grossSales, (float) $previousOrders->sum('total')),
                'orders' => $this->percentageChange($ordersCount, $previousOrders->count()),
                'units_sold' => $this->percentageChange($unitsSold, $previousUnits),
                'customers' => $this->percentageChange(
                    $customerCount,
                    $previousOrders->pluck('phone')->filter()->unique()->count()
                ),
            ],
            'sales_trend' => $trend,
            'status_breakdown' => $statusBreakdown,
            'top_products' => $topProducts,
        ]);
    }

    private function resolveDateRange(string $range, ?string $start, ?string $end, string $timezone): array
    {
        $now = Carbon::now($timezone);

        return match ($range) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'custom' => [
                Carbon::parse($start, $timezone)->startOfDay(),
                Carbon::parse($end, $timezone)->endOfDay(),
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function percentageChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
