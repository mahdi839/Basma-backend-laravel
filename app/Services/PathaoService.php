<?php

namespace App\Services;

use App\Models\PathaoToken;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PathaoService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $username;
    private string $password;
    private string $grantType;
    private int $storeId;

    public function __construct()
    {
        $this->baseUrl = config('pathao.base_url');
        $this->clientId = config('pathao.client_id');
        $this->clientSecret = config('pathao.client_secret');
        $this->username = config('pathao.username');
        $this->password = config('pathao.password');
        $this->grantType = config('pathao.grant_type');
        $this->storeId = config('pathao.store_id');
    }

    public function getAccessToken()
    {
        $token = PathaoToken::latest()->first();
        if (!$token || $token->isExpired()) {
            if ($token && $token->refresh_token) {
                try {
                    return $this->refreshToken($token->refresh_token);
                } catch (Exception $e) {
                    Log::error('Failed to refresh token: ' . $e->getMessage());
                    return $this->issueNewToken();
                }
            }
            return $this->issueNewToken();
        }
        return $token->access_token;
    }

    public function issueNewToken()
    {
        try {
            $response = Http::post($this->baseUrl . '/aladdin/api/v1/issue-token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => $this->grantType,
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to issue token: ' . $response->body());
            }

            $data = $response->json();
            $token = PathaoToken::create([
                'token_type' => $data['token_type'],
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
                'expires_at' => Carbon::now()->addSeconds($data['expires_in']),
            ]);

            return $token->access_token;
        } catch (Exception $e) {
            Log::error('Pathao token issue error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function refreshToken(string $refreshToken): string
    {
        try {
            $response = Http::post($this->baseUrl . '/aladdin/api/v1/issue-token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to refresh token: ' . $response->body());
            }

            $data = $response->json();
            $token = PathaoToken::create([
                'token_type' => $data['token_type'],
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
                'expires_at' => Carbon::now()->addSeconds($data['expires_in']),
            ]);

            return $token->access_token;
        } catch (Exception $e) {
            Log::error('Pathao token refresh error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createOrder(Order $order): array
    {
        try {
            $accessToken = $this->getAccessToken();

            // Build item description from order items
            $itemDescription = $order->orderItems->map(function ($item) {
                $variant = $item->selectedVariant 
                    ? " ({$item->selectedVariant->attribute}: {$item->selectedVariant->value})" 
                    : '';
                return "{$item->title}{$variant} (Qty: {$item->qty})";
            })->implode(', ');

            // Calculate total quantity
            $totalQuantity = $order->orderItems->sum('qty');

            // Calculate total weight (0.5kg per item as default)
            $totalWeight = $totalQuantity * 0.5;
            if ($totalWeight < 0.5) $totalWeight = 0.5;
            if ($totalWeight > 10) $totalWeight = 10;

            $payload = [
                'store_id' => $this->storeId,
                'merchant_order_id' => $order->order_number,
                'recipient_name' => $order->name,
                'recipient_phone' => $order->phone,
                'recipient_address' => $order->address . ', ' . $order->district,
                'delivery_type' => 48, // Normal delivery
                'item_type' => 2, // Parcel
                'special_instruction' => $order->delivery_notes ?? 'Handle with care',
                'item_quantity' => $totalQuantity,
                'item_weight' => (string) $totalWeight,
                'item_description' => $itemDescription,
                'amount_to_collect' => $order->payment_method === 'cash_on_delivery' ? $order->total : 0,
            ];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/aladdin/api/v1/orders', $payload);

            if ($response->failed()) {
                Log::error('Pathao API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload
                ]);
                throw new Exception('Failed to create order: ' . $response->body());
            }

            $responseData = $response->json();

            // Update order courier_entry status
            $order->update(['courier_entry' => true]);

            return $responseData;
        } catch (Exception $e) {
            Log::error('Pathao order creation error: ' . $e->getMessage());
            throw $e;
        }
    }
}