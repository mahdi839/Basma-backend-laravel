<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
            'address'=>'required|text',
            'delivery_notes'=>'nullable|text',
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
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'district' => $request->district,
            'city' => $request->delivery_notes,
            'subtotal' => $subtotal,
            'total' => $total,
            'status'=> 'placed',
            'payment_method' => $request->payment_method,
        ]);
    
        // Create order items
        foreach ($request->cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id'=>$item['id'],
                'product_title' => $item['title'],
                'size' => $item['size'],
                'unit_price' => $item['unitPrice'],
                'quantity' => $item['qty'],
                'total_price' => $item['totalPrice'],
            ]);
        }
    
        return response()->json([
            'message' => 'Order created successfully',
            'order_number' => $order->order_number
        ], 201);
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
