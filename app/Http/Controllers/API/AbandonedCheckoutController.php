<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AbandonedCheckoutController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'cart_items' => 'required|array',
            'name' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $phone = $data['phone'] ?? null;
        $sessionId = $request->header('X-Session-ID') ?? session()->getId();

        // Skip if no phone number provided (user hasn't filled form yet)
        if (empty($phone)) {
            return response()->json(['message' => 'No phone number provided.'], 400);
        }

        $convertedCheckoutExists = AbandonedCheckout::where('is_recovered', true)
            ->whereNotNull('converted_order_id')
            ->where(function ($query) use ($phone, $sessionId) {
                if ($sessionId) {
                    $query->where('session_id', $sessionId);

                    return;
                }

                $query->where('phone', $phone);
            })
            ->exists();

        if ($convertedCheckoutExists) {
            return response()->json(['message' => 'Checkout already converted.']);
        }

        // Check if abandoned checkout already exists for this phone
        $existingCheckout = AbandonedCheckout::where('is_recovered', false)
            ->where(function ($query) use ($phone, $sessionId) {
                $query->where('phone', $phone)
                    ->orWhere('session_id', $sessionId);
            })
            ->first();

        if ($existingCheckout) {
            // Update only if cart items changed or other details changed
            $existingCheckout->update([
                'cart_items' => json_encode($data['cart_items']),
                'name' => $data['name'] ?? $existingCheckout->name,
                'address' => $data['address'] ?? $existingCheckout->address,
                'session_id' => $sessionId,
            ]);

            return response()->json(['message' => 'Checkout progress updated.']);
        }

        // Create new abandoned checkout
        AbandonedCheckout::create([
            'phone' => $phone,
            'cart_items' => json_encode($data['cart_items']),
            'name' => $data['name'] ?? null,
            'address' => $data['address'] ?? null,
            'session_id' => $sessionId,
            'user_id' => Auth::guard('sanctum')->check() ? Auth::guard('sanctum')->id() : null,
            'is_recovered' => false,
        ]);

        return response()->json(['message' => 'Checkout progress saved.']);
    }

    public function index(Request $request)
    {
        $checkouts = AbandonedCheckout::where('is_recovered', false)
            ->whereNull('converted_order_id')
            ->when($request->filled('start_date'),function ($q)use ($request){
                $q->whereDate('created_at','>=',$request->start_date);
            })
            ->when($request->filled('end_date'),function ($q)use ($request){
                $q->whereDate('created_at','<=',$request->end_date);
            })
              ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->latest()
            ->paginate(50);

        return response()->json([
            'data' => $checkouts,
        ]);
    }

    // Add to AbandonedCheckoutController
    public function markAsConverted(Request $request)
    {
        $request->validate([
            'session_id' => 'nullable',
            'phone' => 'nullable',
        ]);

        if (! $request->filled('session_id') && ! $request->filled('phone')) {
            return response()->json(['message' => 'Session ID or phone is required.'], 422);
        }

        AbandonedCheckout::where(function ($query) use ($request) {
                $query->when($request->filled('session_id'), function ($q) use ($request) {
                    $q->where('session_id', $request->session_id);
                })->when(! $request->filled('session_id') && $request->filled('phone'), function ($q) use ($request) {
                    $q->where('phone', $request->phone);
                });
            })
            ->update(['is_recovered' => true]);

        return response()->json(['message' => 'Checkout marked as converted.']);
    }

    public function convertToOrder(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'district' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
            'delivery_notes' => 'nullable|string',
            'payment_method' => 'required|string',
            'advance_payment' => 'nullable|numeric|min:0',
            'cart' => 'required|array|min:1',
            'cart.*.id' => 'required|integer',
            'cart.*.title' => 'required|string',
            'cart.*.size' => 'nullable',
            'cart.*.unitPrice' => 'required|numeric|min:0',
            'cart.*.qty' => 'required|integer|min:1',
            'cart.*.totalPrice' => 'required|numeric|min:0',
            'cart.*.colorImage' => 'nullable',
            'cart.*.color_name' => 'nullable',
        ]);

        DB::beginTransaction();

        try {
            $checkout = AbandonedCheckout::lockForUpdate()->findOrFail($id);

            if ($checkout->converted_order_id) {
                DB::rollBack();

                return response()->json([
                    'message' => 'This incomplete order is already converted.',
                    'order_id' => $checkout->converted_order_id,
                ], 409);
            }

            $subtotal = collect($validated['cart'])->sum('totalPrice');
            $total = $subtotal + $validated['shipping_cost'];

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'user_id' => $checkout->user_id,
                'address' => $validated['address'],
                'district' => $validated['district'],
                'subtotal' => $subtotal,
                'total' => $total,
                'advance_payment' => $validated['advance_payment'] ?? 0,
                'shipping_cost' => $validated['shipping_cost'],
                'delivery_notes' => $validated['delivery_notes'] ?? null,
                'status' => 'placed',
                'payment_method' => $validated['payment_method'],
            ]);

            foreach ($validated['cart'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'title' => $item['title'],
                    'selected_size' => ! empty($item['size']) ? $item['size'] : null,
                    'unitPrice' => $item['unitPrice'],
                    'qty' => $item['qty'],
                    'totalPrice' => $item['totalPrice'],
                    'colorImage' => $item['colorImage'] ?? '',
                    'color_name' => $item['color_name'] ?? '',
                ]);
            }

            $checkout->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'cart_items' => $validated['cart'],
                'is_recovered' => true,
                'status' => 'placed',
                'converted_order_id' => $order->id,
                'converted_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Incomplete order converted successfully.',
                'order' => $order->load('orderItems.size'),
                'checkout' => $checkout->fresh(),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Order conversion failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required',
        ]);

        $checkout = AbandonedCheckout::findOrFail($id);

        $checkout->update([
            'status' => $data['status'],
        ]);

        return response()->json([
            'message' => 'Status updated successfully.',
            'data' => $checkout,
        ]);
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-'.rand(10000, 99999);
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
