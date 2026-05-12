# TikTok LIVE API Sync — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the manual TikTok Live CSV upload chore with an automatic API sync that populates the same `tiktok_live_reports` table (plus paired `actual_live_records`) on a nightly schedule and on-demand, while keeping CSV as a fallback.

**Architecture:** A new `App\Services\TikTok\TikTokLiveSyncService` calls TikTok's new `GET /analytics/202508/shop_lives/performance` endpoint through a thin `AnalyticsExtended` resource (extends the EcomPHP SDK's `Analytics` class to add the missing methods while reusing its signing/auth). Each LIVE row upserts into `tiktok_live_reports` keyed on a new `tiktok_live_id` column, then is matched to a `LiveSession` via the existing `LiveSessionMatcher` service, and a paired `ActualLiveRecord` row is written so downstream commission/payroll flows behave identically to the CSV path.

**Tech Stack:** Laravel 12, PHP 8.3, Pest 4, EcomPHP TikTok Shop SDK v2.8, SQLite (dev) / MySQL (prod), Volt (admin UI), Inertia React (Live Host Desk UI).

**Design doc:** [docs/plans/2026-05-12-tiktok-live-api-sync-design.md](2026-05-12-tiktok-live-api-sync-design.md)

---

## Conventions

- All migrations dual-driver (MySQL + SQLite) per project rule. Use `Schema::table` for additive columns.
- All tests use Pest. Each task creates failing tests first.
- One small commit per task. No `--no-verify`. No `--amend`.
- Run `vendor/bin/pint --dirty` before each commit.
- Use `php artisan test --compact tests/path/to/File.php` to run targeted tests.
- Reference the existing `SyncTikTokAnalytics` job and `TikTokAnalyticsSyncService` as the pattern to mirror.

---

## Task 1: Migration — add columns to `tiktok_live_reports` and `platform_accounts`

**Files:**
- Create: `database/migrations/2026_05_12_140000_add_api_sync_columns_to_tiktok_live_reports.php`
- Create: `database/migrations/2026_05_12_140100_add_last_live_analytics_sync_at_to_platform_accounts.php`

**Step 1: Write the migration for `tiktok_live_reports`**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->string('tiktok_live_id')->nullable()->after('id');
            $table->foreignId('platform_account_id')->nullable()->after('tiktok_live_id')
                ->constrained('platform_accounts')->nullOnDelete();
            $table->string('source', 16)->default('csv')->after('platform_account_id');
            $table->timestamp('synced_at')->nullable()->after('source');
        });

        // Plain unique works on both drivers and tolerates multiple NULL pairs
        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->unique(['platform_account_id', 'tiktok_live_id'], 'tlr_account_live_unique');
        });

        // Backfill platform_account_id for already-matched CSV rows
        DB::table('tiktok_live_reports')
            ->whereNotNull('matched_live_session_id')
            ->whereNull('platform_account_id')
            ->update([
                'platform_account_id' => DB::raw(
                    '(SELECT live_sessions.platform_account_id FROM live_sessions '
                    .'WHERE live_sessions.id = tiktok_live_reports.matched_live_session_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->dropUnique('tlr_account_live_unique');
            $table->dropConstrainedForeignId('platform_account_id');
            $table->dropColumn(['tiktok_live_id', 'source', 'synced_at']);
        });
    }
};
```

**Step 2: Write the migration for `platform_accounts`**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->timestamp('last_live_analytics_sync_at')->nullable()->after('last_analytics_sync_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->dropColumn('last_live_analytics_sync_at');
        });
    }
};
```

> If `last_analytics_sync_at` does not exist on `platform_accounts`, replace `after('last_analytics_sync_at')` with `after('last_order_sync_at')` (which definitely exists per migration `2026_01_23_005527`).

**Step 3: Run the migrations**

Run: `php artisan migrate`
Expected: both migrations complete without error; `tiktok_live_reports` now has `tiktok_live_id`, `platform_account_id`, `source`, `synced_at`; `platform_accounts` has `last_live_analytics_sync_at`.

**Step 4: Verify rollback**

Run: `php artisan migrate:rollback --step=2 && php artisan migrate`
Expected: rolls back and re-applies cleanly on SQLite (dev). Note in commit message that MySQL parity must be smoke-tested before deploy.

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_05_12_140000_*.php database/migrations/2026_05_12_140100_*.php
git commit -m "feat(tiktok-live): add api sync columns to tiktok_live_reports + last_live_analytics_sync_at"
```

---

## Task 2: Update `TiktokLiveReport` model fillable + casts

**Files:**
- Modify: `app/Models/TiktokLiveReport.php`

**Step 1: Read the model**

Run: `cat app/Models/TiktokLiveReport.php`

**Step 2: Add the four new columns to `$fillable` and add a `synced_at` datetime cast**

Modify `$fillable` to include: `'tiktok_live_id', 'platform_account_id', 'source', 'synced_at'`.

Modify the casts method to include: `'synced_at' => 'datetime'`.

Add a `platformAccount()` relationship:

```php
public function platformAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(PlatformAccount::class);
}
```

**Step 3: Run model-touching tests to catch any regression**

Run: `php artisan test --compact tests/Feature/Services/TikTok tests/Feature/Jobs/ProcessTiktokImportJobTest.php`
Expected: all green (or pre-existing failures only — note any).

**Step 4: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/TiktokLiveReport.php
git commit -m "feat(tiktok-live): add fillables + platformAccount relation to TiktokLiveReport"
```

