<?php

namespace Tests\Unit;

use App\Models\FraudCheckerSetting;
use App\Services\BdcourierFraudCheckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BdcourierFraudCheckServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.bdcourier.fraud_check_url', 'https://api.bdcourier.com/courier-check');
        config()->set('services.bdcourier.my_plan_url', 'https://api.bdcourier.com/my-plan');
    }

    public function test_it_normalizes_the_phone_and_maps_provider_results(): void
    {
        Http::fake([
            'https://api.bdcourier.com/courier-check' => Http::response([
                'status' => 'success',
                'data' => [
                    'pathao' => [
                        'name' => 'Pathao',
                        'total_parcel' => 10,
                        'success_parcel' => 8,
                        'cancelled_parcel' => 2,
                        'success_ratio' => 80,
                    ],
                    'summary' => [
                        'total_parcel' => 10,
                        'success_parcel' => 8,
                        'cancelled_parcel' => 2,
                        'success_ratio' => 80,
                    ],
                ],
                'reports' => [],
            ], 200),
        ]);

        $settings = new FraudCheckerSetting([
            'api_key' => 'test-api-key',
            'is_active' => true,
            'cache_minutes' => 0,
        ]);

        $result = app(BdcourierFraudCheckService::class)->check(
            phone: '+880 1712-345678',
            settings: $settings
        );

        $this->assertSame('01712345678', $result['phone']);
        $this->assertSame(80, $result['summary']['success_ratio']);
        $this->assertSame('Pathao', $result['couriers'][0]['name']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.bdcourier.com/courier-check'
                && $request->data()['phone'] === '01712345678'
                && $request->header('Authorization')[0] === 'Bearer test-api-key';
        });
    }

    public function test_it_caches_successful_results_for_the_configured_duration(): void
    {
        Http::fake([
            'https://api.bdcourier.com/courier-check' => Http::response([
                'status' => 'success',
                'data' => ['summary' => ['total_parcel' => 1, 'success_parcel' => 1, 'cancelled_parcel' => 0, 'success_ratio' => 100]],
                'reports' => [],
            ], 200),
        ]);

        $settings = new FraudCheckerSetting([
            'api_key' => 'test-api-key',
            'is_active' => true,
            'cache_minutes' => 60,
        ]);
        $service = app(BdcourierFraudCheckService::class);

        $service->check('01712345678', settings: $settings);
        $cached = $service->check('01712345678', settings: $settings);

        $this->assertTrue($cached['meta']['cached']);
        Http::assertSentCount(1);
    }

    public function test_it_rejects_invalid_phone_numbers_before_calling_provider(): void
    {
        Http::fake();

        $settings = new FraudCheckerSetting([
            'api_key' => 'test-api-key',
            'is_active' => true,
            'cache_minutes' => 0,
        ]);

        $this->expectExceptionMessage('valid Bangladesh mobile number');

        app(BdcourierFraudCheckService::class)->check('12345', settings: $settings);
        Http::assertNothingSent();
    }

    public function test_it_maps_the_provider_plan_response(): void
    {
        Http::fake([
            'https://api.bdcourier.com/my-plan' => Http::response([
                'status' => 'success',
                'data' => [
                    'has_subscription' => true,
                    'plan_id' => 2,
                    'plan_name' => 'Paid Plan',
                    'plan_type' => 'paid',
                    'is_free' => false,
                    'status' => 'active',
                    'next_due_date' => '2025-12-31',
                    'expires_at' => '2025-12-31 23:59:59',
                    'days_remaining' => 365,
                    'frequency' => 'monthly',
                    'price' => 199,
                    'api_calls' => 150,
                    'paid_calls' => 50,
                    'call_limit' => 5,
                    'paid_limit' => 100,
                    'remaining_free_calls' => 0,
                    'remaining_paid_calls' => 50,
                ],
            ], 200),
        ]);

        $settings = new FraudCheckerSetting([
            'api_key' => 'test-api-key',
            'is_active' => false,
            'cache_minutes' => 0,
        ]);

        $result = app(BdcourierFraudCheckService::class)->plan(
            allowDisabled: true,
            settings: $settings
        );

        $this->assertSame('Paid Plan', $result['plan']['plan_name']);
        $this->assertSame(50, $result['plan']['remaining_paid_calls']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.bdcourier.com/my-plan'
            && $request->header('Authorization')[0] === 'Bearer test-api-key');
    }
}
