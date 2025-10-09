<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', '');
        $district = $request->query('district', '');
        $search = $request->input('search', '');
        $min = $request->query('min', '');
        $max = $request->query('max', '');
        $start_date = $request->query('start_date', '');
        $end_date = $request->query('end_date', '');
        $product_title = $request->query('product_title', '');


        $orders = Order::with('orderItems')
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($district, function ($q) use ($district) {
                $q->where('district', $district);
            })
            ->when($min, function ($q) use ($min) {
                $q->where('total', '>=', $min);
            })
            ->when($max, function ($q) use ($max) {
                $q->where('total', '<=', $max);
            })
            ->when($start_date, function ($q) use ($start_date) {
                $q->where('created_at', '>=', $start_date);
            })
            ->when($end_date, function ($q) use ($end_date) {
                $q->where('created_at', '<=', $end_date);
            })
            ->when($product_title, function ($q) use ($product_title) {
                $q->whereHas('orderItems', function ($query) use ($product_title) {
                    $query->where('title', $product_title);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%");
                });
            })
            ->paginate(10);
        return response()->json($orders);
    }

    public function downloadCSV(Request $request)
    {
        $status = $request->query('status', '');
        $district = $request->query('district', '');
        $search = $request->input('search', '');
        $min = $request->query('min', '');
        $max = $request->query('max', '');
        $start_date = $request->query('start_date', '');
        $end_date = $request->query('end_date', '');
        $product_title = $request->query('product_title', '');
        $orders = Order::with('orderItems')
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($district, function ($q) use ($district) {
                $q->where('district', $district);
            })
            ->when($min, function ($q) use ($min) {
                $q->where('total', '>=', $min);
            })
            ->when($max, function ($q) use ($max) {
                $q->where('total', '<=', $max);
            })
            ->when($start_date, function ($q) use ($start_date) {
                $q->where('created_at', '>=', $start_date);
            })
            ->when($end_date, function ($q) use ($end_date) {
                $q->where('created_at', '<=', $end_date);
            })
            ->when($product_title, function ($q) use ($product_title) {
                $q->whereHas('orderItems', function ($query) use ($product_title) {
                    $query->where('title', $product_title);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%");
                });
            })->get();

        $date = Date('Y-m-d');
        $fileName = "orders_{$date}.csv";

        $header = [
            'content-type' => 'text/csv',
            'content-disposition' => "attachment; filename={$fileName}",
        ];

        $columns = ["Customer Info", "Ordered Products", "Order Summery", "status"];
        $callback = function () use ($orders, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($orders as $order) {
                $customer_info = "Name: {$order->name}| Phone: {$order->phone}| District: {$order->district}";
                $ordered_products = $order->orderItems->map(fn($item) => "Product Name: {$item->title} (Qty: {$item->qty})")->implode("; ");
                $orderSummary = "Total: {$order->total}";
                $status = $order->status;
                fputcsv($file, [$customer_info, $ordered_products, $orderSummary, $status]);
            }
            fclose($file);
        };

        return response()->stream($callback,200,$header);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::with('sizes')->select(['id','title','price'])->get();
    
      return response()->json([
         'data' => [
            'products' => $products,
          ]
      ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'shipping_cost' => 'required|numeric',
            'district' => 'required|string',
            'address' => 'required|string',
            'delivery_notes' => 'nullable|string',
            'payment_method' => 'required|string',
            'cart' => 'required|array',
            'cart.*.id' => 'required|integer',
            'cart.*.title' => 'required|string',
            'cart.*.size' => 'nullable|string',
            'cart.*.unitPrice' => 'required|numeric',
            'cart.*.qty' => 'required|integer',
            'cart.*.totalPrice' => 'required|numeric',
        ]);

        // Calculate totals
        $subtotal = collect($request->cart)->sum('totalPrice');
        $total = $subtotal - $request->shipping_cost; // Add shipping/tax if needed

        // Create order
        $order = Order::create([
            'order_number' => 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid()),
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'district' => $request->district,
            'subtotal' => $subtotal,
            'total' => $total,
            'shipping_cost' => $request->shipping_cost,
            'delivery_notes' => $request->delivery_notes,
            'status' => 'placed',
            'payment_method' => $request->payment_method,
        ]);

        // Create order items
        foreach ($request->cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'title' => $item['title'],
                'product_variant_id' => $item['size'],
                'unitPrice' => $item['unitPrice'],
                'qty' => $item['qty'],
                'totalPrice' => $item['totalPrice'],
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order_number' => $order->order_number
        ], 201);
    }

    public function order_status (Request $request,$id){
        $order = Order::findOrFail($id);
        $order->update([
            'status'=> $request->status,
        ]);
        return response()->json([
            'message'=> 'Status Updated Successfully!'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
