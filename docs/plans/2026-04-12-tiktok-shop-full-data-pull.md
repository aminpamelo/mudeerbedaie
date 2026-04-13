# TikTok Shop Full Data Pull — Analytics + Affiliate + Finance

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Pull ALL available data from the TikTok Shop API that we currently don't fetch — shop/video/product analytics, affiliate creator data (who promotes our products, their performance, collaboration content), and finance data (statements, commissions, payments) — into the CMS and admin platform.

**Architecture:** Three new sync services mirroring the existing `TikTokOrderSyncService` pattern, each with a queued job, Artisan command, and scheduled task. New database tables for creators, creator performance, and finance records. New React pages in the CMS module for the creator leaderboard. New API endpoints following existing CMS route conventions. Reuse existing `TikTokClientFactory`, `TikTokAuthService`, `PlatformAccount`, and `PlatformApiCredential` infrastructure — zero changes to OAuth flow.

**Tech Stack:**
- Laravel 12 / PHP 8.3
- React 19 SPA (`resources/js/cms/`) with TanStack Query + Tailwind CSS
- `ecomphp/tiktokshop-php` SDK (already installed)
- Existing `PlatformAccount` + `PlatformApiCredential` OAuth infrastructure
- Pest v4 tests

**Depends on:** [2026-04-11-tiktok-shop-content-stats-sync.md](./2026-04-11-tiktok-shop-content-stats-sync.md) (Phases 0-2 must be completed first — API version bump, URL parser, `platform_account_id` on contents, `source`/`raw_response` on content_stats).

---

## Pre-Requisites Checklist

Before starting this plan, confirm:

- [ ] `TIKTOK_API_VERSION=202405` is set in `.env` (required by Analytics + AffiliateSeller, both have `$minimum_version = 202405`)
- [ ] Phase 0 spike from the previous plan is done — you know the actual API response field names
- [ ] `platform_account_id` column exists on `contents` table (Phase 1 of previous plan)
- [ ] `source` + `raw_response` columns exist on `content_stats` table (Phase 1 of previous plan)
- [ ] `TikTokUrlParser` exists and auto-populates `tiktok_post_id` (Phase 2 of previous plan)

---

# MODULE A — ANALYTICS (9 methods)

Pull shop-level, video-level, product-level, and SKU-level performance data.

## Phase A0 — Discovery Spike

### Task A0: Live spike to discover Analytics response shapes

**Goal:** Capture real responses from ALL Analytics endpoints to lock in field mapping.

**Files:**
- Capture output to: `docs/plans/2026-04-12-analytics-spike-output.md`

**Step 1: Run in tinker**

```bash
php artisan tinker
```

```php
$account = \App\Models\PlatformAccount::where('is_active', true)->whereHas('platform', fn($q) => $q->where('slug', 'tiktok-shop'))->first();
$factory = app(\App\Services\TikTok\TikTokClientFactory::class);
$client = $factory->createClientForAccount($account);

// 1. Shop-level performance
dump('=== SHOP PERFORMANCE ===');
dump($client->Analytics->getShopPerformance());

// 2. Video list (all shop videos with stats)
dump('=== VIDEO PERFORMANCE LIST ===');
dump($client->Analytics->getShopVideoPerformanceList([
    'page_size' => 5,
]));

// 3. Single video (use a known video ID)
dump('=== SINGLE VIDEO PERFORMANCE ===');
dump($client->Analytics->getShopVideoPerformance('PASTE_VIDEO_ID'));

// 4. Video overview (aggregate)
dump('=== VIDEO OVERVIEW ===');
dump($client->Analytics->getShopVideoPerformanceOverview());

// 5. Product performance list
dump('=== PRODUCT PERFORMANCE LIST ===');
dump($client->Analytics->getShopProductPerformanceList([
    'page_size' => 5,
]));

// 6. Single product (use a known product ID)
dump('=== SINGLE PRODUCT PERFORMANCE ===');
dump($client->Analytics->getShopProductPerformance('PASTE_PRODUCT_ID'));

// 7. Video → Products (which products tagged in a video)
dump('=== VIDEO PRODUCT PERFORMANCE ===');
dump($client->Analytics->getShopVideoProductPerformanceList('PASTE_VIDEO_ID'));

// 8. SKU list performance
dump('=== SKU PERFORMANCE LIST ===');
dump($client->Analytics->getShopSkuPerformanceList([
    'page_size' => 5,
]));
```

**Step 2: Record ALL response keys** in `docs/plans/2026-04-12-analytics-spike-output.md` — these are used in every subsequent task to map fields.

**Step 3: Commit**

```bash
git add docs/plans/2026-04-12-analytics-spike-output.md
git commit -m "docs: record TikTok Analytics API spike outputs"
```

> **STOP POINT:** If any endpoint returns a scope/permission error, record it and skip that endpoint's implementation. Do NOT proceed with Tasks that depend on failed endpoints.

---

## Phase A1 — Shop Performance Dashboard Data

### Task A1: Migration — `tiktok_shop_performance_snapshots` table

**Files:**
- Create: `database/migrations/<timestamp>_create_tiktok_shop_performance_snapshots_table.php`

**Step 1: Generate**

```bash
php artisan make:migration create_tiktok_shop_performance_snapshots_table --no-interaction
```

**Step 2: Write the migration**

> Replace field names with actual spike output. Below are the most likely fields from TikTok Shop Analytics.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_shop_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();

            // Core metrics (update field names from spike)
            $table->bigInteger('total_orders')->default(0);
            $table->decimal('total_gmv', 15, 2)->default(0);
            $table->bigInteger('total_buyers')->default(0);
            $table->bigInteger('total_video_views')->default(0);
            $table->bigInteger('total_product_impressions')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);

            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['platform_account_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_shop_performance_snapshots');
    }
};
```

**Step 3: Run**

```bash
php artisan migrate
```

**Step 4: Create model**

```bash
php artisan make:class App/Models/TiktokShopPerformanceSnapshot --no-interaction
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokShopPerformanceSnapshot extends Model
{
    protected $fillable = [
        'platform_account_id',
        'total_orders',
        'total_gmv',
        'total_buyers',
        'total_video_views',
        'total_product_impressions',
        'conversion_rate',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'total_gmv' => 'decimal:2',
            'conversion_rate' => 'decimal:4',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }
}
```

**Step 5: Commit**

```bash
git add database/migrations/ app/Models/TiktokShopPerformanceSnapshot.php
git commit -m "feat(tiktok): add tiktok_shop_performance_snapshots table and model"
```

---

### Task A2: Migration — `tiktok_product_performance` table

**Files:**
- Create: `database/migrations/<timestamp>_create_tiktok_product_performance_table.php`
- Create: `app/Models/TiktokProductPerformance.php`

**Step 1: Generate**

```bash
php artisan make:migration create_tiktok_product_performance_table --no-interaction
```

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_product_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->string('tiktok_product_id');

            // Core product metrics (update from spike)
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('clicks')->default(0);
            $table->bigInteger('orders')->default(0);
            $table->decimal('gmv', 15, 2)->default(0);
            $table->bigInteger('buyers')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);

            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['platform_account_id', 'tiktok_product_id'], 'tpp_account_product_idx');
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_product_performance');
    }
};
```

**Step 3: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokProductPerformance extends Model
{
    protected $table = 'tiktok_product_performance';

    protected $fillable = [
        'platform_account_id',
        'tiktok_product_id',
        'impressions',
        'clicks',
        'orders',
        'gmv',
        'buyers',
        'conversion_rate',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'gmv' => 'decimal:2',
            'conversion_rate' => 'decimal:4',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }
}
```

**Step 4: Run + commit**

```bash
php artisan migrate
git add database/migrations/ app/Models/TiktokProductPerformance.php
git commit -m "feat(tiktok): add tiktok_product_performance table and model"
```

---

### Task A3: `TikTokAnalyticsSyncService`

**Files:**
- Create: `app/Services/TikTok/TikTokAnalyticsSyncService.php`
- Test: `tests/Feature/Services/TikTok/TikTokAnalyticsSyncServiceTest.php`

**Step 1: Write the failing test**

```bash
php artisan make:test Services/TikTok/TikTokAnalyticsSyncServiceTest --no-interaction
```

```php
<?php

use App\Models\PlatformAccount;
use App\Models\TiktokShopPerformanceSnapshot;
use App\Models\TiktokProductPerformance;
use App\Services\TikTok\TikTokAnalyticsSyncService;
use App\Services\TikTok\TikTokClientFactory;

use function Pest\Laravel\mock;

