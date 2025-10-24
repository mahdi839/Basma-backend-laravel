<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PathaoService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class PathaoController extends Controller
{
    private PathaoService $pathaoService;

    public function __construct(PathaoService $pathaoService)
    {
        $this->pathaoService = $pathaoService;
    }

    /**
     * Create Pathao order for a specific order
     */
    public function createOrder($orderId): JsonResponse
    {
        try {
            // Find the order with items
            $order = Order::with('orderItems.selectedVariant')->findOrFail($orderId);

            // Check if already entered
            if ($order->courier_entry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already entered in courier system'
                ], 400);
            }

            // Create order in Pathao
            $response = $this->pathaoService->createOrder($order);

            return response()->json([
                'success' => true,
                'message' => 'Pathao order created successfully',
                'data' => $response
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Pathao order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test token generation
     */
    public function testToken(): JsonResponse
    {
        try {
            $token = $this->pathaoService->getAccessToken();

            return response()->json([
                'success' => true,
                'message' => 'Token generated successfully',
                'token' => substr($token, 0, 20) . '...'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}