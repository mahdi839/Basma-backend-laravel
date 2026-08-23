<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BdcourierFraudCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderFraudCheckController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
        BdcourierFraudCheckService $service
    ): JsonResponse {
        $validated = $request->validate([
            'force_refresh' => 'sometimes|boolean',
        ]);

        $result = $service->check(
            phone: (string) $order->phone,
            forceRefresh: (bool) ($validated['force_refresh'] ?? false)
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
}
