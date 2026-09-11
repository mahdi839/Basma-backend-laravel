<?php

namespace App\Services;

use App\Exceptions\FraudCheckerException;
use App\Models\FraudCheckerSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BdcourierFraudCheckService
{
    public function plan(
        bool $forceRefresh = false,
        bool $allowDisabled = false,
        ?FraudCheckerSetting $settings = null
    ): array {
        $settings ??= FraudCheckerSetting::current();

        if (! $settings || blank($settings->api_key)) {
            throw new FraudCheckerException(
                'Courier checker API key is not configured. Open Dashboard Settings to configure it.'
            );
        }

        if (! $allowDisabled && ! $settings->is_active) {
            throw new FraudCheckerException('Courier checker is currently disabled.');
        }

        $cacheMinutes = min(15, max(0, (int) $settings->cache_minutes));
        $cacheKey = 'bdcourier-plan:v1:'.hash('sha256', $settings->api_key);

        if (! $forceRefresh && $cacheMinutes > 0) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $cached['meta']['cached'] = true;

                return $cached;
            }
        }

        $result = $this->requestPlan($settings->api_key);

        if ($cacheMinutes > 0) {
            Cache::put($cacheKey, $result, now()->addMinutes($cacheMinutes));
        }

        return $result;
    }

    public function check(
        string $phone,
        bool $forceRefresh = false,
        bool $allowDisabled = false,
        ?FraudCheckerSetting $settings = null
    ): array {
        $settings ??= FraudCheckerSetting::current();

        if (! $settings || blank($settings->api_key)) {
            throw new FraudCheckerException(
                'Courier checker API key is not configured. Open Dashboard Settings to configure it.'
            );
        }

        if (! $allowDisabled && ! $settings->is_active) {
            throw new FraudCheckerException('Courier checker is currently disabled.');
        }

        $normalizedPhone = $this->normalizeBangladeshPhone($phone);
        $cacheMinutes = max(0, (int) $settings->cache_minutes);
        $cacheKey = $this->cacheKey($normalizedPhone, $settings->api_key);

        if (! $forceRefresh && $cacheMinutes > 0) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $cached['meta']['cached'] = true;

                return $cached;
            }
        }

        $result = $this->requestProvider($normalizedPhone, $settings->api_key);

        if ($cacheMinutes > 0) {
            Cache::put($cacheKey, $result, now()->addMinutes($cacheMinutes));
        }

        return $result;
    }

    public function normalizeBangladeshPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            $digits = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^01[3-9]\d{8}$/', $digits)) {
            throw new FraudCheckerException(
                'A valid Bangladesh mobile number is required (for example, 017XXXXXXXX).'
            );
        }

        return $digits;
    }

    private function requestProvider(string $phone, string $apiKey): array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->timeout((int) config('services.bdcourier.timeout', 15))
                ->post((string) config('services.bdcourier.fraud_check_url'), [
                    'phone' => $phone,
                ]);
        } catch (ConnectionException) {
            throw new FraudCheckerException(
                'The courier checker service could not be reached. Please try again.',
                502
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new FraudCheckerException(
                'The courier checker returned an invalid response.',
                502
            );
        }

        if (! $response->successful() || ($payload['status'] ?? null) !== 'success') {
            $message = (string) ($payload['message'] ?? 'Courier check failed.');
            $status = str_contains(strtolower($message), 'not found') ? 404 : 422;

            if (in_array($response->status(), [401, 403], true)) {
                $message = 'The courier checker rejected the configured API key.';
            } elseif ($response->status() === 429) {
                $message = 'The courier checker rate limit was reached. Please try again later.';
                $status = 429;
            } elseif ($response->serverError()) {
                $message = 'The courier checker service is temporarily unavailable.';
                $status = 502;
            }

            throw new FraudCheckerException($message, $status);
        }

        return $this->normalizeResponse($phone, $payload);
    }

    private function requestPlan(string $apiKey): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout((int) config('services.bdcourier.timeout', 15))
                ->get((string) config('services.bdcourier.my_plan_url'));
        } catch (ConnectionException) {
            throw new FraudCheckerException(
                'The courier plan service could not be reached. Please try again.',
                502
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new FraudCheckerException(
                'The courier plan service returned an invalid response.',
                502
            );
        }

        if (! $response->successful() || ($payload['status'] ?? null) !== 'success') {
            $message = (string) ($payload['message'] ?? 'Unable to load the courier plan.');
            $status = $response->status();

            if (in_array($status, [401, 403], true)) {
                $message = 'The courier checker rejected the configured API key.';
                $status = 422;
            } elseif ($status === 429) {
                $message = 'The courier checker rate limit was reached. Please try again later.';
            } elseif ($response->serverError()) {
                $message = 'The courier plan service is temporarily unavailable.';
                $status = 502;
            } elseif ($status < 400) {
                $status = 422;
            }

            throw new FraudCheckerException($message, $status);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return [
            'plan' => [
                'has_subscription' => (bool) ($data['has_subscription'] ?? false),
                'plan_id' => $data['plan_id'] ?? null,
                'plan_name' => (string) ($data['plan_name'] ?? 'No active plan'),
                'plan_type' => (string) ($data['plan_type'] ?? ''),
                'is_free' => (bool) ($data['is_free'] ?? false),
                'status' => (string) ($data['status'] ?? 'unknown'),
                'next_due_date' => $data['next_due_date'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'days_remaining' => max(0, (int) ($data['days_remaining'] ?? 0)),
                'frequency' => (string) ($data['frequency'] ?? ''),
                'price' => is_numeric($data['price'] ?? null) ? (float) $data['price'] : null,
                'api_calls' => max(0, (int) ($data['api_calls'] ?? 0)),
                'paid_calls' => max(0, (int) ($data['paid_calls'] ?? 0)),
                'call_limit' => max(0, (int) ($data['call_limit'] ?? 0)),
                'paid_limit' => max(0, (int) ($data['paid_limit'] ?? 0)),
                'remaining_free_calls' => max(0, (int) ($data['remaining_free_calls'] ?? 0)),
                'remaining_paid_calls' => max(0, (int) ($data['remaining_paid_calls'] ?? 0)),
            ],
            'meta' => [
                'cached' => false,
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function normalizeResponse(string $phone, array $payload): array
    {
        $providerData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $summary = is_array($providerData['summary'] ?? null)
            ? $this->parcelStats($providerData['summary'])
            : $this->parcelStats([]);

        unset($providerData['summary']);

        $couriers = collect($providerData)
            ->filter(fn ($courier) => is_array($courier))
            ->map(function (array $courier, string $key): array {
                return [
                    'key' => $key,
                    'name' => (string) ($courier['name'] ?? ucfirst($key)),
                    'logo' => $courier['logo'] ?? null,
                    ...$this->parcelStats($courier),
                ];
            })
            ->values()
            ->all();

        $reports = collect(is_array($payload['reports'] ?? null) ? $payload['reports'] : [])
            ->filter(fn ($report) => is_array($report))
            ->map(fn (array $report): array => [
                'id' => isset($report['id']) ? (string) $report['id'] : null,
                'name' => (string) ($report['name'] ?? 'Unknown'),
                'details' => (string) ($report['details'] ?? ''),
                'created_at' => $report['created_at'] ?? null,
                'courier_logo' => $report['courierLogo'] ?? null,
                'courier_name' => (string) ($report['courierName'] ?? 'Unknown courier'),
            ])
            ->values()
            ->all();

        return [
            'phone' => $phone,
            'summary' => $summary,
            'couriers' => $couriers,
            'reports' => $reports,
            'meta' => [
                'cached' => false,
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function parcelStats(array $data): array
    {
        $ratio = max(0, min(100, round((float) ($data['success_ratio'] ?? 0), 2)));

        return [
            'total_parcel' => max(0, (int) ($data['total_parcel'] ?? 0)),
            'success_parcel' => max(0, (int) ($data['success_parcel'] ?? 0)),
            'cancelled_parcel' => max(0, (int) ($data['cancelled_parcel'] ?? 0)),
            'success_ratio' => $ratio == (int) $ratio ? (int) $ratio : $ratio,
        ];
    }

    private function cacheKey(string $phone, string $apiKey): string
    {
        return 'bdcourier-check:v1:'.hash('sha256', $phone.'|'.$apiKey);
    }
}
