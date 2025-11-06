<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FacebookSetting;

class FacebookConversionService
{
    protected $settings;

    public function __construct()
    {
        $this->settings = FacebookSetting::getActive();
    }

    public function isEnabled()
    {
        return $this->settings && 
               $this->settings->is_active && 
               $this->settings->pixel_id && 
               $this->settings->access_token;
    }

    public function sendEvent($eventName, $userData = [], $customData = [], $eventSourceUrl = null)
    {
        if (!$this->isEnabled()) {
            Log::info('Facebook Conversion API is disabled or not configured');
            return null;
        }

        try {
            // Hash email and phone for privacy
            $hashedEmail = !empty($userData['email']) ? hash('sha256', strtolower(trim($userData['email']))) : null;
            $hashedPhone = !empty($userData['phone']) ? hash('sha256', preg_replace('/[^0-9]/', '', $userData['phone'])) : null;

            // Build user data
            $userDataPayload = array_filter([
                'em' => $hashedEmail,
                'ph' => $hashedPhone,
                'client_ip_address' => $userData['client_ip_address'] ?? request()->ip(),
                'client_user_agent' => $userData['client_user_agent'] ?? request()->userAgent(),
                'fbp' => $userData['fbp'] ?? null,
                'fbc' => $userData['fbc'] ?? null,
            ]);

            // Build custom data
            $customDataPayload = array_filter([
                'currency' => $customData['currency'] ?? 'BDT',
                'value' => $customData['value'] ?? null,
                'content_name' => $customData['content_name'] ?? null,
                'content_category' => $customData['content_category'] ?? null,
                'content_ids' => $customData['content_ids'] ?? [],
                'contents' => $customData['contents'] ?? [],
                'content_type' => $customData['content_type'] ?? 'product',
                'num_items' => $customData['num_items'] ?? null,
            ]);

            // Create event
            $event = [
                'event_name' => $eventName,
                'event_time' => time(),
                'event_source_url' => $eventSourceUrl ?? request()->headers->get('referer'),
                'user_data' => $userDataPayload,
                'custom_data' => $customDataPayload,
                'action_source' => 'website',
                'event_id' => $customData['event_id'] ?? uniqid('fb_', true),
            ];

            // Send event to Facebook API
            $url = "https://graph.facebook.com/v18.0/{$this->settings->pixel_id}/events";

            $payload = array_filter([
                'data' => [$event],
                'access_token' => $this->settings->access_token,
                'test_event_code' => $this->settings->is_test_mode ? $this->settings->test_event_code : null,
            ]);

            $response = Http::post($url, $payload);

            Log::info('Facebook Conversion API event sent', [
                'event_name' => $eventName,
                'test_mode' => $this->settings->is_test_mode,
                'response' => $response->json()
            ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Facebook Conversion API error', [
                'message' => $e->getMessage(),
                'event_name' => $eventName
            ]);
            
            return null;
        }
    }
}
