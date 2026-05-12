<?php

declare(strict_types=1);

use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Services\TikTok\TikTokAuthService;
use App\Services\TikTok\TikTokClientFactory;
use App\Services\TikTok\TikTokLiveSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a service with stubbed factory/auth and an injected fake "client"
 * whose ->Analytics returns the rows we want to assert against. Pattern
 * borrowed from TikTokAnalyticsSyncServiceTest so the seam stays familiar.
 */
function makeLiveSyncService(object $fakeAnalytics)
{
    $authMock = Mockery::mock(TikTokAuthService::class);
    $factoryMock = Mockery::mock(TikTokClientFactory::class);

    $fakeClient = new class($fakeAnalytics)
    {
        public object $Analytics;

        public function __construct(object $analytics)
        {
            $this->Analytics = $analytics;
        }
    };

    return new class($factoryMock, $authMock, $fakeClient) extends TikTokLiveSyncService
    {
        private object $testClient;

        public function __construct(
            TikTokClientFactory $clientFactory,
            TikTokAuthService $authService,
            object $testClient,
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

it('upserts a new TiktokLiveReport from one API live_stream_session row', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAnalytics = new class
    {
        public function getShopLivePerformanceList(array $params): array
        {
            return [
                'live_stream_sessions' => [
                    [
                        'id' => 'live_12345',
                        'title' => 'Friday Night Promo',
                        'username' => 'amarmirzabedaie',
                        'start_time' => '1746000000',
                        'end_time' => '1746003600',
                        'gmv' => ['amount' => '1234.56', 'currency' => 'MYR'],
                        '24h_live_gmv' => ['amount' => '1500.00', 'currency' => 'MYR'],
                        'avg_price' => ['amount' => '45.00', 'currency' => 'MYR'],
                        'products_added' => 10,
                        'different_products_sold' => 5,
                        'sku_orders' => 25,
                        'unit_sold' => 30,
                        'customers' => 20,
                        'click_to_order_rate' => '0.05',
                        'viewers' => 1500,
                        'views' => 5000,
                        'avg_viewing_duration' => '120',
                        'comments' => 50,
                        'shares' => 10,
                        'likes' => 200,
                        'new_followers' => 15,
                        'product_impressions' => 8000,
                        'product_clicks' => 400,
                        'click_through_rate' => '0.05',
                        'acu' => 800,
                        'pcu' => 1200,
                    ],
                ],
                'next_page_token' => null,
                'total_count' => 1,
            ];
        }
    };

    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $row = TiktokLiveReport::where('platform_account_id', $account->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->tiktok_live_id)->toBe('live_12345')
        ->and($row->source)->toBe('api')
        ->and($row->creator_nickname)->toBe('amarmirzabedaie')
        ->and($row->creator_display_name)->toBe('amarmirzabedaie')
        ->and((float) $row->gmv_myr)->toBe(1234.56)
        ->and((float) $row->live_attributed_gmv_myr)->toBe(1500.00)
        ->and((float) $row->avg_price_myr)->toBe(45.00)
        ->and($row->duration_seconds)->toBe(3600)
        ->and($row->viewers)->toBe(1500)
        ->and($row->views)->toBe(5000)
        ->and($row->synced_at)->not->toBeNull();
});

it('writes null to gmv_myr when currency is not MYR but preserves raw_row_json', function () {
    $account = PlatformAccount::factory()->create();
    $fakeAnalytics = new class
    {
        public function getShopLivePerformanceList(array $params): array
        {
            return [
                'live_stream_sessions' => [[
                    'id' => 'live_x',
                    'username' => 'h',
                    'start_time' => '1746000000',
                    'end_time' => '1746000100',
                    'gmv' => ['amount' => '99', 'currency' => 'USD'],
                ]],
                'next_page_token' => null,
            ];
        }
    };
    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $row = TiktokLiveReport::firstWhere('tiktok_live_id', 'live_x');
    expect($row->gmv_myr)->toBeNull()
        ->and($row->raw_row_json['gmv']['amount'])->toBe('99');
});
