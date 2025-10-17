<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use Illuminate\Http\Request;

class AbandonedCheckoutController extends Controller
{
      public function store(Request $request)
    {
        $data = $request->validate([
            'cart_items' => 'required',
            'name' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);
         // ...existing code...
       $phone = $data['phone'] ?? null;
       $userId = auth()->check() ? auth()->id() : null;
       AbandonedCheckout::updateOrCreate(
       [
        'phone' => $phone,
        'session_id' => session()->getId(),
        'user_id' => $userId,
        ],
        [
            'cart_items' => $data['cart_items'],
            'name' => $data['name'] ?? null,
            'address' => $data['address'] ?? null,
        ]
);
        return response()->json(['message' => 'Checkout progress saved.']);
    }

    public function index(Request $request)
    {
           $query = AbandonedCheckout::query();

            // 🗓️ Optional date filters
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            } elseif ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            } elseif ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            } else {
                // 🔥 Default: show today's data only
                $query->whereDate('created_at', now()->toDateString());
            }

            return $query->latest()->paginate(20);
    }
}
