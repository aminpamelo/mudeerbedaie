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

    $matcher = app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class);

    return new class($factoryMock, $authMock, $matcher, $fakeClient) extends TikTokLiveSyncService
    {
        private object $testClient;

        public function __construct(
            TikTokClientFactory $clientFactory,
            TikTokAuthService $authService,
            \App\Services\LiveHost\Tiktok\LiveSessionMatcher $matcher,
            object $testClient,
        ) {
            parent::__construct($clientFactory, $authService, $matcher);
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
                'live_stream_sessions' => [[
                    'id' => 'live_12345',
                    'title' => 'Friday Night Promo',
                    'username' => 'amarmirzabedaie',
                    'start_time' => '1746000000',
                    'end_time' => '1746003600',
                    'sales_performance' => [
                        'gmv' => ['amount' => '1234.56', 'currency' => 'MYR'],
                        '24h_live_gmv' => ['amount' => '1500.00', 'currency' => 'MYR'],
                        'avg_price' => ['amount' => '45.00', 'currency' => 'MYR'],
                        'products_added' => 10,
                        'different_products_sold' => 5,
                        'sku_orders' => 25,
                        'items_sold' => 30,
                        'customers' => 20,
                        'click_to_order_rate' => '5.00%',
                    ],
                    'interaction_performance' => [
                        'viewers' => 1500,
                        'views' => 5000,
                        'avg_viewing_duration' => '120',
                        'comments' => 50,
                        'shares' => 10,
                        'likes' => 200,
                        'new_followers' => 15,
                        'product_impressions' => 8000,
                        'product_clicks' => 400,
                        'click_through_rate' => '5.00%',
                    ],
                ]],
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
                    'sales_performance' => [
                        'gmv' => ['amount' => '99', 'currency' => 'USD'],
                    ],
                ]],
                'next_page_token' => null,
            ];
        }
    };
    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $row = TiktokLiveReport::firstWhere('tiktok_live_id', 'live_x');
    expect($row->gmv_myr)->toBeNull()
        ->and($row->raw_row_json['sales_performance']['gmv']['amount'])->toBe('99');
});

it('looks up creator_platform_user_id from creator_handle and matches a LiveSession', function () {
    $account = PlatformAccount::factory()->create();

    // Hub: a User-LiveHostPlatformAccount pivot mapping the API's username
    // (creator_handle) to a numeric creator id (creator_platform_user_id)
    $user = \App\Models\User::factory()->create();
    $pivot = \App\Models\LiveHostPlatformAccount::factory()->create([
        'user_id' => $user->id,
        'platform_account_id' => $account->id,
        'creator_handle' => 'amarmirzabedaie',
        'creator_platform_user_id' => '6526684195492729856',
    ]);

    // LiveSession matchable by actual_start_at within 30min of API start.
    // Must reference the pivot via live_host_platform_account_id so the
    // matcher's whereHas('liveHostPlatformAccount', ...) clause hits.
    $apiStartTime = now()->subMinutes(10);
    $session = \App\Models\LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'actual_start_at' => $apiStartTime,
        'status' => 'completed',
    ]);

    $fakeAnalytics = new class($apiStartTime)
    {
        public function __construct(private \Carbon\Carbon $startTime) {}

        public function getShopLivePerformanceList(array $params): array
        {
            return [
                'live_stream_sessions' => [[
                    'id' => 'live_match_1',
                    'username' => 'amarmirzabedaie',
                    'start_time' => (string) $this->startTime->timestamp,
                    'end_time' => (string) $this->startTime->copy()->addMinutes(30)->timestamp,
                    'sales_performance' => [
                        'gmv' => ['amount' => '500', 'currency' => 'MYR'],
                    ],
                ]],
                'next_page_token' => null,
            ];
        }
    };

    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $row = TiktokLiveReport::firstWhere('tiktok_live_id', 'live_match_1');
    expect($row->tiktok_creator_id)->toBe('6526684195492729856') // looked up
        ->and($row->matched_live_session_id)->toBe($session->id); // matched
});