it('syncs shop performance snapshot', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAnalytics = new class {
        public function getShopPerformance($params = []) {
            return ['total_orders' => 100, 'total_gmv' => 5000.50, 'total_buyers' => 80];
        }
    };
    $fakeClient = new class($fakeAnalytics) {
        public function __construct(public $Analytics) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')->once()->andReturn($fakeClient);

    $service = app(TikTokAnalyticsSyncService::class);
    $snapshot = $service->syncShopPerformance($account);

    expect($snapshot)->toBeInstanceOf(TiktokShopPerformanceSnapshot::class)
        ->and($snapshot->total_orders)->toBe(100)
        ->and((float) $snapshot->total_gmv)->toBe(5000.50);
});

it('syncs product performance list', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAnalytics = new class {
        public function getShopProductPerformanceList($params = []) {
            return [
                'products' => [
                    ['product_id' => 'P001', 'impressions' => 500, 'clicks' => 50, 'orders' => 5, 'gmv' => 250.00],
                    ['product_id' => 'P002', 'impressions' => 300, 'clicks' => 20, 'orders' => 2, 'gmv' => 80.00],
                ],
            ];
        }
    };
    $fakeClient = new class($fakeAnalytics) {
        public function __construct(public $Analytics) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')->once()->andReturn($fakeClient);

    $service = app(TikTokAnalyticsSyncService::class);
    $count = $service->syncProductPerformance($account);

    expect($count)->toBe(2)
        ->and(TiktokProductPerformance::where('tiktok_product_id', 'P001')->exists())->toBeTrue();
});
```

**Step 2: Run and confirm FAIL**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokAnalyticsSyncServiceTest.php
```

**Step 3: Implement the service**

> Replace field mappings with actual spike output from Task A0.

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\ContentStat;
use App\Models\PlatformAccount;
use App\Models\TiktokProductPerformance;
use App\Models\TiktokShopPerformanceSnapshot;
use Illuminate\Support\Facades\Log;

class TikTokAnalyticsSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService,
    ) {}

    /**
     * Snapshot the overall shop performance.
     */
    public function syncShopPerformance(PlatformAccount $account): TiktokShopPerformanceSnapshot
    {
        $client = $this->getClient($account);

        Log::channel('daily')->info('[TikTokAnalytics] syncing shop performance', ['account_id' => $account->id]);

        $response = $client->Analytics->getShopPerformance();

        return TiktokShopPerformanceSnapshot::create([
            'platform_account_id' => $account->id,
            'total_orders' => (int) ($response['total_orders'] ?? 0),
            'total_gmv' => (float) ($response['total_gmv'] ?? 0),
            'total_buyers' => (int) ($response['total_buyers'] ?? 0),
            'total_video_views' => (int) ($response['total_video_views'] ?? 0),
            'total_product_impressions' => (int) ($response['total_product_impressions'] ?? 0),
            'conversion_rate' => (float) ($response['conversion_rate'] ?? 0),
            'raw_response' => $response,
            'fetched_at' => now(),
        ]);
    }

    /**
     * Sync performance for all shop videos — creates ContentStat rows for linked content.
     */
    public function syncVideoPerformanceList(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        Log::channel('daily')->info('[TikTokAnalytics] syncing video performance list', ['account_id' => $account->id]);

        $response = $client->Analytics->getShopVideoPerformanceList([
            'page_size' => 100,
        ]);

        $videos = $response['videos'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($videos as $video) {
            $videoId = $video['video_id'] ?? $video['id'] ?? null;
            if (! $videoId) {
                continue;
            }

            // Try to find matching Content by tiktok_post_id
            $content = \App\Models\Content::where('tiktok_post_id', $videoId)
                ->where('platform_account_id', $account->id)
                ->first();

            if ($content) {
                ContentStat::create([
                    'content_id' => $content->id,
                    'views' => (int) ($video['video_view_cnt'] ?? $video['views'] ?? 0),
                    'likes' => (int) ($video['like_cnt'] ?? $video['likes'] ?? 0),
                    'comments' => (int) ($video['comment_cnt'] ?? $video['comments'] ?? 0),
                    'shares' => (int) ($video['share_cnt'] ?? $video['shares'] ?? 0),
                    'source' => 'tiktok_api_bulk',
                    'raw_response' => $video,
                    'fetched_at' => now(),
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Sync product-level performance.
     */
    public function syncProductPerformance(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        Log::channel('daily')->info('[TikTokAnalytics] syncing product performance', ['account_id' => $account->id]);

        $response = $client->Analytics->getShopProductPerformanceList([
            'page_size' => 100,
        ]);

        $products = $response['products'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($products as $product) {
            $productId = $product['product_id'] ?? $product['id'] ?? null;
            if (! $productId) {
                continue;
            }

            TiktokProductPerformance::create([
                'platform_account_id' => $account->id,
                'tiktok_product_id' => $productId,
                'impressions' => (int) ($product['impressions'] ?? 0),
                'clicks' => (int) ($product['clicks'] ?? 0),
                'orders' => (int) ($product['orders'] ?? 0),
                'gmv' => (float) ($product['gmv'] ?? 0),
                'buyers' => (int) ($product['buyers'] ?? 0),
                'conversion_rate' => (float) ($product['conversion_rate'] ?? 0),
                'raw_response' => $product,
                'fetched_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Fetch performance overview for all shop videos (aggregate stats).
     */
    public function fetchVideoPerformanceOverview(PlatformAccount $account): array
    {
        $client = $this->getClient($account);

        return $client->Analytics->getShopVideoPerformanceOverview();
    }

    /**
     * Fetch products tagged in a specific video with per-product stats.
     */
    public function fetchVideoProductPerformance(PlatformAccount $account, string $videoId): array
    {
        $client = $this->getClient($account);

        return $client->Analytics->getShopVideoProductPerformanceList($videoId);
    }

    private function getClient(PlatformAccount $account): mixed
    {
        if ($this->authService->needsTokenRefresh($account)) {
            $this->authService->refreshToken($account);
        }

        return $this->clientFactory->createClientForAccount($account);
    }
}
```

**Step 4: Run test — should PASS**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokAnalyticsSyncServiceTest.php
```

**Step 5: Commit**

```bash
git add app/Services/TikTok/TikTokAnalyticsSyncService.php tests/Feature/Services/TikTok/TikTokAnalyticsSyncServiceTest.php
git commit -m "feat(tiktok): add TikTokAnalyticsSyncService for shop/product/video analytics"
```

---

### Task A4: Queued job + Artisan command for Analytics sync

**Files:**
- Create: `app/Jobs/SyncTikTokAnalytics.php`
- Create: `app/Console/Commands/TikTokSyncAnalytics.php`
- Modify: `routes/console.php`

**Step 1: Create the job**

```bash
php artisan make:job SyncTikTokAnalytics --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokAnalyticsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public PlatformAccount $account,
        public string $type = 'all',
    ) {}

    public function handle(TikTokAnalyticsSyncService $service): void
    {
        if (! $this->account->is_active) {
            return;
        }

        Log::info('[SyncTikTokAnalytics] starting', [
            'account_id' => $this->account->id,
            'type' => $this->type,
        ]);

        if (in_array($this->type, ['all', 'shop'])) {
            $service->syncShopPerformance($this->account);
        }
        if (in_array($this->type, ['all', 'videos'])) {
            $service->syncVideoPerformanceList($this->account);
        }
        if (in_array($this->type, ['all', 'products'])) {
            $service->syncProductPerformance($this->account);
        }

        $this->account->updateSyncStatus('completed', 'analytics');
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('[SyncTikTokAnalytics] failed', [
            'account_id' => $this->account->id,
            'error' => $e?->getMessage(),
        ]);
        $this->account->recordSyncError($e?->getMessage() ?? 'Unknown error');
    }

    public function tags(): array
    {
        return ['tiktok-sync', 'analytics', "account:{$this->account->id}"];
    }
}
```

**Step 2: Create the Artisan command**

```bash
php artisan make:command TikTokSyncAnalytics --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokAnalytics;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncAnalytics extends Command
{
    protected $signature = 'tiktok:sync-analytics
        {--account= : Specific platform account ID}
        {--type=all : Sync type: all, shop, videos, products}';

    protected $description = 'Sync TikTok Shop analytics data (shop performance, video stats, product stats)';

    public function handle(): int
    {
        $query = PlatformAccount::query()
            ->where('is_active', true)
            ->whereHas('platform', fn ($q) => $q->where('slug', 'tiktok-shop'));

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();
        $type = $this->option('type');

        foreach ($accounts as $account) {
            SyncTikTokAnalytics::dispatch($account, $type);
            $this->info("Dispatched analytics sync for: {$account->name} (type: {$type})");
        }

        $this->info("Dispatched {$accounts->count()} analytics sync job(s).");

        return self::SUCCESS;
    }
}
```

**Step 3: Add schedule** in `routes/console.php`:

```php
Schedule::command('tiktok:sync-analytics')->dailyAt('04:00');
```

**Step 4: Smoke-test**

```bash
php artisan tiktok:sync-analytics --type=shop
```

**Step 5: Commit**

```bash
git add app/Jobs/SyncTikTokAnalytics.php app/Console/Commands/TikTokSyncAnalytics.php routes/console.php
git commit -m "feat(tiktok): add SyncTikTokAnalytics job and artisan command with daily schedule"
```

---

# MODULE B — AFFILIATE / CREATOR DATA (25 methods)

Pull creator marketplace data, collaboration content, affiliate orders, and creator performance.

## Phase B0 — Discovery Spike

### Task B0: Live spike for AffiliateSeller response shapes

**Files:**
- Capture output to: `docs/plans/2026-04-12-affiliate-spike-output.md`

**Step 1: Run in tinker**

```php
$account = \App\Models\PlatformAccount::where('is_active', true)->whereHas('platform', fn($q) => $q->where('slug', 'tiktok-shop'))->first();
$client = app(\App\Services\TikTok\TikTokClientFactory::class)->createClientForAccount($account);

// 1. Search creators on marketplace
dump('=== MARKETPLACE CREATORS ===');
dump($client->AffiliateSeller->searchCreatorOnMarketplace(['page_size' => 5], []));

// 2. Creator content (who promoted your products)
dump('=== COLLABORATION CREATOR CONTENT ===');
dump($client->AffiliateSeller->getOpenCollaborationCreatorContentDetail(['page_size' => 5]));

// 3. Affiliate orders (sales via affiliates)
dump('=== AFFILIATE ORDERS ===');
dump($client->AffiliateSeller->searchSellerAffiliateOrders(['page_size' => 5]));

// 4. Open collaborations (your campaigns)
dump('=== OPEN COLLABORATIONS ===');
dump($client->AffiliateSeller->searchOpenCollaboration(['page_size' => 5], []));

// 5. Target collaborations (direct partnerships)
dump('=== TARGET COLLABORATIONS ===');
dump($client->AffiliateSeller->searchTargetCollaborations(['page_size' => 5], []));

// 6. Single creator performance (use a creator_user_id from step 1)
// dump('=== CREATOR PERFORMANCE ===');
// dump($client->AffiliateSeller->getMarketplaceCreatorPerformance('PASTE_CREATOR_ID'));

// 7. Collaboration settings
dump('=== COLLABORATION SETTINGS ===');
dump($client->AffiliateSeller->getOpenCollaborationSettings());
```

**Step 2: Record field names** in `docs/plans/2026-04-12-affiliate-spike-output.md`

**Step 3: Commit**

```bash
git add docs/plans/2026-04-12-affiliate-spike-output.md
git commit -m "docs: record TikTok AffiliateSeller API spike outputs"
```

---

## Phase B1 — Schema

### Task B1: Migration — `tiktok_creators` table

**Files:**
- Create: `database/migrations/<timestamp>_create_tiktok_creators_table.php`
- Create: `app/Models/TiktokCreator.php`

**Step 1: Generate**

```bash
php artisan make:migration create_tiktok_creators_table --no-interaction
```

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_creators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->string('creator_user_id')->index();
            $table->string('handle')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->bigInteger('follower_count')->default(0);

            // Performance (cached from getMarketplaceCreatorPerformance)
            $table->decimal('total_gmv', 15, 2)->default(0);
            $table->bigInteger('total_orders')->default(0);
            $table->decimal('total_commission', 15, 2)->default(0);

            $table->json('raw_response')->nullable();
            $table->timestamp('performance_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['platform_account_id', 'creator_user_id'], 'tc_account_creator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_creators');
    }
};
```

**Step 3: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokCreator extends Model
{
    protected $fillable = [
        'platform_account_id',
        'creator_user_id',
        'handle',
        'display_name',
        'avatar_url',
        'country_code',
        'follower_count',
        'total_gmv',
        'total_orders',
        'total_commission',
        'raw_response',
        'performance_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'total_gmv' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'raw_response' => 'array',
            'performance_fetched_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function creatorContents(): HasMany
    {
        return $this->hasMany(TiktokCreatorContent::class);
    }

    public function affiliateOrders(): HasMany
    {
        return $this->hasMany(TiktokAffiliateOrder::class);
    }
}
```

**Step 4: Run + commit**

```bash
php artisan migrate
git add database/migrations/ app/Models/TiktokCreator.php
git commit -m "feat(tiktok): add tiktok_creators table and model"
```

---

### Task B2: Migration — `tiktok_creator_contents` table (pivot: creator promoted which content)

**Files:**
- Create: `database/migrations/<timestamp>_create_tiktok_creator_contents_table.php`
- Create: `app/Models/TiktokCreatorContent.php`

**Step 1: Generate + write migration**

```bash
php artisan make:migration create_tiktok_creator_contents_table --no-interaction
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_creator_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_creator_id')->constrained('tiktok_creators')->cascadeOnDelete();
            $table->foreignId('content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();

            $table->string('creator_video_id')->nullable(); // creator's own video (NOT your content video)
            $table->string('tiktok_product_id')->nullable();

            // Creator video performance for YOUR product
            $table->bigInteger('views')->default(0);
            $table->bigInteger('likes')->default(0);
            $table->bigInteger('comments')->default(0);
            $table->bigInteger('shares')->default(0);
            $table->decimal('gmv', 15, 2)->default(0);
            $table->bigInteger('orders')->default(0);

            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['tiktok_creator_id', 'content_id'], 'tcc_creator_content_idx');
            $table->index('creator_video_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_creator_contents');
    }
};
```

**Step 2: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokCreatorContent extends Model
{
    protected $fillable = [
        'tiktok_creator_id',
        'content_id',
        'platform_account_id',
        'creator_video_id',
        'tiktok_product_id',
        'views',
        'likes',
        'comments',
        'shares',
        'gmv',
        'orders',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'gmv' => 'decimal:2',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TiktokCreator::class, 'tiktok_creator_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }
}
```

**Step 3: Run + commit**

```bash
php artisan migrate
git add database/migrations/ app/Models/TiktokCreatorContent.php
git commit -m "feat(tiktok): add tiktok_creator_contents pivot table and model"
```

---

### Task B3: Migration — `tiktok_affiliate_orders` table

**Files:**
- Create: `database/migrations/<timestamp>_create_tiktok_affiliate_orders_table.php`
- Create: `app/Models/TiktokAffiliateOrder.php`

**Step 1: Generate + write**

```bash
php artisan make:migration create_tiktok_affiliate_orders_table --no-interaction
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_affiliate_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->foreignId('tiktok_creator_id')->nullable()->constrained('tiktok_creators')->nullOnDelete();

            $table->string('tiktok_order_id')->index();
            $table->string('creator_user_id')->nullable();
            $table->string('tiktok_product_id')->nullable();
            $table->string('order_status')->nullable();

            $table->decimal('order_amount', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('commission_rate', 8, 4)->default(0);

            $table->string('collaboration_type')->nullable(); // open, target
            $table->timestamp('order_created_at')->nullable();

            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['platform_account_id', 'tiktok_order_id'], 'tao_account_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_affiliate_orders');
    }
};
```

**Step 2: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokAffiliateOrder extends Model
{
    protected $fillable = [
        'platform_account_id',
        'tiktok_creator_id',
        'tiktok_order_id',
        'creator_user_id',
        'tiktok_product_id',
        'order_status',
        'order_amount',
        'commission_amount',
        'commission_rate',
        'collaboration_type',
        'order_created_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'order_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'raw_response' => 'array',
            'order_created_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TiktokCreator::class, 'tiktok_creator_id');
    }
}
```

**Step 3: Run + commit**

```bash
php artisan migrate
git add database/migrations/ app/Models/TiktokAffiliateOrder.php
git commit -m "feat(tiktok): add tiktok_affiliate_orders table and model"
```

---

## Phase B2 — Sync Service

### Task B4: `TikTokAffiliateSyncService`

**Files:**
- Create: `app/Services/TikTok/TikTokAffiliateSyncService.php`
- Test: `tests/Feature/Services/TikTok/TikTokAffiliateSyncServiceTest.php`

**Step 1: Write the failing test**

```bash
php artisan make:test Services/TikTok/TikTokAffiliateSyncServiceTest --no-interaction
```

```php
<?php

use App\Models\PlatformAccount;
use App\Models\TiktokCreator;
use App\Models\TiktokAffiliateOrder;
use App\Models\TiktokCreatorContent;
use App\Services\TikTok\TikTokAffiliateSyncService;
use App\Services\TikTok\TikTokClientFactory;

use function Pest\Laravel\mock;

it('syncs creators from marketplace', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAffiliateSeller = new class {
        public function searchCreatorOnMarketplace($query = [], $body = []) {
            return [
                'creators' => [
                    [
                        'creator_user_id' => 'CR001',
                        'handle' => '@creator1',
                        'display_name' => 'Creator One',
                        'follower_count' => 50000,
                        'country_code' => 'MY',
                    ],
                ],
            ];
        }
    };
    $fakeClient = new class($fakeAffiliateSeller) {
        public function __construct(public $AffiliateSeller) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')->once()->andReturn($fakeClient);

    $count = app(TikTokAffiliateSyncService::class)->syncCreators($account);

    expect($count)->toBe(1)
        ->and(TiktokCreator::where('creator_user_id', 'CR001')->exists())->toBeTrue();
});

it('syncs affiliate orders', function () {
    $account = PlatformAccount::factory()->create();

    $fakeAffiliateSeller = new class {
        public function searchSellerAffiliateOrders($query = []) {
            return [
                'orders' => [
                    [
                        'order_id' => 'ORD001',
                        'creator_user_id' => 'CR001',
                        'product_id' => 'P001',
                        'order_status' => 'COMPLETED',
                        'order_amount' => 100.00,
                        'commission_amount' => 10.00,
                        'commission_rate' => 0.10,
                    ],
                ],
            ];
        }
    };
    $fakeClient = new class($fakeAffiliateSeller) {
        public function __construct(public $AffiliateSeller) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')->once()->andReturn($fakeClient);

    $count = app(TikTokAffiliateSyncService::class)->syncAffiliateOrders($account);

    expect($count)->toBe(1)
        ->and(TiktokAffiliateOrder::where('tiktok_order_id', 'ORD001')->exists())->toBeTrue();
});

it('syncs creator collaboration content', function () {
    $account = PlatformAccount::factory()->create();
    $creator = TiktokCreator::factory()->create([
        'platform_account_id' => $account->id,
        'creator_user_id' => 'CR001',
    ]);

    $fakeAffiliateSeller = new class {
        public function getOpenCollaborationCreatorContentDetail($query = []) {
            return [
                'contents' => [
                    [
                        'creator_user_id' => 'CR001',
                        'video_id' => 'VID999',
                        'product_id' => 'P001',
                        'views' => 8000,
                        'likes' => 300,
                        'comments' => 20,
                        'shares' => 5,
                        'gmv' => 500.00,
                        'orders' => 8,
                    ],
                ],
            ];
        }
    };
    $fakeClient = new class($fakeAffiliateSeller) {
        public function __construct(public $AffiliateSeller) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')->once()->andReturn($fakeClient);

    $count = app(TikTokAffiliateSyncService::class)->syncCreatorContent($account);

    expect($count)->toBe(1)
        ->and(TiktokCreatorContent::where('creator_video_id', 'VID999')->exists())->toBeTrue();
});
```

**Step 2: Run and confirm FAIL**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokAffiliateSyncServiceTest.php
```

**Step 3: Implement the service**

> Replace field mappings with spike output from Task B0.

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PlatformAccount;
use App\Models\TiktokAffiliateOrder;
use App\Models\TiktokCreator;
use App\Models\TiktokCreatorContent;
use Illuminate\Support\Facades\Log;

class TikTokAffiliateSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService,
    ) {}

    /**
     * Sync creators from TikTok marketplace into tiktok_creators table.
     */
    public function syncCreators(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        Log::channel('daily')->info('[TikTokAffiliate] syncing creators', ['account_id' => $account->id]);

        $response = $client->AffiliateSeller->searchCreatorOnMarketplace(['page_size' => 100], []);
        $creators = $response['creators'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($creators as $creator) {
            $creatorId = $creator['creator_user_id'] ?? null;
            if (! $creatorId) {
                continue;
            }

            TiktokCreator::updateOrCreate(
                [
                    'platform_account_id' => $account->id,
                    'creator_user_id' => $creatorId,
                ],
                [
                    'handle' => $creator['handle'] ?? $creator['unique_id'] ?? null,
                    'display_name' => $creator['display_name'] ?? $creator['nickname'] ?? null,
                    'avatar_url' => $creator['avatar_url'] ?? $creator['avatar'] ?? null,
                    'country_code' => $creator['country_code'] ?? null,
                    'follower_count' => (int) ($creator['follower_count'] ?? 0),
                    'raw_response' => $creator,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Fetch and update performance for a single creator.
     */
    public function syncCreatorPerformance(PlatformAccount $account, TiktokCreator $creator): TiktokCreator
    {
        $client = $this->getClient($account);

        $response = $client->AffiliateSeller->getMarketplaceCreatorPerformance($creator->creator_user_id);

        $creator->update([
            'total_gmv' => (float) ($response['total_gmv'] ?? $response['gmv'] ?? 0),
            'total_orders' => (int) ($response['total_orders'] ?? $response['order_count'] ?? 0),
            'total_commission' => (float) ($response['total_commission'] ?? $response['commission'] ?? 0),
            'follower_count' => (int) ($response['follower_count'] ?? $creator->follower_count),
            'performance_fetched_at' => now(),
            'raw_response' => $response,
        ]);

        return $creator;
    }

    /**
     * Sync all creators' performance in bulk.
     */
    public function syncAllCreatorPerformance(PlatformAccount $account): int
    {
        $creators = TiktokCreator::where('platform_account_id', $account->id)->get();
        $count = 0;

        foreach ($creators as $creator) {
            try {
                $this->syncCreatorPerformance($account, $creator);
                $count++;
            } catch (\Throwable $e) {
                Log::channel('daily')->warning('[TikTokAffiliate] creator performance failed', [
                    'creator_user_id' => $creator->creator_user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Sync affiliate orders — sales driven by creators.
     */
    public function syncAffiliateOrders(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        Log::channel('daily')->info('[TikTokAffiliate] syncing affiliate orders', ['account_id' => $account->id]);

        $response = $client->AffiliateSeller->searchSellerAffiliateOrders(['page_size' => 100]);
        $orders = $response['orders'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($orders as $order) {
            $orderId = $order['order_id'] ?? $order['id'] ?? null;
            if (! $orderId) {
                continue;
            }

            // Try to link to a known creator
            $creatorUserId = $order['creator_user_id'] ?? null;
            $creator = $creatorUserId
                ? TiktokCreator::where('platform_account_id', $account->id)
                    ->where('creator_user_id', $creatorUserId)
                    ->first()
                : null;

            TiktokAffiliateOrder::updateOrCreate(
                [
                    'platform_account_id' => $account->id,
                    'tiktok_order_id' => $orderId,
                ],
                [
                    'tiktok_creator_id' => $creator?->id,
                    'creator_user_id' => $creatorUserId,
                    'tiktok_product_id' => $order['product_id'] ?? null,
                    'order_status' => $order['order_status'] ?? $order['status'] ?? null,
                    'order_amount' => (float) ($order['order_amount'] ?? $order['total_amount'] ?? 0),
                    'commission_amount' => (float) ($order['commission_amount'] ?? $order['commission'] ?? 0),
                    'commission_rate' => (float) ($order['commission_rate'] ?? 0),
                    'collaboration_type' => $order['collaboration_type'] ?? null,
                    'order_created_at' => isset($order['create_time'])
                        ? \Carbon\Carbon::createFromTimestamp($order['create_time'])
                        : null,
                    'raw_response' => $order,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync creator-generated content for your products/collaborations.
     */
    public function syncCreatorContent(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        Log::channel('daily')->info('[TikTokAffiliate] syncing creator content', ['account_id' => $account->id]);

        $response = $client->AffiliateSeller->getOpenCollaborationCreatorContentDetail(['page_size' => 100]);
        $contents = $response['contents'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($contents as $item) {
            $creatorUserId = $item['creator_user_id'] ?? null;
            if (! $creatorUserId) {
                continue;
            }

            $creator = TiktokCreator::where('platform_account_id', $account->id)
                ->where('creator_user_id', $creatorUserId)
                ->first();

            if (! $creator) {
                // Auto-create creator stub if not yet synced
                $creator = TiktokCreator::create([
                    'platform_account_id' => $account->id,
                    'creator_user_id' => $creatorUserId,
                    'handle' => $item['creator_handle'] ?? null,
                    'display_name' => $item['creator_name'] ?? null,
                ]);
            }

            TiktokCreatorContent::create([
                'tiktok_creator_id' => $creator->id,
                'platform_account_id' => $account->id,
                'content_id' => null, // linked later if we can match product to content
                'creator_video_id' => $item['video_id'] ?? null,
                'tiktok_product_id' => $item['product_id'] ?? null,
                'views' => (int) ($item['views'] ?? $item['video_view_cnt'] ?? 0),
                'likes' => (int) ($item['likes'] ?? $item['like_cnt'] ?? 0),
                'comments' => (int) ($item['comments'] ?? $item['comment_cnt'] ?? 0),
                'shares' => (int) ($item['shares'] ?? $item['share_cnt'] ?? 0),
                'gmv' => (float) ($item['gmv'] ?? 0),
                'orders' => (int) ($item['orders'] ?? $item['order_count'] ?? 0),
                'raw_response' => $item,
                'fetched_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function getClient(PlatformAccount $account): mixed
    {
        if ($this->authService->needsTokenRefresh($account)) {
            $this->authService->refreshToken($account);
        }

        return $this->clientFactory->createClientForAccount($account);
    }
}
```

**Step 4: Run tests — should PASS**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokAffiliateSyncServiceTest.php
```

**Step 5: Commit**

```bash
git add app/Services/TikTok/TikTokAffiliateSyncService.php tests/Feature/Services/TikTok/TikTokAffiliateSyncServiceTest.php
git commit -m "feat(tiktok): add TikTokAffiliateSyncService for creators, orders, collaboration content"
```

---

### Task B5: Queued job + Artisan command for Affiliate sync

**Files:**
- Create: `app/Jobs/SyncTikTokAffiliates.php`
- Create: `app/Console/Commands/TikTokSyncAffiliates.php`
- Modify: `routes/console.php`

**Step 1: Job**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokAffiliateSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokAffiliates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public PlatformAccount $account) {}

    public function handle(TikTokAffiliateSyncService $service): void
    {
        if (! $this->account->is_active) {
            return;
        }

        Log::info('[SyncTikTokAffiliates] starting', ['account_id' => $this->account->id]);

        $service->syncCreators($this->account);
        $service->syncAllCreatorPerformance($this->account);
        $service->syncAffiliateOrders($this->account);
        $service->syncCreatorContent($this->account);

        $this->account->updateSyncStatus('completed', 'affiliate');
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('[SyncTikTokAffiliates] failed', [
            'account_id' => $this->account->id,
            'error' => $e?->getMessage(),
        ]);
        $this->account->recordSyncError($e?->getMessage() ?? 'Unknown error');
    }

    public function tags(): array
    {
        return ['tiktok-sync', 'affiliates', "account:{$this->account->id}"];
    }
}
```

**Step 2: Artisan command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokAffiliates;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncAffiliates extends Command
{
    protected $signature = 'tiktok:sync-affiliates {--account= : Specific platform account ID}';
    protected $description = 'Sync TikTok affiliate creators, orders, and collaboration content';

    public function handle(): int
    {
        $query = PlatformAccount::query()
            ->where('is_active', true)
            ->whereHas('platform', fn ($q) => $q->where('slug', 'tiktok-shop'));

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        foreach ($accounts as $account) {
            SyncTikTokAffiliates::dispatch($account);
            $this->info("Dispatched affiliate sync for: {$account->name}");
        }

        $this->info("Dispatched {$accounts->count()} affiliate sync job(s).");

        return self::SUCCESS;
    }
}
```

**Step 3: Schedule** in `routes/console.php`:

```php
Schedule::command('tiktok:sync-affiliates')->dailyAt('05:00');
```

**Step 4: Commit**

```bash
git add app/Jobs/SyncTikTokAffiliates.php app/Console/Commands/TikTokSyncAffiliates.php routes/console.php
git commit -m "feat(tiktok): add SyncTikTokAffiliates job and artisan command"
```

---

### Task B6: Add `last_affiliate_sync_at` to `platform_accounts`

**Files:**
- Create: `database/migrations/<timestamp>_add_last_affiliate_sync_at_to_platform_accounts_table.php`
- Modify: `app/Models/PlatformAccount.php` (add to `$fillable` and `casts()`)

**Step 1: Generate + write migration**

```bash
php artisan make:migration add_last_affiliate_sync_at_to_platform_accounts_table --table=platform_accounts --no-interaction
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->timestamp('last_affiliate_sync_at')->nullable()->after('last_inventory_sync_at');
            $table->timestamp('last_analytics_sync_at')->nullable()->after('last_affiliate_sync_at');
            $table->timestamp('last_finance_sync_at')->nullable()->after('last_analytics_sync_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->dropColumn(['last_affiliate_sync_at', 'last_analytics_sync_at', 'last_finance_sync_at']);
        });
    }
};
```

**Step 2: Update `PlatformAccount.php`** — add to `$fillable`:

```php
'last_affiliate_sync_at',
'last_analytics_sync_at',
'last_finance_sync_at',
```

Add to `casts()`:

```php
'last_affiliate_sync_at' => 'datetime',
'last_analytics_sync_at' => 'datetime',
'last_finance_sync_at' => 'datetime',
```

**Step 3: Run + commit**

```bash
php artisan migrate
git add database/migrations/ app/Models/PlatformAccount.php
git commit -m "feat(tiktok): add sync timestamp columns to platform_accounts for analytics/affiliate/finance"
```

---

## Phase B3 — API + UI (Creator Leaderboard + Per-Content Creator List)

### Task B7: API endpoints for affiliate data

**Files:**
- Create: `app/Http/Controllers/Api/Cms/CmsAffiliateController.php`
- Modify: `routes/api.php`

**Step 1: Create controller**

```bash
php artisan make:controller Api/Cms/CmsAffiliateController --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\TiktokAffiliateOrder;
use App\Models\TiktokCreator;
use App\Models\TiktokCreatorContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsAffiliateController extends Controller
{
    /**
     * Creator leaderboard — all creators ranked by GMV.
     */
    public function creators(Request $request): JsonResponse
    {
        $query = TiktokCreator::query()
            ->with('platformAccount:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('display_name', 'like', "%{$s}%")
                ->orWhere('handle', 'like', "%{$s}%"))
            ->when($request->sort === 'followers', fn ($q) => $q->orderByDesc('follower_count'),
                fn ($q) => $q->orderByDesc('total_gmv'))
            ->paginate($request->integer('per_page', 20));

        return response()->json($query);
    }

    /**
     * Single creator detail with recent content and orders.
     */
    public function creatorDetail(TiktokCreator $creator): JsonResponse
    {
        $creator->load([
            'creatorContents' => fn ($q) => $q->latest('fetched_at')->limit(20),
            'affiliateOrders' => fn ($q) => $q->latest('order_created_at')->limit(20),
        ]);

        return response()->json(['data' => $creator]);
    }

    /**
     * Creators who promoted a specific content item.
     */
    public function contentCreators(int $contentId): JsonResponse
    {
        $creators = TiktokCreatorContent::where('content_id', $contentId)
            ->with('creator:id,creator_user_id,handle,display_name,avatar_url,follower_count')
            ->latest('fetched_at')
            ->get();

        return response()->json(['data' => $creators]);
    }

    /**
     * Affiliate orders summary — recent orders with creator info.
     */
    public function affiliateOrders(Request $request): JsonResponse
    {
        $query = TiktokAffiliateOrder::query()
            ->with('creator:id,display_name,handle,avatar_url')
            ->when($request->creator_id, fn ($q, $id) => $q->where('tiktok_creator_id', $id))
            ->latest('order_created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($query);
    }
}
```

**Step 2: Add routes** in `routes/api.php` inside the CMS middleware group (after line 1144):

```php
// Affiliate / Creators
Route::get('affiliates/creators', [CmsAffiliateController::class, 'creators']);
Route::get('affiliates/creators/{creator}', [CmsAffiliateController::class, 'creatorDetail']);
Route::get('affiliates/orders', [CmsAffiliateController::class, 'affiliateOrders']);
Route::get('contents/{content}/creators', [CmsAffiliateController::class, 'contentCreators']);
```

Add the import at the top of the routes file:

```php
use App\Http\Controllers\Api\Cms\CmsAffiliateController;
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Cms/CmsAffiliateController.php routes/api.php
git commit -m "feat(cms): add affiliate API endpoints for creators, orders, and content creators"
```

---

### Task B8: React API client functions for affiliate data

**Files:**
- Modify: `resources/js/cms/lib/api.js`

**Step 1: Add after the `fetchPerformanceReport` block** (line 106):

```js
// ─── Affiliates / Creators ─────────────────────────────────────────────────

export function fetchCreators(params) {
    return api.get('/affiliates/creators', { params }).then((r) => r.data);
}

export function fetchCreatorDetail(id) {
    return api.get(`/affiliates/creators/${id}`).then((r) => r.data);
}

export function fetchAffiliateOrders(params) {
    return api.get('/affiliates/orders', { params }).then((r) => r.data);
}

export function fetchContentCreators(contentId) {
    return api.get(`/contents/${contentId}/creators`).then((r) => r.data);
}
```

**Step 2: Commit**

```bash
git add resources/js/cms/lib/api.js
git commit -m "feat(cms): add affiliate API client functions"
```

---

### Task B9: Creator Leaderboard React page

**Files:**
- Create: `resources/js/cms/pages/CreatorLeaderboard.jsx`
- Modify: `resources/js/cms/App.jsx` (add route)

**Step 1: Create the page**

Create `resources/js/cms/pages/CreatorLeaderboard.jsx`:

```jsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Trophy, Users, TrendingUp, Search, ExternalLink } from 'lucide-react';
import { fetchCreators } from '../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';

export default function CreatorLeaderboard() {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState('gmv');
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['creators', { search, sort, page }],
        queryFn: () => fetchCreators({ search, sort, page, per_page: 20 }),
    });

    const creators = data?.data || [];
    const pagination = data?.meta || data || {};

    const formatCurrency = (v) =>
        new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(v || 0);
    const formatNumber = (v) =>
        new Intl.NumberFormat('en-MY', { notation: 'compact' }).format(v || 0);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Creator Leaderboard</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        TikTok Shop affiliate creators ranked by performance
                    </p>
                </div>
            </div>

            {/* Filters */}
            <div className="flex items-center gap-3">
                <div className="relative flex-1 max-w-sm">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <Input
                        placeholder="Search creators..."
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        className="pl-9"
                    />
                </div>
                <div className="flex gap-1">
                    <Button
                        size="sm"
                        variant={sort === 'gmv' ? 'default' : 'outline'}
                        onClick={() => setSort('gmv')}
                    >
                        <TrendingUp className="mr-1 h-3.5 w-3.5" /> By GMV
                    </Button>
                    <Button
                        size="sm"
                        variant={sort === 'followers' ? 'default' : 'outline'}
                        onClick={() => setSort('followers')}
                    >
                        <Users className="mr-1 h-3.5 w-3.5" /> By Followers
                    </Button>
                </div>
            </div>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 w-12">#</th>
                                <th className="px-4 py-3">Creator</th>
                                <th className="px-4 py-3 text-right">Followers</th>
                                <th className="px-4 py-3 text-right">GMV</th>
                                <th className="px-4 py-3 text-right">Orders</th>
                                <th className="px-4 py-3 text-right">Commission</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {isLoading ? (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">Loading...</td></tr>
                            ) : creators.length === 0 ? (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">No creators found</td></tr>
                            ) : (
                                creators.map((c, idx) => (
                                    <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-4 py-3 text-slate-400 font-mono">
                                            {idx === 0 && page === 1 ? (
                                                <Trophy className="h-4 w-4 text-amber-500" />
                                            ) : (
                                                (page - 1) * 20 + idx + 1
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {c.avatar_url ? (
                                                    <img src={c.avatar_url} className="h-8 w-8 rounded-full object-cover" alt="" />
                                                ) : (
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600">
                                                        {(c.display_name || '?')[0]}
                                                    </div>
                                                )}
                                                <div>
                                                    <p className="font-medium text-slate-800">{c.display_name || 'Unknown'}</p>
                                                    {c.handle && (
                                                        <p className="text-xs text-slate-400">{c.handle}</p>
                                                    )}
                                                </div>
                                                {c.country_code && (
                                                    <Badge variant="outline" className="ml-1 text-[10px]">{c.country_code}</Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-slate-600">{formatNumber(c.follower_count)}</td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold text-emerald-600">{formatCurrency(c.total_gmv)}</td>
                                        <td className="px-4 py-3 text-right font-mono text-slate-600">{formatNumber(c.total_orders)}</td>
                                        <td className="px-4 py-3 text-right font-mono text-amber-600">{formatCurrency(c.total_commission)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            {/* Pagination */}
            {pagination.last_page > 1 && (
                <div className="flex justify-center gap-2">
                    <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
                    <span className="flex items-center text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <Button size="sm" variant="outline" disabled={page >= pagination.last_page} onClick={() => setPage(page + 1)}>Next</Button>
                </div>
            )}
        </div>
    );
}
```

**Step 2: Add route** in `resources/js/cms/App.jsx` — add import and route:

Import at top:
```jsx
import CreatorLeaderboard from './pages/CreatorLeaderboard';
```

Inside `<Routes>`, after the performance report route:
```jsx
<Route path="creators" element={<CreatorLeaderboard />} />
```

**Step 3: Add nav item** — find the sidebar/nav component in `resources/js/cms/layouts/CmsLayout.jsx` and add a "Creators" link pointing to `/cms/creators`. Follow the existing pattern for navigation items.

**Step 4: Build + smoke-test**

```bash
npm run build
```

Navigate to `/cms/creators` in the browser.

**Step 5: Commit**

```bash
git add resources/js/cms/pages/CreatorLeaderboard.jsx resources/js/cms/App.jsx resources/js/cms/layouts/CmsLayout.jsx
git commit -m "feat(cms): add Creator Leaderboard page with search, sorting, and pagination"
```

---

### Task B10: "Promoted by" section on ContentDetail page

**Files:**
- Modify: `resources/js/cms/pages/ContentDetail.jsx`

**Step 1: Import the API function** at the top:

```jsx
import { fetchContentCreators } from '../lib/api';
```

Import `Users` icon (already imported — verify).

**Step 2: Add a query** inside the component:

```jsx
const { data: contentCreators = [] } = useQuery({
    queryKey: ['content-creators', id],
    queryFn: () => fetchContentCreators(id).then((r) => r.data),
    enabled: currentStage === 'posted',
});
```

**Step 3: Add the "Promoted by" section** below the TikTok Stats section (after line 699):

```jsx
{/* Creator Promotions */}
{currentStage === 'posted' && contentCreators.length > 0 && (
    <Card className="mt-4">
        <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base">
                <Users className="h-4 w-4 text-purple-500" />
                Promoted by {contentCreators.length} Creator{contentCreators.length !== 1 && 's'}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div className="space-y-3">
                {contentCreators.map((cc) => (
                    <div key={cc.id} className="flex items-center justify-between rounded-lg border border-slate-100 p-3">
                        <div className="flex items-center gap-3">
                            {cc.creator?.avatar_url ? (
                                <img src={cc.creator.avatar_url} className="h-8 w-8 rounded-full" alt="" />
                            ) : (
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-xs font-bold text-purple-600">
                                    {(cc.creator?.display_name || '?')[0]}
                                </div>
                            )}
                            <div>
                                <p className="text-sm font-medium">{cc.creator?.display_name}</p>
                                <p className="text-xs text-slate-400">{cc.creator?.handle}</p>
                            </div>
                        </div>
                        <div className="flex gap-4 text-xs text-slate-500">
                            <span>{(cc.views || 0).toLocaleString()} views</span>
                            <span>{(cc.orders || 0).toLocaleString()} orders</span>
                            <span className="font-medium text-emerald-600">
                                RM {parseFloat(cc.gmv || 0).toFixed(2)}
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </CardContent>
    </Card>
)}
```

**Step 4: Build + test**

```bash
npm run build
```

**Step 5: Commit**

```bash
git add resources/js/cms/pages/ContentDetail.jsx
git commit -m "feat(cms): add 'Promoted by' creator section on content detail page"
```

---

# MODULE C — FINANCE (6 methods)

Pull statements, transaction details, payments, and withdrawals.

## Phase C0 — Discovery Spike

### Task C0: Live spike for Finance response shapes

**Files:**
- Capture output to: `docs/plans/2026-04-12-finance-spike-output.md`

**Step 1: Run in tinker**

```php
$account = \App\Models\PlatformAccount::where('is_active', true)->whereHas('platform', fn($q) => $q->where('slug', 'tiktok-shop'))->first();
$client = app(\App\Services\TikTok\TikTokClientFactory::class)->createClientForAccount($account);

// 1. Statements
dump('=== STATEMENTS ===');
dump($client->Finance->getStatements(['page_size' => 5]));

// 2. Payments
dump('=== PAYMENTS ===');
dump($client->Finance->getPayments(['page_size' => 5]));

// 3. Withdrawals
dump('=== WITHDRAWALS ===');
dump($client->Finance->getWithdrawals(['page_size' => 5]));

// 4. Statement transactions (use a statement_id from step 1)
// dump('=== STATEMENT TRANSACTIONS ===');
// dump($client->Finance->getStatementTransactions('PASTE_STATEMENT_ID', ['page_size' => 5]));

// 5. Order transactions (use a known tiktok order_id)
// dump('=== ORDER TRANSACTIONS ===');
// dump($client->Finance->getOrderStatementTransactions('PASTE_ORDER_ID'));
```

**Step 2: Record fields + commit**

```bash
git add docs/plans/2026-04-12-finance-spike-output.md
git commit -m "docs: record TikTok Finance API spike outputs"
```

---

## Phase C1 — Schema

### Task C1: Migration — `tiktok_finance_statements` + `tiktok_finance_transactions` tables

**Files:**
- Create: `database/migrations/<timestamp>_create_tiktok_finance_tables.php`
- Create: `app/Models/TiktokFinanceStatement.php`
- Create: `app/Models/TiktokFinanceTransaction.php`

**Step 1: Generate**

```bash
php artisan make:migration create_tiktok_finance_tables --no-interaction
```

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_finance_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->string('tiktok_statement_id')->index();

            $table->string('statement_type')->nullable(); // SETTLE, WITHDRAW, etc.
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('order_amount', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('status')->nullable();

            $table->timestamp('statement_time')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['platform_account_id', 'tiktok_statement_id'], 'tfs_account_statement_unique');
        });

        Schema::create('tiktok_finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->foreignId('statement_id')->nullable()->constrained('tiktok_finance_statements')->nullOnDelete();

            $table->string('tiktok_order_id')->nullable()->index();
            $table->string('transaction_type')->nullable();

            $table->decimal('order_amount', 15, 2)->default(0);
            $table->decimal('seller_revenue', 15, 2)->default(0);
            $table->decimal('affiliate_commission', 15, 2)->default(0);
            $table->decimal('platform_commission', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);

            $table->timestamp('order_created_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['platform_account_id', 'tiktok_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_finance_transactions');
        Schema::dropIfExists('tiktok_finance_statements');
    }
};
```

**Step 3: Create models**

`app/Models/TiktokFinanceStatement.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokFinanceStatement extends Model
{
    protected $fillable = [
        'platform_account_id', 'tiktok_statement_id', 'statement_type',
        'total_amount', 'order_amount', 'commission_amount',
        'shipping_fee', 'platform_fee', 'currency', 'status',
        'statement_time', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'order_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'raw_response' => 'array',
            'statement_time' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TiktokFinanceTransaction::class, 'statement_id');
    }
}
```

`app/Models/TiktokFinanceTransaction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokFinanceTransaction extends Model
{
    protected $fillable = [
        'platform_account_id', 'statement_id', 'tiktok_order_id',
        'transaction_type', 'order_amount', 'seller_revenue',
        'affiliate_commission', 'platform_commission', 'shipping_fee',
        'order_created_at', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'order_amount' => 'decimal:2',
            'seller_revenue' => 'decimal:2',
            'affiliate_commission' => 'decimal:2',
            'platform_commission' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'raw_response' => 'array',
            'order_created_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TiktokFinanceStatement::class, 'statement_id');
    }
}
```

**Step 4: Run + commit**

```bash
php artisan migrate
git add database/migrations/ app/Models/TiktokFinanceStatement.php app/Models/TiktokFinanceTransaction.php
git commit -m "feat(tiktok): add finance statements and transactions tables"
```

---

## Phase C2 — Sync Service

### Task C2: `TikTokFinanceSyncService`

**Files:**
- Create: `app/Services/TikTok/TikTokFinanceSyncService.php`
- Test: `tests/Feature/Services/TikTok/TikTokFinanceSyncServiceTest.php`

**Step 1: Write the failing test**

```bash
php artisan make:test Services/TikTok/TikTokFinanceSyncServiceTest --no-interaction
```

```php
<?php

use App\Models\PlatformAccount;
use App\Models\TiktokFinanceStatement;
use App\Services\TikTok\TikTokFinanceSyncService;
use App\Services\TikTok\TikTokClientFactory;

use function Pest\Laravel\mock;

it('syncs finance statements', function () {
    $account = PlatformAccount::factory()->create();

    $fakeFinance = new class {
        public function getStatements($params = []) {
            return [
                'statements' => [
                    [
                        'statement_id' => 'STM001',
                        'type' => 'SETTLE',
                        'total_amount' => 1500.00,
                        'order_amount' => 1800.00,
                        'commission_amount' => 180.00,
                        'shipping_fee' => 50.00,
                        'platform_fee' => 70.00,
                        'currency' => 'MYR',
                        'status' => 'COMPLETED',
                    ],
                ],
            ];
        }
    };
    $fakeClient = new class($fakeFinance) {
        public function __construct(public $Finance) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')->once()->andReturn($fakeClient);

    $count = app(TikTokFinanceSyncService::class)->syncStatements($account);

    expect($count)->toBe(1)
        ->and(TiktokFinanceStatement::where('tiktok_statement_id', 'STM001')->exists())->toBeTrue();
});
```

**Step 2: Run — confirm FAIL**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokFinanceSyncServiceTest.php
```

**Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PlatformAccount;
use App\Models\TiktokFinanceStatement;
use App\Models\TiktokFinanceTransaction;
use Illuminate\Support\Facades\Log;

class TikTokFinanceSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService,
    ) {}

    /**
     * Sync settlement statements.
     */
    public function syncStatements(PlatformAccount $account): int
    {
        $client = $this->getClient($account);

        Log::channel('daily')->info('[TikTokFinance] syncing statements', ['account_id' => $account->id]);

        $response = $client->Finance->getStatements(['page_size' => 100]);
        $statements = $response['statements'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($statements as $stmt) {
            $stmtId = $stmt['statement_id'] ?? $stmt['id'] ?? null;
            if (! $stmtId) {
                continue;
            }

            TiktokFinanceStatement::updateOrCreate(
                [
                    'platform_account_id' => $account->id,
                    'tiktok_statement_id' => $stmtId,
                ],
                [
                    'statement_type' => $stmt['type'] ?? $stmt['statement_type'] ?? null,
                    'total_amount' => (float) ($stmt['total_amount'] ?? 0),
                    'order_amount' => (float) ($stmt['order_amount'] ?? 0),
                    'commission_amount' => (float) ($stmt['commission_amount'] ?? $stmt['affiliate_commission'] ?? 0),
                    'shipping_fee' => (float) ($stmt['shipping_fee'] ?? 0),
                    'platform_fee' => (float) ($stmt['platform_fee'] ?? $stmt['platform_commission'] ?? 0),
                    'currency' => $stmt['currency'] ?? null,
                    'status' => $stmt['status'] ?? null,
                    'statement_time' => isset($stmt['statement_time'])
                        ? \Carbon\Carbon::createFromTimestamp($stmt['statement_time'])
                        : null,
                    'raw_response' => $stmt,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync transactions for a specific statement.
     */
    public function syncStatementTransactions(PlatformAccount $account, TiktokFinanceStatement $statement): int
    {
        $client = $this->getClient($account);

        $response = $client->Finance->getStatementTransactions($statement->tiktok_statement_id, ['page_size' => 100]);
        $transactions = $response['statement_transactions'] ?? $response['data'] ?? [];
        $count = 0;

        foreach ($transactions as $txn) {
            TiktokFinanceTransaction::create([
                'platform_account_id' => $account->id,
                'statement_id' => $statement->id,
                'tiktok_order_id' => $txn['order_id'] ?? null,
                'transaction_type' => $txn['transaction_type'] ?? $txn['type'] ?? null,
                'order_amount' => (float) ($txn['order_amount'] ?? 0),
                'seller_revenue' => (float) ($txn['seller_revenue'] ?? $txn['settlement_amount'] ?? 0),
                'affiliate_commission' => (float) ($txn['affiliate_commission'] ?? 0),
                'platform_commission' => (float) ($txn['platform_commission'] ?? 0),
                'shipping_fee' => (float) ($txn['shipping_fee'] ?? 0),
                'order_created_at' => isset($txn['order_create_time'])
                    ? \Carbon\Carbon::createFromTimestamp($txn['order_create_time'])
                    : null,
                'raw_response' => $txn,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Sync payments.
     */
    public function fetchPayments(PlatformAccount $account): array
    {
        $client = $this->getClient($account);

        return $client->Finance->getPayments(['page_size' => 100]);
    }

    /**
     * Sync withdrawals.
     */
    public function fetchWithdrawals(PlatformAccount $account): array
    {
        $client = $this->getClient($account);

        return $client->Finance->getWithdrawals(['page_size' => 100]);
    }

    /**
     * Fetch transaction breakdown for a specific order.
     */
    public function fetchOrderTransactions(PlatformAccount $account, string $orderId): array
    {
        $client = $this->getClient($account);

        return $client->Finance->getOrderStatementTransactions($orderId);
    }

    private function getClient(PlatformAccount $account): mixed
    {
        if ($this->authService->needsTokenRefresh($account)) {
            $this->authService->refreshToken($account);
        }

        return $this->clientFactory->createClientForAccount($account);
    }
}
```

**Step 4: Run tests — PASS**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokFinanceSyncServiceTest.php
```

**Step 5: Commit**

```bash
git add app/Services/TikTok/TikTokFinanceSyncService.php tests/Feature/Services/TikTok/TikTokFinanceSyncServiceTest.php
git commit -m "feat(tiktok): add TikTokFinanceSyncService for statements, transactions, payments"
```

---

### Task C3: Queued job + Artisan command for Finance sync

**Files:**
- Create: `app/Jobs/SyncTikTokFinance.php`
- Create: `app/Console/Commands/TikTokSyncFinance.php`
- Modify: `routes/console.php`

**Step 1: Job** (follow same pattern as SyncTikTokAnalytics)

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Models\TiktokFinanceStatement;
use App\Services\TikTok\TikTokFinanceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokFinance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public PlatformAccount $account) {}

    public function handle(TikTokFinanceSyncService $service): void
    {
        if (! $this->account->is_active) {
            return;
        }

        Log::info('[SyncTikTokFinance] starting', ['account_id' => $this->account->id]);

        // Sync statements
        $service->syncStatements($this->account);

        // Sync transactions for recent statements
        $recentStatements = TiktokFinanceStatement::where('platform_account_id', $this->account->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        foreach ($recentStatements as $statement) {
            $service->syncStatementTransactions($this->account, $statement);
        }

        $this->account->updateSyncStatus('completed', 'finance');
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('[SyncTikTokFinance] failed', [
            'account_id' => $this->account->id,
            'error' => $e?->getMessage(),
        ]);
        $this->account->recordSyncError($e?->getMessage() ?? 'Unknown error');
    }

    public function tags(): array
    {
        return ['tiktok-sync', 'finance', "account:{$this->account->id}"];
    }
}
```

**Step 2: Artisan command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokFinance;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncFinance extends Command
{
    protected $signature = 'tiktok:sync-finance {--account= : Specific platform account ID}';
    protected $description = 'Sync TikTok Shop finance data (statements, transactions, payments)';

    public function handle(): int
    {
        $query = PlatformAccount::query()
            ->where('is_active', true)
            ->whereHas('platform', fn ($q) => $q->where('slug', 'tiktok-shop'));

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        foreach ($accounts as $account) {
            SyncTikTokFinance::dispatch($account);
            $this->info("Dispatched finance sync for: {$account->name}");
        }

        $this->info("Dispatched {$accounts->count()} finance sync job(s).");

        return self::SUCCESS;
    }
}
```

**Step 3: Schedule** in `routes/console.php`:

```php
Schedule::command('tiktok:sync-finance')->dailyAt('06:00');
```

**Step 4: Commit**

```bash
git add app/Jobs/SyncTikTokFinance.php app/Console/Commands/TikTokSyncFinance.php routes/console.php
git commit -m "feat(tiktok): add SyncTikTokFinance job and artisan command with daily schedule"
```

---

# MODULE D — MASTER SYNC COMMAND

### Task D1: `tiktok:sync-all` Artisan command

**Files:**
- Create: `app/Console/Commands/TikTokSyncAll.php`

**Step 1: Implement** — dispatches all three sync jobs for each account with staggered delays to respect rate limits:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokAnalytics;
use App\Jobs\SyncTikTokAffiliates;
use App\Jobs\SyncTikTokFinance;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncAll extends Command
{
    protected $signature = 'tiktok:sync-all {--account= : Specific platform account ID}';
    protected $description = 'Sync ALL TikTok Shop data (analytics, affiliates, finance) with staggered delays';

    public function handle(): int
    {
        $query = PlatformAccount::query()
            ->where('is_active', true)
            ->whereHas('platform', fn ($q) => $q->where('slug', 'tiktok-shop'));

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        foreach ($accounts as $account) {
            // Stagger by 2 minutes to avoid rate limits
            SyncTikTokAnalytics::dispatch($account, 'all');
            SyncTikTokAffiliates::dispatch($account)->delay(now()->addMinutes(2));
            SyncTikTokFinance::dispatch($account)->delay(now()->addMinutes(4));

            $this->info("Dispatched all syncs for: {$account->name}");
        }

        $this->info("Dispatched {$accounts->count()} x 3 sync jobs.");

        return self::SUCCESS;
    }
}
```

**Step 2: Commit**

```bash
git add app/Console/Commands/TikTokSyncAll.php
git commit -m "feat(tiktok): add tiktok:sync-all master command with staggered dispatch"
```

---

# FACTORIES (needed for tests)

### Task F1: Create factories for new models

**Files:**
- Create: `database/factories/TiktokCreatorFactory.php`
- Create: `database/factories/TiktokCreatorContentFactory.php`

**Step 1: Generate**

```bash
php artisan make:factory TiktokCreatorFactory --model=TiktokCreator --no-interaction
php artisan make:factory TiktokCreatorContentFactory --model=TiktokCreatorContent --no-interaction
```

**Step 2: Implement TiktokCreatorFactory**

```php
<?php

namespace Database\Factories;

use App\Models\PlatformAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class TiktokCreatorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'platform_account_id' => PlatformAccount::factory(),
            'creator_user_id' => 'CR' . fake()->unique()->numerify('######'),
            'handle' => '@' . fake()->userName(),
            'display_name' => fake()->name(),
            'country_code' => fake()->randomElement(['MY', 'SG', 'ID', 'TH']),
            'follower_count' => fake()->numberBetween(1000, 500000),
            'total_gmv' => fake()->randomFloat(2, 0, 50000),
            'total_orders' => fake()->numberBetween(0, 500),
            'total_commission' => fake()->randomFloat(2, 0, 5000),
        ];
    }
}
```

**Step 3: Commit**

```bash
git add database/factories/
git commit -m "feat(tiktok): add factories for TiktokCreator and TiktokCreatorContent models"
```

---

# VERIFICATION

### Task V1: Run all tests

```bash
php artisan test --compact --filter='TikTok|Tiktok|Content'
```

All must pass.

### Task V2: Run Pint

```bash
vendor/bin/pint --dirty
```

### Task V3: Smoke-test all artisan commands

```bash
php artisan tiktok:sync-analytics --type=shop
php artisan tiktok:sync-affiliates
php artisan tiktok:sync-finance
php artisan tiktok:sync-all
```

### Task V4: Build frontend and verify

```bash
npm run build
```

Navigate to `/cms/creators` — verify Creator Leaderboard loads.
Navigate to a posted Content item — verify "Promoted by" section appears (if data exists).

---

# SCHEDULE SUMMARY

After all tasks are complete, `routes/console.php` should have these TikTok entries:

```php
// TikTok Shop sync schedule
Schedule::command('tiktok:sync-content-stats')->dailyAt('03:00');  // from previous plan
Schedule::command('tiktok:sync-analytics')->dailyAt('04:00');       // Module A
Schedule::command('tiktok:sync-affiliates')->dailyAt('05:00');      // Module B
Schedule::command('tiktok:sync-finance')->dailyAt('06:00');         // Module C
```

Or replace all four with a single master command:

```php
Schedule::command('tiktok:sync-all')->dailyAt('03:00');
```

---

# NEW FILES SUMMARY

```
app/Services/TikTok/
├── TikTokAnalyticsSyncService.php       (Module A)
├── TikTokAffiliateSyncService.php       (Module B)
└── TikTokFinanceSyncService.php         (Module C)

app/Jobs/
├── SyncTikTokAnalytics.php              (Module A)
├── SyncTikTokAffiliates.php             (Module B)
└── SyncTikTokFinance.php                (Module C)

app/Console/Commands/
├── TikTokSyncAnalytics.php              (Module A)
├── TikTokSyncAffiliates.php             (Module B)
├── TikTokSyncFinance.php                (Module C)
└── TikTokSyncAll.php                    (Module D)

app/Models/
├── TiktokShopPerformanceSnapshot.php    (Module A)
├── TiktokProductPerformance.php         (Module A)
├── TiktokCreator.php                    (Module B)
├── TiktokCreatorContent.php             (Module B)
├── TiktokAffiliateOrder.php             (Module B)
├── TiktokFinanceStatement.php           (Module C)
└── TiktokFinanceTransaction.php         (Module C)

app/Http/Controllers/Api/Cms/
└── CmsAffiliateController.php           (Module B)

resources/js/cms/
├── pages/CreatorLeaderboard.jsx         (Module B)
└── lib/api.js                           (updated)

database/migrations/
├── *_create_tiktok_shop_performance_snapshots_table.php
├── *_create_tiktok_product_performance_table.php
├── *_create_tiktok_creators_table.php
├── *_create_tiktok_creator_contents_table.php
├── *_create_tiktok_affiliate_orders_table.php
├── *_create_tiktok_finance_tables.php
└── *_add_last_affiliate_sync_at_to_platform_accounts_table.php

database/factories/
├── TiktokCreatorFactory.php
└── TiktokCreatorContentFactory.php

tests/Feature/Services/TikTok/
├── TikTokAnalyticsSyncServiceTest.php
├── TikTokAffiliateSyncServiceTest.php
└── TikTokFinanceSyncServiceTest.php
```
