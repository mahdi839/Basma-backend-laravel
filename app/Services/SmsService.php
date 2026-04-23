<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendOrderConfirmation(string $phone, string $customerName, string $orderNumber,?string $custom_message = null): array
    {
        $phone = $this->formatBdPhone($phone);

        if (!$phone) {
            return [
                'success' => false,
                'message' => 'Invalid phone number',
            ];
        }

        $DefaultMessage = "Dear {$customerName}, your order {$orderNumber} has been placed successfully. Thank you for shopping with us.";
        if($custom_message){
            $message = $custom_message;
        }else{
            $message = $DefaultMessage;
        }
        return $this->sendSms($phone, $message);
    }

    public function sendSms(string $phone, string $message): array
    {
        if (!config('sms.enabled')) {
            return [
                'success' => false,
                'message' => 'SMS sending is disabled',
            ];
        }

        try {
            $response = Http::timeout(15)->get(config('sms.base_url') . '/smsapi', [
                'api_key'   => config('sms.api_key'),
                'type'      => config('sms.type', 'text'),
                'contacts'  => $phone,
                'senderid'  => config('sms.sender_id'),
                'msg'       => $message,
                'label'     => config('sms.label', 'transactional'),
            ]);

            $body = $response->body();

            Log::info('MRAM SMS API response', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $body,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'SMS API HTTP request failed',
                    'response' => $body,
                ];
            }

            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'response' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('SMS sending failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function formatBdPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        // keep only digits
        $phone = preg_replace('/\D+/', '', $phone);

        // 01XXXXXXXXX => 8801XXXXXXXXX
        if (preg_match('/^01\d{9}$/', $phone)) {
            return '88' . $phone;
        }

        // 8801XXXXXXXXX => valid
        if (preg_match('/^8801\d{9}$/', $phone)) {
            return $phone;
        }

        // 81XXXXXXXXX or malformed => invalid
        return null;
    }
}