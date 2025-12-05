<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerLeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
            'district' => 'nullable|string',
            'order_count_sort' => 'nullable|in:asc,desc',
            'total_spent_sort' => 'nullable|in:asc,desc',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $query = DB::table('orders')
            ->select(
                'phone',
                DB::raw('MAX(name) as name'),
                DB::raw('MAX(address) as address'),
                DB::raw('MAX(district) as district'),
                DB::raw('MAX(user_id) as user_id'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('MAX(created_at) as last_order_date')
            )
            ->groupBy('phone');

        // Search filter (name, phone, email from users table if linked)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // District filter
        if ($request->filled('district')) {
            $query->where('district', $request->district);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Sorting
        if ($request->filled('order_count_sort')) {
            $query->orderBy('total_orders', $request->order_count_sort);
        }
        if ($request->filled('total_spent_sort')) {
            $query->orderBy('total_spent', $request->total_spent_sort);
        }

        // Default sorting by total spent desc if no sort specified
        if (!$request->filled('order_count_sort') && !$request->filled('total_spent_sort')) {
            $query->orderBy('total_spent', 'desc');
        }

        $perPage = $request->input('per_page', 15);
        $customers = $query->paginate($perPage);

        // Enhance data with additional info
        $customers->getCollection()->transform(function ($customer) {
            // Get user email if user_id exists
            $email = null;
            if ($customer->user_id) {
                $user = DB::table('users')->where('id', $customer->user_id)->first();
                if ($user) {
                    $email = $user->email;
                }
            }

            // Get last order details
            $lastOrder = DB::table('orders')
                ->where('phone', $customer->phone)
                ->orderBy('created_at', 'desc')
                ->first();

            // Get last ordered products
            $lastOrderedProducts = [];
            if ($lastOrder) {
                $lastOrderedProducts = DB::table('order_items')
                    ->where('order_id', $lastOrder->id)
                    ->select('title', 'qty', 'unitPrice', 'totalPrice', 'colorImage', 'selected_size')
                    ->get()
                    ->toArray();
            }

            // Determine badge
            $badge = $customer->total_orders == 1 ? 'new' : 'repeat_customer';

            return [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $email,
                'address' => $customer->address,
                'district' => $customer->district,
                'total_orders' => $customer->total_orders,
                'total_spent' => (float) $customer->total_spent,
                'last_order_date' => $customer->last_order_date,
                'last_ordered_products' => $lastOrderedProducts,
                'badge' => $badge
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem()
            ]
        ]);
    }

    // Get statistics summary
    public function statistics()
    {
        $stats = DB::table('orders')
            ->select(
                DB::raw('COUNT(DISTINCT phone) as total_customers'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('AVG(total) as average_order_value')
            )
            ->first();

        $newCustomers = DB::table('orders')
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) = 1')
            ->get()
            ->count();

        $repeatCustomers = DB::table('orders')
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $stats->total_customers,
                'total_orders' => $stats->total_orders,
                'total_revenue' => (float) $stats->total_revenue,
                'average_order_value' => (float) $stats->average_order_value,
                'new_customers' => $newCustomers,
                'repeat_customers' => $repeatCustomers
            ]
        ]);
    }

    // Get customer details by phone
    public function show($phone)
    {
        $customer = DB::table('orders')
            ->select(
                'phone',
                DB::raw('MAX(name) as name'),
                DB::raw('MAX(address) as address'),
                DB::raw('MAX(district) as district'),
                DB::raw('MAX(user_id) as user_id'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('MAX(created_at) as last_order_date'),
                DB::raw('MIN(created_at) as first_order_date')
            )
            ->where('phone', $phone)
            ->groupBy('phone')
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        // Get user email if exists
        $email = null;
        if ($customer->user_id) {
            $user = DB::table('users')->where('id', $customer->user_id)->first();
            if ($user) {
                $email = $user->email;
            }
        }

        // Get all orders
        $orders = DB::table('orders')
            ->where('phone', $phone)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get order items for each order
        foreach ($orders as $order) {
            $order->items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->get();
        }

        $badge = $customer->total_orders == 1 ? 'new' : 'repeat_customer';

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $email,
                'address' => $customer->address,
                'district' => $customer->district,
                'total_orders' => $customer->total_orders,
                'total_spent' => (float) $customer->total_spent,
                'first_order_date' => $customer->first_order_date,
                'last_order_date' => $customer->last_order_date,
                'badge' => $badge,
                'orders' => $orders
            ]
        ]);
    }
}