---

## Task 3: Create `AnalyticsExtended` SDK subclass

**Files:**
- Create: `app/Services/TikTok/Sdk/AnalyticsExtended.php`
- Create: `tests/Unit/Services/TikTok/Sdk/AnalyticsExtendedTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\TikTok\Sdk\AnalyticsExtended;

it('defines getShopLivePerformanceList and getShopLivePerformanceOverview methods', function () {
    expect(method_exists(AnalyticsExtended::class, 'getShopLivePerformanceList'))->toBeTrue();
    expect(method_exists(AnalyticsExtended::class, 'getShopLivePerformanceOverview'))->toBeTrue();
});

it('declares minimum_version 202508', function () {
    $reflection = new ReflectionClass(AnalyticsExtended::class);
    $prop = $reflection->getProperty('minimum_version');
    $prop->setAccessible(true);
    expect((int) $prop->getValue($reflection->newInstance()))->toBe(202508);
});
```

**Step 2: Run test to confirm it fails**

Run: `php artisan test --compact tests/Unit/Services/TikTok/Sdk/AnalyticsExtendedTest.php`
Expected: FAIL with "Class App\Services\TikTok\Sdk\AnalyticsExtended not found".

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok\Sdk;

use EcomPHP\TiktokShop\Resources\Analytics;
use GuzzleHttp\RequestOptions;

class AnalyticsExtended extends Analytics
{
    protected $minimum_version = 202508;

