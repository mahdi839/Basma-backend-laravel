<?php

namespace App\Services;

use App\Models\PathaoToken;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                    return  $this->issueNewToken();
                }
            }

            return  $this->issueNewToken();
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
        } catch (Exception $e) {
            Log::error('Pathao token issue error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function refreshToken($refresh_token) {}
}
