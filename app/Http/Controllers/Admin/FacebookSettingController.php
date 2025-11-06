<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\FacebookSetting;

class FacebookSettingController extends Controller
{
    public function index()
    {
        $settings = FacebookSetting::first();
        return response()->json($settings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pixel_id' => 'required|string',
            'access_token' => 'required|string',
            'test_event_code' => 'nullable|string',
            'is_active' => 'boolean',
            'is_test_mode' => 'boolean',
        ]);

        $settings = FacebookSetting::first();

        if ($settings) {
            $settings->update($validated);
        } else {
            $settings = FacebookSetting::create($validated);
        }

        return response()->json([
            'message' => 'Facebook settings saved successfully',
            'settings' => $settings
        ]);
    }

    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'pixel_id' => 'required|string',
            'access_token' => 'required|string',
            'test_event_code' => 'nullable|string',
        ]);

        try {
            // Prepare test event payload
            $event = [
                'event_name' => 'TestConnection',
                'event_time' => time(),
                'action_source' => 'website',
                'user_data' => [
                    'client_ip_address' => request()->ip(),
                    'client_user_agent' => request()->userAgent(),
                ],
            ];

            // Facebook CAPI endpoint
            $url = "https://graph.facebook.com/v18.0/{$validated['pixel_id']}/events";

            // Build request payload
            $payload = [
                'data' => [$event],
                'access_token' => $validated['access_token'],
            ];

            if (!empty($validated['test_event_code'])) {
                $payload['test_event_code'] = $validated['test_event_code'];
            }

            // Send HTTP request
            $response = Http::post($url, $payload);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful! Check Facebook Events Manager.',
                    'response' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection failed: ' . json_encode($response->json())
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ], 400);
        }
    }
}
