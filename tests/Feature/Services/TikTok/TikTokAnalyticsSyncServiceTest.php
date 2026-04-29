<?php

declare(strict_types=1);

use App\Exceptions\MissingPlatformAppConnectionException;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use App\Models\PlatformApp;
use App\Models\TiktokShopPerformanceSnapshot;
use App\Models\User;
use App\Services\TikTok\TikTokAnalyticsSyncService;
use App\Services\TikTok\TikTokAuthService;
use App\Services\TikTok\TikTokClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAnalyticsService(object $fakeClient): TikTokAnalyticsSyncService
{
    $authMock = Mockery::mock(TikTokAuthService::class);
    $factoryMock = Mockery::mock(TikTokClientFactory::class);

    return new class($factoryMock, $authMock, $fakeClient) extends TikTokAnalyticsSyncService
    {
        private object $testClient;

        public function __construct(
            TikTokClientFactory $clientFactory,
            TikTokAuthService $authService,
            object $testClient
        ) {
            parent::__construct($clientFactory, $authService);
            $this->testClient = $testClient;
        }

        protected function getClient(PlatformAccount $account): mixed
        {
            return $this->testClient;
        }
    };
}

it('syncs shop performance snapshot', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAnalytics = new class
    {
        public function getShopPerformance(): array
        {
            return [
                'total_orders' => 150,
                'total_gmv' => 25000.50,
                'total_buyers' => 120,
                'total_video_views' => 50000,
                'total_product_impressions' => 80000,
                'conversion_rate' => 0.0325,
            ];
        }
    };

    $fakeClient = new class($fakeAnalytics)
    {
        public object $Analytics;

        public function __construct(object $analytics)
        {
            $this->Analytics = $analytics;
        }
    };

    $service = makeAnalyticsService($fakeClient);
    $snapshot = $service->syncShopPerformance($account);

    expect($snapshot)->toBeInstanceOf(TiktokShopPerformanceSnapshot::class)
        ->and($snapshot->platform_account_id)->toBe($account->id)
        ->and($snapshot->total_orders)->toBe(150)
        ->and((float) $snapshot->total_gmv)->toBe(25000.50)
        ->and($snapshot->total_buyers)->toBe(120)
        ->and($snapshot->total_video_views)->toBe(50000)
        ->and($snapshot->total_product_impressions)->toBe(80000)
        ->and($snapshot->fetched_at)->not->toBeNull()
        ->and($snapshot->raw_response)->toBeArray()
        ->and($snapshot->raw_response['total_orders'])->toBe(150);
});

it('syncs product performance list', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAnalytics = new class
    {
        public function getShopProductPerformanceList(array $params): array
        {
            return [
                'products' => [
                    [
                        'product_id' => 'prod_001',
                        'impressions' => 5000,
                        'clicks' => 300,
                        'orders' => 45,
                        'gmv' => 1500.00,
                        'buyers' => 40,
                        'conversion_rate' => 0.06,
                    ],
                    [
                        'product_id' => 'prod_002',
                        'impressions' => 3000,
                        'clicks' => 150,
                        'orders' => 20,
                        'gmv' => 800.00,
                        'buyers' => 18,
                        'conversion_rate' => 0.04,
                    ],
                ],
            ];
        }
    };

    $fakeClient = new class($fakeAnalytics)
    {
        public object $Analytics;

        public function __construct(object $analytics)
        {
            $this->Analytics = $analytics;
        }
    };

    $service = makeAnalyticsService($fakeClient);
    $count = $service->syncProductPerformance($account);

    expect($count)->toBe(2);

    $this->assertDatabaseCount('tiktok_product_performance', 2);
    $this->assertDatabaseHas('tiktok_product_performance', [
        'platform_account_id' => $account->id,
        'tiktok_product_id' => 'prod_001',
        'impressions' => 5000,
        'clicks' => 300,
        'orders' => 45,
        'buyers' => 40,
    ]);
    $this->assertDatabaseHas('tiktok_product_performance', [
        'platform_account_id' => $account->id,
        'tiktok_product_id' => 'prod_002',
        'impressions' => 3000,
        'clicks' => 150,
        'orders' => 20,
        'buyers' => 18,
    ]);
});

it('refuses to sync analytics when only multi-channel credential exists', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $user = User::factory()->create();

    $multiChannelApp = PlatformApp::factory()->multiChannel()->create(['platform_id' => $platform->id]);

    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $user->id,
    ]);

    $cred = new PlatformApiCredential([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_app_id' => $multiChannelApp->id,
        'credential_type' => 'oauth_token',
        'name' => 'MC',
        'is_active' => true,
        'expires_at' => now()->addHours(20),
    ]);
    $cred->setValue('token');
    $cred->save();

    $service = app(TikTokAnalyticsSyncService::class);

    expect(fn () => $service->syncShopPerformance($account))
        ->toThrow(MissingPlatformAppConnectionException::class);
});
