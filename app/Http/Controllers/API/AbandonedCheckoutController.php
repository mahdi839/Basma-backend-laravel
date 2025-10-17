<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Check if abandoned checkout already exists for this phone
        $existingCheckout = AbandonedCheckout::where('phone', $phone)
            ->where('is_recovered', false) // Only check non-converted checkouts
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
            'user_id' => Auth::guard('sanctum')->check()?Auth::guard('sanctum')->id():null,
            'is_recovered' => false,
        ]);

        return response()->json(['message' => 'Checkout progress saved.']);
    }

    public function index()
    {
        return AbandonedCheckout::where('is_recovered', false)
            ->latest()
            ->get();
    }

    // Add to AbandonedCheckoutController
public function markAsConverted(Request $request)
{
    $request->validate([
        'phone' => 'required|string',
    ]);

    AbandonedCheckout::where('phone', $request->phone)
        ->update(['is_recovered' => true]);

    return response()->json(['message' => 'Checkout marked as converted.']);
 }
}