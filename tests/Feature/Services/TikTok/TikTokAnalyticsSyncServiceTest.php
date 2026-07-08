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

        protected function getClient(PlatformAccount $account, string $version): mixed
        {
            return $this->testClient;
        }
    };
}

it('syncs shop performance snapshot from a single aggregated interval', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAnalytics = new class
    {
        public array $capturedParams = [];

        public function getShopPerformance(array $params): array
        {
            $this->capturedParams = $params;

            return [
                'performance' => [
                    'intervals' => [
                        [
                            'orders' => 150,
                            'gmv' => ['amount' => '25000.50', 'currency' => 'MYR'],
                            'buyers' => 120,
                            'product_impressions' => 80000,
                            'product_page_views' => 2400,
                            'product_impression_breakdowns' => [
                                ['type' => 'VIDEO', 'amount' => 50000],
                                ['type' => 'LIVE', 'amount' => 30000],
                            ],
                        ],
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
    $snapshot = $service->syncShopPerformance($account);

    // The intervals[0] read only holds when we ask TikTok for a single
    // aggregated interval — otherwise it returns a per-day breakdown.
    expect($fakeAnalytics->capturedParams['granularity'])->toBe('ALL');

    expect($snapshot)->toBeInstanceOf(TiktokShopPerformanceSnapshot::class)
        ->and($snapshot->platform_account_id)->toBe($account->id)
        ->and($snapshot->total_orders)->toBe(150)
        ->and((float) $snapshot->total_gmv)->toBe(25000.50)
        ->and($snapshot->total_buyers)->toBe(120)
        ->and($snapshot->total_video_views)->toBe(50000)
        ->and($snapshot->total_product_impressions)->toBe(80000)
        // buyers / product_page_views => 120 / 2400 * 100
        ->and((float) $snapshot->conversion_rate)->toBe(5.0)
        ->and($snapshot->fetched_at)->not->toBeNull()
        ->and($snapshot->raw_response)->toBeArray();
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