it('creates a paired ActualLiveRecord per upserted TiktokLiveReport', function () {
    $account = PlatformAccount::factory()->create();
    $fakeAnalytics = new class
    {
        public function getShopLivePerformanceList(array $params): array
        {
            return [
                'live_stream_sessions' => [[
                    'id' => 'live_alr_1',
                    'username' => 'host1',
                    'start_time' => '1746000000',
                    'end_time' => '1746003600',
                    'sales_performance' => [
                        'gmv' => ['amount' => '300', 'currency' => 'MYR'],
                    ],
                    'interaction_performance' => [
                        'viewers' => 50,
                    ],
                ]],
                'next_page_token' => null,
            ];
        }
    };
    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $alr = \App\Models\ActualLiveRecord::where('source', 'api_sync')
        ->where('source_record_id', 'live_alr_1')
        ->where('platform_account_id', $account->id)
        ->first();

    expect($alr)->not->toBeNull()
        ->and($alr->creator_handle)->toBe('host1')
        ->and((float) $alr->gmv_myr)->toBe(300.00)
        ->and($alr->viewers)->toBe(50);
});

it('re-syncing the same live preserves matched_live_session_id', function () {
    $account = PlatformAccount::factory()->create();
    $session = \App\Models\LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => now()->subDay(), // far enough away that it would NOT auto-match
    ]);

    $payload = fn (int $gmv) => [
        'live_stream_sessions' => [[
            'id' => 'live_resync',
            'username' => 'h',
            'start_time' => '1746000000',
            'end_time' => '1746003600',
            'sales_performance' => [
                'gmv' => ['amount' => (string) $gmv, 'currency' => 'MYR'],
            ],
        ]],
        'next_page_token' => null,
    ];

    $fake = new class($payload)
    {
        public $payload;

        public int $call = 0;

        public function __construct($payload)
        {
            $this->payload = $payload;
        }

        public function getShopLivePerformanceList(array $params): array
        {
            $this->call++;

            return ($this->payload)($this->call === 1 ? 100 : 200);
        }
    };

    $service = makeLiveSyncService($fake);
    $service->syncLivePerformance($account);

    // Manually link this row (simulating a successful match or an admin's manual fix)
    \App\Models\TiktokLiveReport::firstWhere('tiktok_live_id', 'live_resync')
        ->update(['matched_live_session_id' => $session->id]);

    // Re-sync: matched_live_session_id must survive the update; gmv must refresh
    $service->syncLivePerformance($account);

    $row = \App\Models\TiktokLiveReport::firstWhere('tiktok_live_id', 'live_resync');
    expect($row->matched_live_session_id)->toBe($session->id)
        ->and((float) $row->gmv_myr)->toBe(200.00);
});

it('leaves duration_seconds null when end_time is missing from the API payload', function () {
    $account = PlatformAccount::factory()->create();
    $fakeAnalytics = new class
    {
        public function getShopLivePerformanceList(array $params): array
        {
            return [
                'live_stream_sessions' => [[
                    'id' => 'live_no_end',
                    'username' => 'h',
                    'start_time' => '1746000000',
                    // end_time deliberately omitted
                    'sales_performance' => [
                        'gmv' => ['amount' => '10', 'currency' => 'MYR'],
                    ],
                ]],
                'next_page_token' => null,
            ];
        }
    };
    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $row = TiktokLiveReport::firstWhere('tiktok_live_id', 'live_no_end');
    expect($row->duration_seconds)->toBeNull();

    $alr = \App\Models\ActualLiveRecord::where('source_record_id', 'live_no_end')->first();
    expect($alr->duration_seconds)->toBeNull();
});

it('skips cleanly and flags the account when API returns not_authorized', function () {
    $account = PlatformAccount::factory()->create();
    $fake = new class
    {
        public function getShopLivePerformanceList(array $params): array
        {
            throw new \EcomPHP\TiktokShop\Errors\ResponseException('not_authorized', 105005);
        }
    };
    $service = makeLiveSyncService($fake);

    $result = $service->syncLivePerformance($account);

    $account->refresh();
    expect($result['synced'])->toBe(0)
        ->and($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(0)
        ->and($result['matched'])->toBe(0)
        ->and($result['unmatched'])->toBe(0)
        ->and($result['pages'])->toBe(0)
        ->and($account->metadata['live_api_supported'] ?? null)->toBeFalse();
});

it('short-circuits on subsequent runs when account is flagged live_api_supported=false', function () {
    $account = PlatformAccount::factory()->create();
    $account->update(['metadata' => array_merge($account->metadata ?? [], ['live_api_supported' => false])]);

    // Use a fake that would throw if called — we expect zero invocations
    $fake = new class
    {
        public int $called = 0;

        public function getShopLivePerformanceList(array $params): array
        {
            $this->called++;
            throw new \RuntimeException('Should not have been called');
        }
    };

    $service = makeLiveSyncService($fake);
    $result = $service->syncLivePerformance($account);

    expect($result['synced'])->toBe(0);
    expect($fake->called)->toBe(0);
});