    /**
     * GET /analytics/{version}/shop_lives/performance
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getShopLivePerformanceList(array $params = []): array
    {
        return $this->call('GET', 'shop_lives/performance', [
            RequestOptions::QUERY => $params,
        ]);
    }

    /**
     * GET /analytics/{version}/shop_lives/overview_performance
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getShopLivePerformanceOverview(array $params = []): array
    {
        return $this->call('GET', 'shop_lives/overview_performance', [
            RequestOptions::QUERY => $params,
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/TikTok/Sdk/AnalyticsExtendedTest.php`
Expected: PASS (2 tests).

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/TikTok/Sdk/AnalyticsExtended.php tests/Unit/Services/TikTok/Sdk/AnalyticsExtendedTest.php
git commit -m "feat(tiktok-live): add AnalyticsExtended sdk subclass with shop_lives endpoints"
```

---

## Task 4: Create the `TikTokLiveSyncService` (skeleton + normalizer)

**Files:**
- Create: `app/Services/TikTok/TikTokLiveSyncService.php`
- Create: `tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`

This task only adds the service skeleton and the row-normalization step. Pagination, matching, and `ActualLiveRecord` paired-write come in Task 5.

**Step 1: Write the failing test for normalization**

```php
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
 * borrowed verbatim from TikTokAnalyticsSyncServiceTest so the seam stays
 * familiar.
 */
function makeLiveSyncService(object $fakeAnalytics)
{
    $authMock = Mockery::mock(TikTokAuthService::class);
    $factoryMock = Mockery::mock(TikTokClientFactory::class);

    $fakeClient = new class($fakeAnalytics)
    {
        public object $Analytics;
        public function __construct(object $analytics) { $this->Analytics = $analytics; }
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
```

**Step 2: Run test to confirm it fails**

Run: `php artisan test --compact tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`
Expected: FAIL with "Class App\Services\TikTok\TikTokLiveSyncService not found".

**Step 3: Write the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PlatformAccount;
use App\Models\PlatformApp;
use App\Models\TiktokLiveReport;
use App\Services\TikTok\Sdk\AnalyticsExtended;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TikTokLiveSyncService
{
    protected const REQUIRED_CATEGORY = PlatformApp::CATEGORY_ANALYTICS_REPORTING;

    private const API_VERSION = '202508';

    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService,
    ) {}

    /**
     * @return array{synced: int, created: int, updated: int, pages: int}
     */
    public function syncLivePerformance(
        PlatformAccount $account,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $client = $this->getClient($account);

        $from ??= now()->subDays(30);
        $to ??= now();

        $synced = 0;
        $created = 0;
        $updated = 0;
        $pages = 0;
        $pageToken = null;

        do {
            $params = [
                'start_date_ge' => $from->format('Y-m-d'),
                'end_date_lt' => $to->format('Y-m-d'),
                'page_size' => 100,
            ];
            if ($pageToken) {
                $params['page_token'] = $pageToken;
            }

            $response = $client->Analytics->getShopLivePerformanceList($params);
            $sessions = $response['live_stream_sessions'] ?? [];

            foreach ($sessions as $session) {
                $tiktokLiveId = $session['id'] ?? null;
                if (! $tiktokLiveId) {
                    continue;
                }

                $attrs = $this->normalize($session);

                /** @var TiktokLiveReport $report */
                $report = TiktokLiveReport::firstOrNew([
                    'platform_account_id' => $account->id,
                    'tiktok_live_id' => $tiktokLiveId,
                ]);
                $existed = $report->exists;

                // Preserve matched_live_session_id and import_id on re-sync
                $report->fill($attrs);
                $report->platform_account_id = $account->id;
                $report->tiktok_live_id = $tiktokLiveId;
                $report->source = 'api';
                $report->synced_at = now();
                $report->save();

                $synced++;
                $existed ? $updated++ : $created++;
            }

            $pageToken = $response['next_page_token'] ?? null;
            $pages++;
        } while ($pageToken && $pages < 50);

        Log::info('[TikTokLiveSync] Completed', [
            'account_id' => $account->id,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'pages' => $pages,
        ]);

        return compact('synced', 'created', 'updated', 'pages');
    }

    /**
     * Map an API live_stream_session payload into TiktokLiveReport columns.
     *
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function normalize(array $s): array
    {
        $start = isset($s['start_time']) ? Carbon::createFromTimestamp((int) $s['start_time']) : null;
        $end = isset($s['end_time']) ? Carbon::createFromTimestamp((int) $s['end_time']) : null;
        $duration = ($start && $end) ? max(0, $end->diffInSeconds($start)) : null;

        $myr = fn (?array $money) => (isset($money['currency']) && $money['currency'] === 'MYR')
            ? (float) ($money['amount'] ?? 0)
            : null;

        return [
            'creator_nickname' => $s['username'] ?? null,
            'creator_display_name' => $s['username'] ?? null,
            'tiktok_creator_id' => null, // API doesn't expose this here
            'launched_time' => $start,
            'duration_seconds' => $duration,
            'gmv_myr' => $myr($s['gmv'] ?? null),
            'live_attributed_gmv_myr' => $myr($s['24h_live_gmv'] ?? null),
            'avg_price_myr' => $myr($s['avg_price'] ?? null),
            'products_added' => $s['products_added'] ?? null,
            'products_sold' => $s['different_products_sold'] ?? null,
            'sku_orders' => $s['sku_orders'] ?? null,
            'items_sold' => $s['unit_sold'] ?? null,
            'unique_customers' => $s['customers'] ?? null,
            'click_to_order_rate' => $s['click_to_order_rate'] ?? null,
            'viewers' => $s['viewers'] ?? null,
            'views' => $s['views'] ?? null,
            'avg_view_duration_sec' => isset($s['avg_viewing_duration']) ? (int) $s['avg_viewing_duration'] : null,
            'comments' => $s['comments'] ?? null,
            'shares' => $s['shares'] ?? null,
            'likes' => $s['likes'] ?? null,
            'new_followers' => $s['new_followers'] ?? null,
            'product_impressions' => $s['product_impressions'] ?? null,
            'product_clicks' => $s['product_clicks'] ?? null,
            'ctr' => $s['click_through_rate'] ?? null,
            'raw_row_json' => $s,
        ];
    }

    protected function getClient(PlatformAccount $account): mixed
    {
        $app = $this->clientFactory->resolveApp($account, static::REQUIRED_CATEGORY);

        if ($this->authService->needsTokenRefresh($account, $app)) {
            Log::info('[TikTokLiveSync] Refreshing token before sync', [
                'account_id' => $account->id,
                'platform_app_id' => $app->id,
            ]);
            $this->authService->refreshToken($account, $app);
        }

        $client = $this->clientFactory->createClientForAccount($account, static::REQUIRED_CATEGORY);
        $client->useVersion(self::API_VERSION);

        // Swap in our extended Analytics resource so getShopLive* methods exist.
        // Reuses the SDK Client's internal HTTP client (signing + headers).
        $analytics = new \App\Services\TikTok\Sdk\AnalyticsExtended();
        $reflection = new \ReflectionMethod($client, 'httpClient');
        $reflection->setAccessible(true);
        $analytics->useHttpClient($reflection->invoke($client));
        $analytics->useVersion(self::API_VERSION);

        // Decorate $client so $client->Analytics returns our extended resource.
        return new class($analytics) {
            public function __construct(public object $Analytics) {}
        };
    }
}
```

> Note: this is one of the rare cases where reflection is justified — the SDK's `httpClient()` is `protected` and we need its already-configured Guzzle instance (with signing middleware + base URI). Calling it directly avoids replicating signing logic. If EcomPHP later makes it `public` or adds the LIVE methods natively, we delete this shim.

**Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`
Expected: PASS (2 tests).

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/TikTok/TikTokLiveSyncService.php tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php
git commit -m "feat(tiktok-live): add TikTokLiveSyncService skeleton with normalization + upsert"
```

---

## Task 5: Add matching + paired `ActualLiveRecord` write

**Files:**
- Modify: `app/Services/TikTok/TikTokLiveSyncService.php`
- Modify: `tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`

**Step 1: Add failing tests**

Append to the test file:

```php
it('runs LiveSessionMatcher and sets matched_live_session_id when a session matches', function () {
    $account = PlatformAccount::factory()->create();

    // Create a LiveHost mapping for this creator
    $host = \App\Models\LiveHost::factory()->create();
    \App\Models\LiveHostPlatformAccount::factory()
        ->for($host)
        ->state(['platform_account_id' => $account->id, 'platform_creator_id' => 'amarmirzabedaie'])
        ->create();

    // Create a LiveSession that overlaps the API row's launched_time
    $session = \App\Models\LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_id' => $host->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $fakeAnalytics = new class
    {
        public function getShopLivePerformanceList(array $params): array
        {
            return [
                'live_stream_sessions' => [[
                    'id' => 'live_match_1',
                    'username' => 'amarmirzabedaie',
                    'start_time' => (string) now()->timestamp,
                    'end_time' => (string) now()->addMinutes(30)->timestamp,
                    'gmv' => ['amount' => '500', 'currency' => 'MYR'],
                ]],
                'next_page_token' => null,
            ];
        }
    };

    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $row = TiktokLiveReport::firstWhere('tiktok_live_id', 'live_match_1');
    expect($row->matched_live_session_id)->toBe($session->id);
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
                    'gmv' => ['amount' => '300', 'currency' => 'MYR'],
                ]],
                'next_page_token' => null,
            ];
        }
    };
    $service = makeLiveSyncService($fakeAnalytics);
    $service->syncLivePerformance($account);

    $alr = \App\Models\ActualLiveRecord::where('source', 'api')
        ->where('source_record_id', 'live_alr_1')
        ->where('platform_account_id', $account->id)
        ->first();

    expect($alr)->not->toBeNull()
        ->and($alr->creator_handle)->toBe('host1')
        ->and((float) $alr->gmv_myr)->toBe(300.00);
});

it('re-syncing the same live preserves matched_live_session_id', function () {
    $account = PlatformAccount::factory()->create();
    $session = \App\Models\LiveSession::factory()->create([
        'platform_account_id' => $account->id,
    ]);

    $payload = fn (int $gmv) => [
        'live_stream_sessions' => [[
            'id' => 'live_resync',
            'username' => 'h',
            'start_time' => '1746000000',
            'end_time' => '1746003600',
            'gmv' => ['amount' => (string) $gmv, 'currency' => 'MYR'],
        ]],
        'next_page_token' => null,
    ];

    $fake = new class($payload) {
        public $payload;
        public int $call = 0;
        public function __construct($payload) { $this->payload = $payload; }
        public function getShopLivePerformanceList(array $params): array {
            $this->call++;
            return ($this->payload)($this->call === 1 ? 100 : 200);
        }
    };

    $service = makeLiveSyncService($fake);
    $service->syncLivePerformance($account);

    // Manually tag the row as matched (simulating a successful first match
    // or an admin's manual link)
    \App\Models\TiktokLiveReport::firstWhere('tiktok_live_id', 'live_resync')
        ->update(['matched_live_session_id' => $session->id]);

    $service->syncLivePerformance($account);

    $row = \App\Models\TiktokLiveReport::firstWhere('tiktok_live_id', 'live_resync');
    expect($row->matched_live_session_id)->toBe($session->id)
        ->and((float) $row->gmv_myr)->toBe(200.00);
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`
Expected: the three new tests FAIL (no matching, no ActualLiveRecord write).

**Step 3: Add matching + ActualLiveRecord write to the service**

In `app/Services/TikTok/TikTokLiveSyncService.php`:

a. Inject `\App\Services\LiveHost\Tiktok\LiveSessionMatcher` into the constructor:

```php
public function __construct(
    private TikTokClientFactory $clientFactory,
    private TikTokAuthService $authService,
    private \App\Services\LiveHost\Tiktok\LiveSessionMatcher $matcher,
) {}
```

b. Inside the `foreach ($sessions as $session)` loop, after `$report->save();`, add:

```php
if ($report->matched_live_session_id === null) {
    $matched = $this->matcher->match($report, $account->id);
    if ($matched !== null) {
        $report->matched_live_session_id = $matched->id;
        $report->save();
    }
}

// Mirror the ActualLiveRecord that ProcessTiktokImportJob creates for CSV
\App\Models\ActualLiveRecord::updateOrCreate(
    [
        'platform_account_id' => $account->id,
        'source' => 'api',
        'source_record_id' => $tiktokLiveId,
    ],
    [
        'creator_platform_user_id' => $report->tiktok_creator_id,
        'creator_handle' => $report->creator_nickname,
        'launched_time' => $report->launched_time,
        'duration_seconds' => $report->duration_seconds,
        'gmv_myr' => $report->gmv_myr ?? 0,
        'live_attributed_gmv_myr' => $report->live_attributed_gmv_myr ?? 0,
        'viewers' => $report->viewers,
        'views' => $report->views,
        'comments' => $report->comments,
        'shares' => $report->shares,
        'likes' => $report->likes,
        'new_followers' => $report->new_followers,
        'products_added' => $report->products_added,
        'products_sold' => $report->products_sold,
        'items_sold' => $report->items_sold,
        'sku_orders' => $report->sku_orders,
        'unique_customers' => $report->unique_customers,
        'avg_price_myr' => $report->avg_price_myr,
        'click_to_order_rate' => $report->click_to_order_rate,
        'ctr' => $report->ctr,
        'raw_json' => $report->raw_row_json,
    ],
);
```

c. Update return shape to include `matched` and `unmatched` counts.

d. Update the `makeLiveSyncService` helper in the test to pass a real `LiveSessionMatcher` from the container:

```php
function makeLiveSyncService(object $fakeAnalytics)
{
    $authMock = Mockery::mock(TikTokAuthService::class);
    $factoryMock = Mockery::mock(TikTokClientFactory::class);
    $matcher = app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class);
    // ... rest unchanged, but pass $matcher to the parent constructor
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`
Expected: all 5 tests PASS.

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/TikTok/TikTokLiveSyncService.php tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php
git commit -m "feat(tiktok-live): match LIVE rows to sessions and mirror ActualLiveRecord"
```

---

## Task 6: Handle `not_authorized` / region-not-enabled gracefully

**Files:**
- Modify: `app/Services/TikTok/TikTokLiveSyncService.php`
- Modify: `tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`

**Step 1: Write the failing test**

```php
it('skips cleanly and flags the account when API returns not_authorized', function () {
    $account = PlatformAccount::factory()->create();
    $fake = new class {
        public function getShopLivePerformanceList(array $params): array
        {
            throw new \EcomPHP\TiktokShop\Errors\ResponseException('not_authorized', 105005);
        }
    };
    $service = makeLiveSyncService($fake);

    $result = $service->syncLivePerformance($account);

    $account->refresh();
    expect($result['synced'])->toBe(0)
        ->and($account->metadata['live_api_supported'] ?? null)->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php --filter="skips cleanly"`
Expected: FAIL — exception propagates.

**Step 3: Wrap the paginated loop**

Add to `syncLivePerformance` around the `do { ... } while (...)` block:

```php
try {
    do { /* existing pagination */ } while ($pageToken && $pages < 50);
} catch (\EcomPHP\TiktokShop\Errors\ResponseException $e) {
    if (str_contains($e->getMessage(), 'not_authorized') || str_contains($e->getMessage(), 'permission_denied')) {
        Log::warning('[TikTokLiveSync] LIVE API not enabled for account', [
            'account_id' => $account->id,
            'message' => $e->getMessage(),
        ]);
        $meta = $account->metadata ?? [];
        $meta['live_api_supported'] = false;
        $account->update(['metadata' => $meta]);

        return ['synced' => 0, 'created' => 0, 'updated' => 0, 'matched' => 0, 'unmatched' => 0, 'pages' => 0];
    }
    throw $e;
}
```

Also add a pre-check at the top of `syncLivePerformance`:

```php
if (($account->metadata['live_api_supported'] ?? null) === false) {
    Log::info('[TikTokLiveSync] Skipping account flagged as live_api_supported=false', [
        'account_id' => $account->id,
    ]);
    return ['synced' => 0, 'created' => 0, 'updated' => 0, 'matched' => 0, 'unmatched' => 0, 'pages' => 0];
}
```

**Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php`
Expected: all tests PASS.

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/TikTok/TikTokLiveSyncService.php tests/Feature/Services/TikTok/TikTokLiveSyncServiceTest.php
git commit -m "feat(tiktok-live): degrade cleanly when shop is not authorized for LIVE API"
```

---

## Task 7: Queued job `SyncTikTokLive`

**Files:**
- Create: `app/Jobs/SyncTikTokLive.php`
- Create: `tests/Feature/Jobs/SyncTikTokLiveTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Jobs\SyncTikTokLive;
use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokLiveSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('skips inactive accounts', function () {
    $account = PlatformAccount::factory()->create(['is_active' => false]);

    $serviceMock = Mockery::mock(TikTokLiveSyncService::class);
    $serviceMock->shouldNotReceive('syncLivePerformance');

    (new SyncTikTokLive($account))->handle($serviceMock);
})->throwsNoExceptions();

it('updates last_live_analytics_sync_at on success', function () {
    $account = PlatformAccount::factory()->create(['is_active' => true]);

    $serviceMock = Mockery::mock(TikTokLiveSyncService::class);
    $serviceMock->shouldReceive('syncLivePerformance')
        ->once()
        ->andReturn(['synced' => 1, 'created' => 1, 'updated' => 0, 'matched' => 0, 'unmatched' => 0, 'pages' => 1]);

    (new SyncTikTokLive($account))->handle($serviceMock);

    $account->refresh();
    expect($account->last_live_analytics_sync_at)->not->toBeNull()
        ->and($account->sync_status)->toBe('completed');
});

it('records sync error and rethrows on failure', function () {
    $account = PlatformAccount::factory()->create(['is_active' => true]);

    $serviceMock = Mockery::mock(TikTokLiveSyncService::class);
    $serviceMock->shouldReceive('syncLivePerformance')
        ->andThrow(new \Exception('boom'));

    expect(fn () => (new SyncTikTokLive($account))->handle($serviceMock))
        ->toThrow(\Exception::class, 'boom');
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Jobs/SyncTikTokLiveTest.php`
Expected: FAIL — class not found.

**Step 3: Write the job (mirror `SyncTikTokAnalytics`)**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokLiveSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokLive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $maxExceptions = 2;

    public function __construct(public PlatformAccount $account) {}

    public function handle(TikTokLiveSyncService $service): void
    {
        if (! $this->account->is_active) {
            Log::warning('[SyncTikTokLive] Account not active, skipping', [
                'account_id' => $this->account->id,
            ]);
            return;
        }

        Log::info('[SyncTikTokLive] Starting sync', [
            'account_id' => $this->account->id,
        ]);

        $result = $service->syncLivePerformance($this->account);

        $this->account->updateSyncStatus('completed', 'live_analytics');

        $meta = $this->account->metadata ?? [];
        $meta['last_live_sync_result'] = array_merge($result, [
            'fetched_at' => now()->toIso8601String(),
        ]);
        $this->account->update(['metadata' => $meta]);

        Log::info('[SyncTikTokLive] Sync completed', [
            'account_id' => $this->account->id,
            'result' => $result,
        ]);
    }

    public function failed(?Exception $exception): void
    {
        Log::error('[SyncTikTokLive] Job failed permanently', [
            'account_id' => $this->account->id,
            'error' => $exception?->getMessage(),
        ]);
        $this->account->recordSyncError($exception?->getMessage() ?? 'Unknown error');
    }

    /** @return array<string> */
    public function tags(): array
    {
        return ['tiktok-sync', 'live-analytics', 'account:'.$this->account->id];
    }

    /** @return array<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
```

**Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Jobs/SyncTikTokLiveTest.php`
Expected: 3 PASS.

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Jobs/SyncTikTokLive.php tests/Feature/Jobs/SyncTikTokLiveTest.php
git commit -m "feat(tiktok-live): add SyncTikTokLive queued job"
```

---

## Task 8: Artisan command `tiktok:sync-live`

**Files:**
- Create: `app/Console/Commands/TikTokSyncLive.php`
- Create: `tests/Feature/Console/TikTokSyncLiveCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Jobs\SyncTikTokLive;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches a job per active TikTok Shop account', function () {
    Queue::fake();

    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $a = PlatformAccount::factory()->for($platform)->create(['is_active' => true]);
    $b = PlatformAccount::factory()->for($platform)->create(['is_active' => true]);
    PlatformAccount::factory()->for($platform)->create(['is_active' => false]);

    $this->artisan('tiktok:sync-live')->assertSuccessful();

    Queue::assertPushed(SyncTikTokLive::class, 2);
});

it('dispatches for a single account when --account is given', function () {
    Queue::fake();

    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $a = PlatformAccount::factory()->for($platform)->create(['is_active' => true]);
    PlatformAccount::factory()->for($platform)->create(['is_active' => true]);

    $this->artisan('tiktok:sync-live', ['--account' => $a->id])->assertSuccessful();

    Queue::assertPushed(SyncTikTokLive::class, 1);
});
```

**Step 2: Run test to confirm it fails**

Run: `php artisan test --compact tests/Feature/Console/TikTokSyncLiveCommandTest.php`
Expected: FAIL — command not found.

**Step 3: Write the command (mirror `TikTokSyncAnalytics`)**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokLive;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncLive extends Command
{
    protected $signature = 'tiktok:sync-live
                            {--account= : Specific platform account ID}';

    protected $description = 'Sync TikTok Shop per-LIVE performance data';

    public function handle(): int
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();
        if (! $platform) {
            $this->error('TikTok Shop platform not found.');
            return Command::FAILURE;
        }

        $query = PlatformAccount::where('platform_id', $platform->id)
            ->where('is_active', true);

        if ($id = $this->option('account')) {
            $query->where('id', $id);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->error('No active TikTok Shop accounts found.');
            return Command::FAILURE;
        }

        $this->info("Dispatching LIVE sync for {$accounts->count()} account(s)...");

        foreach ($accounts as $account) {
            SyncTikTokLive::dispatch($account);
            $this->info("  → Dispatched for: {$account->name} (ID: {$account->id})");
        }

        return Command::SUCCESS;
    }
}
```

**Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Console/TikTokSyncLiveCommandTest.php`
Expected: 2 PASS.

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Console/Commands/TikTokSyncLive.php tests/Feature/Console/TikTokSyncLiveCommandTest.php
git commit -m "feat(tiktok-live): add tiktok:sync-live artisan command"
```

---

## Task 9: Schedule the command nightly

**Files:**
- Modify: `routes/console.php`

**Step 1: Read current schedule entries**

Run: `grep -n "tiktok:sync" routes/console.php`
Expected: see entries for `tiktok:sync-orders`, `tiktok:sync-analytics`, `tiktok:sync-affiliates`, `tiktok:sync-finance`.

**Step 2: Add a new schedule entry**

After the existing `tiktok:sync-analytics` entry, add:

```php
Schedule::command('tiktok:sync-live')->dailyAt('04:30');
```

**Step 3: Verify the schedule lists the new job**

Run: `php artisan schedule:list`
Expected: output includes `tiktok:sync-live` running daily at 04:30.

**Step 4: Commit**

```bash
git add routes/console.php
git commit -m "feat(tiktok-live): schedule tiktok:sync-live nightly at 04:30"
```

---

## Task 10: UI — "Sync LIVE Performance" panel on Platform Account → Analytics tab

**Files:**
- Modify: the Volt component that renders the Platform Account analytics tab (`resources/views/livewire/admin/platforms/...` — locate with `grep -rln "Channel Breakdown" resources/views/`).
- Test: `tests/Feature/Livewire/PlatformAccountAnalyticsTabTest.php` (or extend an existing test file for that component if one exists).

**Step 1: Locate the component**

Run: `grep -rln "Channel Breakdown\|channel_breakdown\|TikTok LIVE.*Short Video" resources/views/`
Expected: one Volt file path.

**Step 2: Write a Livewire test**

```php
it('dispatches SyncTikTokLive when the LIVE sync button is clicked', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $admin = \App\Models\User::factory()->admin()->create();
    $account = \App\Models\PlatformAccount::factory()->create();

    \Livewire\Volt\Volt::test('admin.platforms.account-analytics', ['account' => $account])
        ->actingAs($admin)
        ->call('syncLive')
        ->assertHasNoErrors();

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncTikTokLive::class, 1);
});
```

> If the actual component name differs, adjust the `Volt::test()` argument to match.

**Step 3: Run test, expect fail**

Run: `php artisan test --compact tests/Feature/Livewire/PlatformAccountAnalyticsTabTest.php`
Expected: FAIL — `syncLive` method missing on the Volt component.

**Step 4: Add the panel + action to the Volt component**

In the located Volt file:

a. Add a `public function syncLive(): void` method that dispatches the job:

```php
public function syncLive(): void
{
    $this->authorize('viewAdmin', $this->account); // or whatever the existing policy is
    \App\Jobs\SyncTikTokLive::dispatch($this->account);
    $this->dispatch('notify', message: 'LIVE sync queued.');
}
```

b. Add a panel below the existing Channel Breakdown panel:

```blade
<div class="rounded-lg border border-gray-200 bg-white p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <flux:heading size="lg">LIVE Performance</flux:heading>
            <flux:text class="text-sm">Per-LIVE detail synced from TikTok Shop.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="syncLive" wire:loading.attr="disabled">
            <span wire:loading.remove>Sync Now</span>
            <span wire:loading>Syncing…</span>
        </flux:button>
    </div>

    @php($result = $account->metadata['last_live_sync_result'] ?? null)
    @if ($result)
        <div class="grid grid-cols-4 gap-4">
            <div><flux:text class="text-xs uppercase">Synced</flux:text><div class="text-2xl">{{ $result['synced'] ?? 0 }}</div></div>
            <div><flux:text class="text-xs uppercase">Matched</flux:text><div class="text-2xl">{{ $result['matched'] ?? 0 }}</div></div>
            <div><flux:text class="text-xs uppercase">Unmatched</flux:text><div class="text-2xl">{{ $result['unmatched'] ?? 0 }}</div></div>
            <div><flux:text class="text-xs uppercase">Last Sync</flux:text><div class="text-sm">{{ $account->last_live_analytics_sync_at?->diffForHumans() ?? '—' }}</div></div>
        </div>
    @else
        <flux:text>No sync has been run yet. Click "Sync Now" to fetch the last 30 days of LIVE data.</flux:text>
    @endif
</div>
```

**Step 5: Run test**

Run: `php artisan test --compact tests/Feature/Livewire/PlatformAccountAnalyticsTabTest.php`
Expected: PASS.

**Step 6: Visual smoke check**

Run: `composer run dev` (in a separate shell)
Visit: `https://mudeerbedaie.test/admin/platforms/tiktok-shop/accounts/4?tab=analytics`
Expect: see "LIVE Performance" panel below "Channel Breakdown". Clicking "Sync Now" shows a notification and dispatches the job.

**Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add resources/views/livewire/... tests/Feature/Livewire/PlatformAccountAnalyticsTabTest.php
git commit -m "feat(tiktok-live): add 'Sync LIVE Performance' panel on Analytics tab"
```

---

## Task 11: UI — passive notice on TikTok Imports page

**Files:**
- Modify: `resources/js/livehost/pages/tiktok-imports/Index.tsx` (or whatever the existing Inertia page is — confirm with `grep -rln "tiktok-imports" resources/js/livehost/`)

**Step 1: Locate the page**

Run: `grep -rln "TikTok Imports" resources/js/livehost/`
Expected: the Inertia page file.

**Step 2: Add a one-line notice block above the imports table**

```tsx
<div className="mb-4 rounded border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
  LIVE performance data now syncs automatically from TikTok. CSV upload remains available for
  backfilling older periods or accounts where the API is not yet enabled.
</div>
```

**Step 3: Verify the notice renders**

Run: `npm run build` (or `npm run dev` for HMR)
Visit: `https://mudeerbedaie.test/livehost/tiktok-imports`
Expect: see the notice at the top, layout otherwise unchanged.

**Step 4: Commit**

```bash
git add resources/js/livehost/pages/tiktok-imports/Index.tsx
git commit -m "feat(tiktok-live): add passive notice on TikTok Imports page about API auto-sync"
```

---

## Task 12: End-to-end smoke against the real API (one account)

**Files:** none

**Step 1: Confirm env**

Run: `grep TIKTOK .env`
Expected: `TIKTOK_API_VERSION=202405` (from the prior bugfix).

**Step 2: Run the sync against one real account via tinker**

Run:

```bash
php artisan tinker
```

```php
$account = App\Models\PlatformAccount::find(4); // the account from the screenshot
$service = app(App\Services\TikTok\TikTokLiveSyncService::class);
$result = $service->syncLivePerformance($account);
dump($result);
echo App\Models\TiktokLiveReport::where('platform_account_id', $account->id)
    ->where('source', 'api')
    ->count();
```

Expected: `synced > 0` (or `not_authorized` if the API is not yet enabled for this region — in which case the row count stays 0 but `metadata.live_api_supported = false`).

**Step 3: Spot-check a row**

```php
$row = App\Models\TiktokLiveReport::where('source', 'api')->latest('id')->first();
dump($row->only(['tiktok_live_id', 'creator_nickname', 'launched_time', 'gmv_myr', 'matched_live_session_id']));
```

Expected: a real LIVE id, MYR gmv, possibly null `matched_live_session_id` (matcher couldn't bind it).

**Step 4: If `not_authorized`, document and stop**

If the shop isn't authorized for the LIVE API, the implementation still works — it just no-ops on this account. Note in a commit message that production smoke is pending shop authorization.

**Step 5: Optional commit (only if you produced any code changes during smoke)**

No commit required if everything worked as designed.

---

## Task 13: Run the full test suite, then full Pint, then ship

**Step 1: Run all changed-area tests**

Run:

```bash
php artisan test --compact tests/Feature/Services/TikTok tests/Feature/Jobs tests/Feature/Console tests/Feature/Livewire/PlatformAccountAnalyticsTabTest.php tests/Unit/Services/TikTok
```

Expected: all green. Any pre-existing failure (e.g. the `syncs shop performance snapshot` test we already flagged in this branch) is noted but not in scope.

**Step 2: Run Pint on the whole branch**

Run: `vendor/bin/pint --dirty`
Expected: `{"result":"pass"}` or auto-fixes applied.

**Step 3: Commit any Pint fixes**

```bash
git add -u
git diff --staged --quiet || git commit -m "style: pint formatting"
```

**Step 4: Ask the user whether to merge to main**

Use the AskUserQuestion tool to confirm before merging. If approved, fast-forward main and push.

---

## Out of scope reminders (do NOT do)

- Don't change `TikTokAnalyticsSyncService` — the prior bug fix already pinned its versions correctly.
- Don't extract `LiveSessionMatcher` further — it's already a clean service.
- Don't migrate `tiktok_live_reports` to a new table — the design explicitly chose to share the table.
- Don't remove the CSV upload flow — it stays as the fallback.
- Don't add a `tiktok_live_id` column to `actual_live_records` — we use `source_record_id` for that linkage.
- Don't run `migrate:fresh` — only `php artisan migrate` (project rule).
