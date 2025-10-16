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

        AbandonedCheckout::updateOrCreate(
            ['session_id' => session()->getId()],
            [
                'user_id' => auth()->id(),
                'cart_items' => json_encode($data['cart_items']),
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]
        );

        return response()->json(['message' => 'Checkout progress saved.']);
    }

    public function index()
    {
        return AbandonedCheckout::latest()->get();
    }
}
