<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FraudCheckerSetting;
use App\Services\BdcourierFraudCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FraudCheckerSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsPayload(FraudCheckerSetting::current()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string|max:2048',
            'is_active' => 'required|boolean',
            'cache_minutes' => 'required|integer|min:0|max:1440',
        ]);

        $settings = FraudCheckerSetting::current() ?? new FraudCheckerSetting();
        $newApiKey = trim((string) ($validated['api_key'] ?? ''));

        if ($validated['is_active'] && $newApiKey === '' && blank($settings->api_key)) {
            throw ValidationException::withMessages([
                'api_key' => ['An API key is required before enabling the courier checker.'],
            ]);
        }

        if ($newApiKey !== '') {
            $settings->api_key = $newApiKey;
        }

        $settings->is_active = $validated['is_active'];
        $settings->cache_minutes = $validated['cache_minutes'];
        $settings->save();

        return response()->json([
            'message' => 'Courier checker settings saved successfully.',
            'data' => $this->settingsPayload($settings->fresh()),
        ]);
    }

    public function test(Request $request, BdcourierFraudCheckService $service): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $result = $service->check(
            phone: $validated['phone'],
            forceRefresh: true,
            allowDisabled: true
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Connection successful.',
            'data' => $result,
        ]);
    }

    private function settingsPayload(?FraudCheckerSetting $settings): array
    {
        $apiKey = $settings?->api_key;

        return [
            'is_active' => (bool) ($settings?->is_active ?? false),
            'cache_minutes' => (int) ($settings?->cache_minutes ?? 60),
            'has_api_key' => filled($apiKey),
            'api_key_hint' => filled($apiKey) ? '••••'.substr($apiKey, -4) : null,
            'provider_url' => config('services.bdcourier.fraud_check_url'),
        ];
    }
}
