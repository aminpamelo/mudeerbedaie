# TikTok Shop Content Stats Sync Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Pull real-time video performance stats (views, likes, comments, shares) from TikTok Shop API into the CMS Content detail page, replacing manual stat entry for shop-tagged videos.

**Architecture:** Mirror the existing `TikTokOrderSyncService` / `SyncTikTokOrders` pattern. Add a `platform_account_id` FK on `contents` linking each content item to the TikTok Shop account that posted it. A new `TikTokContentStatsSyncService` uses the existing `TikTokClientFactory` to call `Analytics->getShopVideoPerformance($videoId)`, maps the response into a `ContentStat` row, and stores the raw payload for debugging. A "Sync from TikTok" button on the Content detail page dispatches a queued job that does the same work. The shop video ID is auto-extracted from `tiktok_url` on save.

**Tech Stack:**
- Laravel 12, Livewire Volt (for admin pages — not touched here)
- React SPA for CMS module (`resources/js/cms/`)
- `ecomphp/tiktokshop-php` SDK (already integrated for Orders/Products)
- Existing `PlatformAccount` + `PlatformApiCredential` OAuth infrastructure
- Pest v4 tests

---

## Critical Pre-Flight Findings

1. **API version blocker:** `EcomPHP\TiktokShop\Resources\Analytics` has `$minimum_version = 202405`. Current config `services.tiktok.api_version` defaults to `202309`. The SDK will reject Analytics calls unless version is bumped. **First task fixes this.**
2. **No linkage today:** `contents` table has `tiktok_url` and `tiktok_post_id` columns, but `tiktok_post_id` is never populated and there is no FK to `platform_accounts`. Both gaps are addressed in Phase 1.
3. **Unknown response shape:** The SDK method `getShopVideoPerformance($video_id, $params)` exists ([vendor/ecomphp/tiktokshop-php/src/Resources/Analytics.php:70-75](../../vendor/ecomphp/tiktokshop-php/src/Resources/Analytics.php#L70-L75)) but the TikTok Shop docs have slightly different field names across regions. **Task 0 is a live spike** to discover the actual field names before mapping them into `ContentStat`.
4. **Stats table is manual-entry-shaped:** [content_stats](../../database/migrations/2026_03_30_223019_create_content_stats_table.php) only has views/likes/comments/shares. We'll add `raw_response` JSON + `source` string so auto-sync and manual entries can co-exist and so debugging the API is possible.

---

## Phase 0 — Discovery Spike (DO THIS BEFORE CODING)

### Task 0: Live tinker spike to discover `getShopVideoPerformance` response shape

**Goal:** Capture a real response from the TikTok Shop Analytics API to lock in field mapping before writing code.

**Files:**
- No files modified
- Capture output to: `docs/plans/2026-04-11-tiktok-shop-content-stats-sync-spike-output.md` (gitignored or committed — user decides)

**Step 1: Bump the API version in `.env`**

TikTok Analytics requires `202405`. Edit `.env`:

```dotenv
TIKTOK_API_VERSION=202405
```

Then run:
```bash
php artisan config:clear
```

**Step 2: Pick a real TikTok Shop account + a real video ID**

- Open [/admin/platforms/tiktok-shop/accounts](http://mudeerbedaie.test/admin/platforms/tiktok-shop/accounts) in the browser and pick an active, connected account. Note its `id`.
- Find the video ID from a known TikTok Shop video — the numeric segment in a URL like `https://www.tiktok.com/@user/video/7452345678901234567`.

**Step 3: Run the spike in tinker**

```bash
php artisan tinker
```

Inside tinker, run:

```php
$account = \App\Models\PlatformAccount::find(ACCOUNT_ID);
$factory = app(\App\Services\TikTok\TikTokClientFactory::class);
$client = $factory->createClientForAccount($account);

// Single video performance — this is the key call
$resp = $client->Analytics->getShopVideoPerformance('PASTE_VIDEO_ID_HERE');
dump($resp);

// Also try the list method (useful for listing all shop videos)
$resp2 = $client->Analytics->getShopVideoPerformanceList([
    'start_date_ge' => now()->subDays(30)->toDateString(),
    'end_date_lt' => now()->toDateString(),
]);
dump($resp2);
```

**Step 4: Record the response field mapping**

Create `docs/plans/2026-04-11-tiktok-shop-content-stats-sync-spike-output.md` with:
- The exact JSON/array keys returned (e.g., `video_view_cnt`, `like_cnt`, `comment_cnt`, `share_cnt` — actual names from the response)
- Any date-range parameters that are required
- Any error messages encountered (e.g., "shop not authorized for analytics scope")

**Step 5: Commit the spike findings**

```bash
git add .env.example docs/plans/2026-04-11-tiktok-shop-content-stats-sync-spike-output.md
git commit -m "docs: record TikTok Shop video analytics spike findings"
```

> **STOP POINT:** If the spike fails (scope not granted, version rejected, empty response) — pause and bring findings to the user before continuing. All subsequent field mapping assumes this data is in hand.

---

## Phase 1 — Schema Foundations

### Task 1: Migration — link Content to PlatformAccount

**Files:**
- Create: `database/migrations/<timestamp>_add_platform_account_id_to_contents_table.php`

**Step 1: Generate the migration**

```bash
php artisan make:migration add_platform_account_id_to_contents_table --table=contents
```

**Step 2: Write the migration** (MySQL + SQLite safe)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->foreignId('platform_account_id')
                ->nullable()
                ->after('video_url')
                ->constrained('platform_accounts')
                ->nullOnDelete();

            $table->index(['platform_account_id', 'tiktok_post_id'], 'contents_platform_post_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex('contents_platform_post_idx');
            $table->dropConstrainedForeignId('platform_account_id');
        });
    }
};
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: `1 migration ran.`

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat(cms): add platform_account_id to contents table"
```

---

### Task 2: Migration — extend `content_stats` with `raw_response` + `source`

**Files:**
- Create: `database/migrations/<timestamp>_add_raw_response_and_source_to_content_stats_table.php`

**Step 1: Generate**

```bash
php artisan make:migration add_raw_response_and_source_to_content_stats_table --table=content_stats
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
        Schema::table('content_stats', function (Blueprint $table) {
            $table->string('source', 32)->default('manual')->after('shares');
            $table->json('raw_response')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('content_stats', function (Blueprint $table) {
            $table->dropColumn(['source', 'raw_response']);
        });
    }
};
```

**Step 3: Run**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat(cms): add source and raw_response to content_stats"
```

---

### Task 3: Update `Content` model — add fillable + relation

**Files:**
- Modify: [app/Models/Content.php](../../app/Models/Content.php)

**Step 1: Add `platform_account_id` to `$fillable`** (line 18-33). Add after `'video_url'`:

```php
'platform_account_id',
```

**Step 2: Add the relationship method** at the end of the class, after `adCampaigns()` (around line 97):

```php
/**
 * Get the TikTok Shop account this content was posted from.
 */
public function platformAccount(): BelongsTo
{
    return $this->belongsTo(PlatformAccount::class);
}
```

**Step 3: Verify no test breakage**

```bash
php artisan test --compact --filter=Content
```

Expected: PASS (or no matching tests — that's fine).

**Step 4: Commit**

```bash
git add app/Models/Content.php
git commit -m "feat(cms): add platformAccount relationship to Content model"
```

---

### Task 4: Update `ContentStat` model — add fillable + casts

**Files:**
- Modify: `app/Models/ContentStat.php`

**Step 1: Read the current file first** (get its exact structure).

**Step 2: Add `source` and `raw_response` to `$fillable`:**

```php
protected $fillable = [
    'content_id',
    'views',
    'likes',
    'comments',
    'shares',
    'source',
    'raw_response',
    'fetched_at',
];
```

**Step 3: Add cast in `casts()` method:**

```php
protected function casts(): array
{
    return [
        'views' => 'integer',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'raw_response' => 'array',
        'fetched_at' => 'datetime',
    ];
}
```

**Step 4: Commit**

```bash
git add app/Models/ContentStat.php
git commit -m "feat(cms): cast raw_response and add source to ContentStat"
```

---

## Phase 2 — TikTok URL Parser

### Task 5: Write the failing unit test for `TikTokUrlParser`

**Files:**
- Create: `tests/Unit/Services/TikTok/TikTokUrlParserTest.php`

**Step 1: Generate the test file**

```bash
php artisan make:test --unit Services/TikTok/TikTokUrlParserTest
```

**Step 2: Write the test cases**

```php
<?php

use App\Services\TikTok\TikTokUrlParser;

it('extracts video id from standard @username url', function () {
    expect(TikTokUrlParser::extractVideoId('https://www.tiktok.com/@myshop/video/7452345678901234567'))
        ->toBe('7452345678901234567');
});

it('extracts video id from mobile m.tiktok.com url', function () {
    expect(TikTokUrlParser::extractVideoId('https://m.tiktok.com/v/7452345678901234567.html'))
        ->toBe('7452345678901234567');
});

it('extracts video id when url has query params', function () {
    expect(TikTokUrlParser::extractVideoId('https://www.tiktok.com/@myshop/video/7452345678901234567?is_from_webapp=1'))
        ->toBe('7452345678901234567');
});

it('returns null for non-tiktok urls', function () {
    expect(TikTokUrlParser::extractVideoId('https://instagram.com/p/ABCDEF/'))
        ->toBeNull();
});

it('returns null for null or empty input', function () {
    expect(TikTokUrlParser::extractVideoId(null))->toBeNull();
    expect(TikTokUrlParser::extractVideoId(''))->toBeNull();
});

it('returns null for short vt.tiktok.com urls (requires redirect resolution)', function () {
    expect(TikTokUrlParser::extractVideoId('https://vt.tiktok.com/ZS8abcdef/'))
        ->toBeNull();
});
```

**Step 3: Run the test and confirm it fails**

```bash
php artisan test --compact tests/Unit/Services/TikTok/TikTokUrlParserTest.php
```

Expected: FAIL with "Class App\Services\TikTok\TikTokUrlParser not found".

---

### Task 6: Implement `TikTokUrlParser`

**Files:**
- Create: `app/Services/TikTok/TikTokUrlParser.php`

**Step 1: Create the service class**

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok;

class TikTokUrlParser
{
    /**
     * Extract the numeric video id from a TikTok URL.
     * Supports:
     *   - https://www.tiktok.com/@user/video/{id}
     *   - https://m.tiktok.com/v/{id}.html
     * Returns null for short URLs (vt.tiktok.com/...) which require HTTP redirect resolution.
     */
    public static function extractVideoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (! str_contains($url, 'tiktok.com')) {
            return null;
        }

        // Short URLs (vt.tiktok.com) require redirect resolution — unsupported here.
        if (str_contains($url, 'vt.tiktok.com')) {
            return null;
        }

        if (preg_match('#/video/(\d+)#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#/v/(\d+)\.html#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
```

**Step 2: Run the test and confirm it passes**

```bash
php artisan test --compact tests/Unit/Services/TikTok/TikTokUrlParserTest.php
```

Expected: PASS (6 tests).

**Step 3: Commit**

```bash
git add app/Services/TikTok/TikTokUrlParser.php tests/Unit/Services/TikTok/TikTokUrlParserTest.php
git commit -m "feat(tiktok): add TikTokUrlParser to extract shop video ids"
```

---

### Task 7: Auto-populate `tiktok_post_id` on Content save

**Files:**
- Modify: [app/Models/Content.php](../../app/Models/Content.php)
- Test: `tests/Feature/Cms/ContentTiktokPostIdTest.php`

**Step 1: Write the failing feature test**

```bash
php artisan make:test Cms/ContentTiktokPostIdTest
```

Contents:

```php
<?php

use App\Models\Content;

it('auto-populates tiktok_post_id when tiktok_url is set', function () {
    $content = Content::factory()->create([
        'tiktok_url' => 'https://www.tiktok.com/@shop/video/7452345678901234567',
        'tiktok_post_id' => null,
    ]);

    expect($content->fresh()->tiktok_post_id)->toBe('7452345678901234567');
});

it('does not overwrite a manually set tiktok_post_id when url matches', function () {
    $content = Content::factory()->create([
        'tiktok_url' => 'https://www.tiktok.com/@shop/video/7452345678901234567',
    ]);

    expect($content->tiktok_post_id)->toBe('7452345678901234567');
});

it('leaves tiktok_post_id null when tiktok_url is missing', function () {
    $content = Content::factory()->create([
        'tiktok_url' => null,
    ]);

    expect($content->tiktok_post_id)->toBeNull();
});
```

**Step 2: Run and verify FAIL**

```bash
php artisan test --compact tests/Feature/Cms/ContentTiktokPostIdTest.php
```

Expected: FAIL (first test — `tiktok_post_id` is null).

**Step 3: Add the `booted()` hook on the Content model**

Add to `app/Models/Content.php` after the `casts()` method:

```php
protected static function booted(): void
{
    static::saving(function (Content $content): void {
        if ($content->isDirty('tiktok_url') && $content->tiktok_url) {
            $videoId = \App\Services\TikTok\TikTokUrlParser::extractVideoId($content->tiktok_url);
            if ($videoId) {
                $content->tiktok_post_id = $videoId;
            }
        }
    });
}
```

**Step 4: Run the test — should PASS**

```bash
php artisan test --compact tests/Feature/Cms/ContentTiktokPostIdTest.php
```

Expected: PASS (3 tests).

**Step 5: Commit**

```bash
git add app/Models/Content.php tests/Feature/Cms/ContentTiktokPostIdTest.php
git commit -m "feat(cms): auto-populate tiktok_post_id from tiktok_url on save"
```

---

## Phase 3 — Stats Sync Service

### Task 8: Write the failing feature test for `TikTokContentStatsSyncService`

**Files:**
- Create: `tests/Feature/Services/TikTok/TikTokContentStatsSyncServiceTest.php`

**Step 1: Generate**

```bash
php artisan make:test Services/TikTok/TikTokContentStatsSyncServiceTest
```

**Step 2: Write the tests using a mocked client**

> **Important:** The field names in this test (`video_view_cnt`, etc.) are placeholders — replace them with the real names you recorded in **Task 0's spike output** before running.

```php
<?php

use App\Models\Content;
use App\Models\ContentStat;
use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokClientFactory;
use App\Services\TikTok\TikTokContentStatsSyncService;

use function Pest\Laravel\mock;

it('fetches stats from TikTok Shop and stores them in content_stats', function () {
    $account = PlatformAccount::factory()->create();
    $content = Content::factory()->create([
        'platform_account_id' => $account->id,
        'tiktok_url' => 'https://www.tiktok.com/@shop/video/7452345678901234567',
        'stage' => 'posted',
    ]);

    // Mock the SDK client — response shape is based on Task 0 spike findings
    $fakeAnalytics = new class
    {
        public function getShopVideoPerformance($videoId, $params = [])
        {
            return [
                'video_view_cnt' => 12345,
                'like_cnt' => 678,
                'comment_cnt' => 42,
                'share_cnt' => 15,
            ];
        }
    };

    $fakeClient = new class($fakeAnalytics)
    {
        public function __construct(public $Analytics) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')
        ->once()
        ->andReturn($fakeClient);

    $service = app(TikTokContentStatsSyncService::class);
    $stat = $service->fetchStatsForContent($content);

    expect($stat)->toBeInstanceOf(ContentStat::class)
        ->and($stat->views)->toBe(12345)
        ->and($stat->likes)->toBe(678)
        ->and($stat->comments)->toBe(42)
        ->and($stat->shares)->toBe(15)
        ->and($stat->source)->toBe('tiktok_api')
        ->and($stat->raw_response)->toBeArray();
});

it('throws when content has no platform account', function () {
    $content = Content::factory()->create([
        'platform_account_id' => null,
        'tiktok_post_id' => '7452345678901234567',
    ]);

    app(TikTokContentStatsSyncService::class)->fetchStatsForContent($content);
})->throws(\RuntimeException::class, 'Content has no linked platform account');

it('throws when content has no tiktok_post_id', function () {
    $account = PlatformAccount::factory()->create();
    $content = Content::factory()->create([
        'platform_account_id' => $account->id,
        'tiktok_url' => null,
        'tiktok_post_id' => null,
    ]);

    app(TikTokContentStatsSyncService::class)->fetchStatsForContent($content);
})->throws(\RuntimeException::class, 'Content has no TikTok video id');

it('auto-flags content for ads when views exceed threshold', function () {
    $account = PlatformAccount::factory()->create();
    $content = Content::factory()->create([
        'platform_account_id' => $account->id,
        'tiktok_url' => 'https://www.tiktok.com/@shop/video/7452345678901234567',
        'is_flagged_for_ads' => false,
    ]);

    $fakeAnalytics = new class
    {
        public function getShopVideoPerformance($videoId, $params = [])
        {
            return [
                'video_view_cnt' => 15000,
                'like_cnt' => 500,
                'comment_cnt' => 20,
                'share_cnt' => 10,
            ];
        }
    };
    $fakeClient = new class($fakeAnalytics)
    {
        public function __construct(public $Analytics) {}
    };

    mock(TikTokClientFactory::class)
        ->shouldReceive('createClientForAccount')
        ->once()
        ->andReturn($fakeClient);

    app(TikTokContentStatsSyncService::class)->fetchStatsForContent($content);

    expect($content->fresh()->is_flagged_for_ads)->toBeTrue();
});
```

**Step 3: Run — confirm FAIL (class not found)**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokContentStatsSyncServiceTest.php
```

Expected: FAIL (TikTokContentStatsSyncService not found).

---

### Task 9: Implement `TikTokContentStatsSyncService`

**Files:**
- Create: `app/Services/TikTok/TikTokContentStatsSyncService.php`

**Step 1: Write the service**

> Replace `video_view_cnt`, `like_cnt`, `comment_cnt`, `share_cnt` with the **actual field names** you recorded in Task 0.

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\Content;
use App\Models\ContentStat;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TikTokContentStatsSyncService
{
    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService,
    ) {}

    /**
     * Fetch video performance stats from TikTok Shop for a single Content item
     * and persist a new ContentStat row.
     */
    public function fetchStatsForContent(Content $content): ContentStat
    {
        $account = $content->platformAccount;
        if (! $account) {
            throw new RuntimeException('Content has no linked platform account');
        }

        $videoId = $content->tiktok_post_id
            ?: TikTokUrlParser::extractVideoId($content->tiktok_url);

        if (! $videoId) {
            throw new RuntimeException('Content has no TikTok video id');
        }

        // Refresh token proactively if needed (same pattern as TikTokOrderSyncService)
        if ($this->authService->needsTokenRefresh($account)) {
            $this->authService->refreshToken($account);
        }

        $client = $this->clientFactory->createClientForAccount($account);

        Log::channel('daily')->info('[TikTokContentStats] fetching', [
            'content_id' => $content->id,
            'video_id' => $videoId,
            'account_id' => $account->id,
        ]);

        try {
            $response = $client->Analytics->getShopVideoPerformance($videoId);
        } catch (\Throwable $e) {
            Log::channel('daily')->error('[TikTokContentStats] fetch failed', [
                'content_id' => $content->id,
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $stat = $this->persistStat($content, $response);
        $this->maybeFlagForAds($content, $stat);

        return $stat;
    }

    /**
     * Map API response into a ContentStat row.
     * Update the field keys to match the spike output from Task 0.
     */
    private function persistStat(Content $content, array $response): ContentStat
    {
        return ContentStat::create([
            'content_id' => $content->id,
            'views' => (int) ($response['video_view_cnt'] ?? 0),
            'likes' => (int) ($response['like_cnt'] ?? 0),
            'comments' => (int) ($response['comment_cnt'] ?? 0),
            'shares' => (int) ($response['share_cnt'] ?? 0),
            'source' => 'tiktok_api',
            'raw_response' => $response,
            'fetched_at' => now(),
        ]);
    }

    private function maybeFlagForAds(Content $content, ContentStat $stat): void
    {
        if ($content->is_flagged_for_ads) {
            return;
        }

        $engagementRate = $stat->views > 0
            ? (($stat->likes + $stat->comments + $stat->shares) / $stat->views) * 100
            : 0;

        if ($stat->views > 10000 || $stat->likes > 1000 || $engagementRate > 5) {
            $content->update(['is_flagged_for_ads' => true]);
        }
    }
}
```

**Step 2: Run the tests**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokContentStatsSyncServiceTest.php
```

Expected: PASS (4 tests).

**Step 3: Commit**

```bash
git add app/Services/TikTok/TikTokContentStatsSyncService.php tests/Feature/Services/TikTok/TikTokContentStatsSyncServiceTest.php
git commit -m "feat(cms): add TikTokContentStatsSyncService for video performance sync"
```

---

### Task 10: Queued job `SyncTikTokContentStats`

**Files:**
- Create: `app/Jobs/SyncTikTokContentStats.php`
- Test: `tests/Feature/Jobs/SyncTikTokContentStatsTest.php`

**Step 1: Generate**

```bash
php artisan make:job SyncTikTokContentStats
php artisan make:test Jobs/SyncTikTokContentStatsTest
```

**Step 2: Write the failing test**

```php
<?php

use App\Jobs\SyncTikTokContentStats;
use App\Models\Content;
use App\Models\ContentStat;
use App\Services\TikTok\TikTokContentStatsSyncService;

use function Pest\Laravel\mock;

it('calls the sync service for the given content', function () {
    $content = Content::factory()->create();

    mock(TikTokContentStatsSyncService::class)
        ->shouldReceive('fetchStatsForContent')
        ->once()
        ->with(\Mockery::on(fn ($c) => $c->id === $content->id))
        ->andReturn(new ContentStat(['views' => 1, 'likes' => 0, 'comments' => 0, 'shares' => 0]));

    (new SyncTikTokContentStats($content->id))->handle(app(TikTokContentStatsSyncService::class));
});
```

**Step 3: Run — confirm FAIL**

```bash
php artisan test --compact tests/Feature/Jobs/SyncTikTokContentStatsTest.php
```

**Step 4: Implement the job**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Content;
use App\Services\TikTok\TikTokContentStatsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokContentStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $contentId) {}

    public function handle(TikTokContentStatsSyncService $service): void
    {
        $content = Content::find($this->contentId);
        if (! $content) {
            Log::channel('daily')->warning('[SyncTikTokContentStats] content not found', [
                'content_id' => $this->contentId,
            ]);
            return;
        }

        $service->fetchStatsForContent($content);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('daily')->error('[SyncTikTokContentStats] job failed permanently', [
            'content_id' => $this->contentId,
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['tiktok-sync', 'content-stats', "content:{$this->contentId}"];
    }
}
```

**Step 5: Run the test — PASS**

```bash
php artisan test --compact tests/Feature/Jobs/SyncTikTokContentStatsTest.php
```

**Step 6: Commit**

```bash
git add app/Jobs/SyncTikTokContentStats.php tests/Feature/Jobs/SyncTikTokContentStatsTest.php
git commit -m "feat(cms): add SyncTikTokContentStats queued job"
```

---

## Phase 4 — API + UI

### Task 11: Add `POST /api/cms/contents/{id}/sync-stats` endpoint

**Files:**
- Modify: [app/Http/Controllers/Api/Cms/CmsContentController.php](../../app/Http/Controllers/Api/Cms/CmsContentController.php)
- Modify: [routes/api.php](../../routes/api.php) (near line 1127-1132)
- Test: `tests/Feature/Cms/SyncContentStatsEndpointTest.php`

**Step 1: Write the failing test**

```bash
php artisan make:test Cms/SyncContentStatsEndpointTest
```

```php
<?php

use App\Models\Content;
use App\Models\ContentStat;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\TikTok\TikTokContentStatsSyncService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;
use function Pest\Laravel\postJson;

it('syncs content stats via the API endpoint', function () {
    $user = User::factory()->create();
    $account = PlatformAccount::factory()->create();
    $content = Content::factory()->create([
        'platform_account_id' => $account->id,
        'tiktok_url' => 'https://www.tiktok.com/@shop/video/7452345678901234567',
    ]);

    mock(TikTokContentStatsSyncService::class)
        ->shouldReceive('fetchStatsForContent')
        ->once()
        ->andReturn(ContentStat::factory()->make([
            'content_id' => $content->id,
            'views' => 100,
            'likes' => 10,
            'comments' => 2,
            'shares' => 1,
        ]));

    actingAs($user);
    postJson("/api/cms/contents/{$content->id}/sync-stats")
        ->assertSuccessful()
        ->assertJsonPath('data.views', 100);
});

it('returns 422 when content has no platform account', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['platform_account_id' => null]);

    actingAs($user);
    postJson("/api/cms/contents/{$content->id}/sync-stats")
        ->assertStatus(422);
});
```

**Step 2: Add the controller method** at the end of [CmsContentController.php](../../app/Http/Controllers/Api/Cms/CmsContentController.php) (after `addStats()`):

```php
/**
 * Sync performance stats from TikTok Shop API.
 */
public function syncStats(Content $content, TikTokContentStatsSyncService $syncService): JsonResponse
{
    try {
        $stat = $syncService->fetchStatsForContent($content);
    } catch (\RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Failed to sync TikTok stats: '.$e->getMessage(),
        ], 500);
    }

    return response()->json([
        'data' => $stat,
        'message' => 'TikTok stats synced successfully.',
    ], 201);
}
```

Also add the import at the top:

```php
use App\Services\TikTok\TikTokContentStatsSyncService;
```

**Step 3: Add the route** in [routes/api.php](../../routes/api.php) near the CMS content routes (line 1127-1132):

```php
Route::post('cms/contents/{content}/sync-stats', [CmsContentController::class, 'syncStats'])
    ->name('cms.contents.sync-stats');
```

**Step 4: Run the tests**

```bash
php artisan test --compact tests/Feature/Cms/SyncContentStatsEndpointTest.php
```

Expected: PASS (2 tests).

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Cms/CmsContentController.php routes/api.php tests/Feature/Cms/SyncContentStatsEndpointTest.php
git commit -m "feat(cms): add sync-stats API endpoint for TikTok Shop video performance"
```

---

### Task 12: Add `syncContentStats()` to React API client

**Files:**
- Modify: [resources/js/cms/lib/api.js](../../resources/js/cms/lib/api.js)

**Step 1: Read the current file** to find where `addContentStats` is defined (around line 50).

**Step 2: Add new function** right after `addContentStats`:

```js
export const syncContentStats = (contentId) =>
    api.post(`/cms/contents/${contentId}/sync-stats`).then((r) => r.data);
```

**Step 3: Commit**

```bash
git add resources/js/cms/lib/api.js
git commit -m "feat(cms): add syncContentStats API client function"
```

---

### Task 13: Add "Sync from TikTok" button to ContentDetail page

**Files:**
- Modify: [resources/js/cms/pages/ContentDetail.jsx](../../resources/js/cms/pages/ContentDetail.jsx) (around line 680-690 — the TikTok Stats section)

**Step 1: Import the new API function** at the top of the file:

```jsx
import { syncContentStats } from '../lib/api';
```

**Step 2: Add a mutation** inside the component (alongside existing mutations):

```jsx
const syncStatsMutation = useMutation({
    mutationFn: () => syncContentStats(data.id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['content', data.id] });
        toast.success('TikTok stats synced');
    },
    onError: (err) => {
        toast.error(err?.response?.data?.message || 'Failed to sync stats');
    },
});
```

**Step 3: Replace the TikTok Stats header** (around line 680-688) with a header + button row:

```jsx
{/* Right: TikTok Stats */}
<div>
    {currentStage === 'posted' ? (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-lg font-semibold text-slate-800">
                    TikTok Stats
                </h3>
                {data.platform_account_id && (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={syncStatsMutation.isPending}
                        onClick={() => syncStatsMutation.mutate()}
                    >
                        {syncStatsMutation.isPending ? 'Syncing…' : 'Sync from TikTok'}
                    </Button>
                )}
            </div>
            <StatsCard stats={data.stats || data.tiktok_stats || []} />
        </div>
    ) : (
```

**Step 4: Rebuild frontend**

```bash
npm run build
```

**Step 5: Smoke test in browser**

- Navigate to a posted content item with a linked `platform_account_id`
- Click "Sync from TikTok"
- Verify a new stat row appears (either via live refresh or after reloading)

**Step 6: Commit**

```bash
git add resources/js/cms/pages/ContentDetail.jsx
git commit -m "feat(cms): add Sync from TikTok button on content detail page"
```

---

### Task 14: Allow selecting `platform_account_id` in Content create/edit forms

**Files:**
- Modify: `app/Http/Requests/Cms/StoreContentRequest.php`
- Modify: `app/Http/Requests/Cms/UpdateContentRequest.php`
- Modify: [resources/js/cms/pages/ContentCreate.jsx](../../resources/js/cms/pages/ContentCreate.jsx)
- Modify: [resources/js/cms/pages/ContentEdit.jsx](../../resources/js/cms/pages/ContentEdit.jsx)
- Modify: [resources/js/cms/lib/api.js](../../resources/js/cms/lib/api.js)
- Modify: [app/Http/Controllers/Api/Cms/CmsContentController.php](../../app/Http/Controllers/Api/Cms/CmsContentController.php)

**Step 1: Read both form requests** first to see existing validation structure.

**Step 2: Add `platform_account_id` validation rule to `StoreContentRequest` and `UpdateContentRequest`:**

```php
'platform_account_id' => ['nullable', 'integer', 'exists:platform_accounts,id'],
```

**Step 3: Add an API route + controller method** to list active TikTok Shop accounts. In `CmsContentController`:

```php
/**
 * List active TikTok Shop accounts for the platform account dropdown.
 */
public function tiktokAccounts(): JsonResponse
{
    $accounts = PlatformAccount::query()
        ->whereHas('platform', fn ($q) => $q->where('slug', 'tiktok-shop'))
        ->where('is_active', true)
        ->select('id', 'name', 'shop_id', 'country_code')
        ->orderBy('name')
        ->get();

    return response()->json(['data' => $accounts]);
}
```

In `routes/api.php`:

```php
Route::get('cms/tiktok-shop-accounts', [CmsContentController::class, 'tiktokAccounts'])
    ->name('cms.tiktok-shop-accounts');
```

> **Check** the exact platform slug at [/admin/platforms/tiktok-shop/accounts](http://mudeerbedaie.test/admin/platforms/tiktok-shop/accounts) — it may be `tiktok-shop` or `tiktok_shop`. Update the `where('slug', ...)` accordingly.

**Step 4: Add API client function** in `resources/js/cms/lib/api.js`:

```js
export const listTikTokShopAccounts = () =>
    api.get('/cms/tiktok-shop-accounts').then((r) => r.data.data);
```

**Step 5: Add the dropdown** in `ContentCreate.jsx` and `ContentEdit.jsx`. Pattern:

```jsx
import { listTikTokShopAccounts } from '../lib/api';

const { data: tiktokAccounts = [] } = useQuery({
    queryKey: ['tiktok-shop-accounts'],
    queryFn: listTikTokShopAccounts,
});

// Inside the form, near the tiktok_url field:
<div>
    <Label htmlFor="platform_account_id">TikTok Shop Account</Label>
    <Select
        id="platform_account_id"
        value={form.platform_account_id || ''}
        onChange={(e) => setForm({ ...form, platform_account_id: e.target.value || null })}
    >
        <option value="">— None (no auto sync) —</option>
        {tiktokAccounts.map((acc) => (
            <option key={acc.id} value={acc.id}>
                {acc.name} ({acc.country_code})
            </option>
        ))}
    </Select>
</div>
```

> **Match the existing form component style** in ContentCreate/ContentEdit — use the exact `Label`, `Select`, `Input` imports already present. Read the files first to confirm conventions.

**Step 6: Update the controller's `store` and `update` methods** (if they don't already spread `$request->validated()`) to include `platform_account_id`.

**Step 7: Rebuild and smoke-test**

```bash
npm run build
```

Create a new content item, pick a TikTok Shop account, save. Verify `platform_account_id` is set on the DB row via tinker:

```bash
php artisan tinker --execute="dump(\App\Models\Content::latest()->first()->only(['id','platform_account_id','tiktok_url','tiktok_post_id']));"
```

**Step 8: Commit**

```bash
git add app/Http/Requests/Cms/StoreContentRequest.php app/Http/Requests/Cms/UpdateContentRequest.php app/Http/Controllers/Api/Cms/CmsContentController.php routes/api.php resources/js/cms/pages/ContentCreate.jsx resources/js/cms/pages/ContentEdit.jsx resources/js/cms/lib/api.js
git commit -m "feat(cms): allow linking content to TikTok Shop account in forms"
```

---

## Phase 5 — Automation (optional but recommended)

### Task 15: Dispatch sync job automatically when content enters "posted" stage

**Files:**
- Modify: [app/Http/Controllers/Api/Cms/CmsContentController.php](../../app/Http/Controllers/Api/Cms/CmsContentController.php) (the `updateStage` method, around line 200-250)

**Step 1: Read the `updateStage` method** to understand where stage transitions are detected.

**Step 2: After the content stage is updated to `'posted'`**, dispatch the job if the content has a linked account:

```php
use App\Jobs\SyncTikTokContentStats;

// ... inside updateStage, after stage is saved:
if ($content->stage === 'posted' && $content->platform_account_id && $content->tiktok_post_id) {
    SyncTikTokContentStats::dispatch($content->id)->delay(now()->addMinutes(5));
}
```

> 5-minute delay gives TikTok Shop time to register the video before we first query for stats.

**Step 3: Write a feature test** that asserts the job is dispatched on transition:

```php
use App\Jobs\SyncTikTokContentStats;
use Illuminate\Support\Facades\Queue;

it('dispatches SyncTikTokContentStats when content moves to posted stage', function () {
    Queue::fake();

    $account = PlatformAccount::factory()->create();
    $content = Content::factory()->create([
        'platform_account_id' => $account->id,
        'tiktok_url' => 'https://www.tiktok.com/@shop/video/7452345678901234567',
        'stage' => 'posting',
    ]);

    actingAs(User::factory()->create());
    patchJson("/api/cms/contents/{$content->id}/stage", ['stage' => 'posted'])
        ->assertSuccessful();

    Queue::assertPushed(SyncTikTokContentStats::class, fn ($job) => $job->contentId === $content->id);
});
```

**Step 4: Run**

```bash
php artisan test --compact --filter=dispatches_SyncTikTokContentStats
```

Expected: PASS.

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Cms/CmsContentController.php tests/
git commit -m "feat(cms): auto-dispatch TikTok stats sync when content moves to posted"
```

---

### Task 16: Artisan command for scheduled daily re-sync

**Files:**
- Create: `app/Console/Commands/TikTokSyncContentStats.php`
- Modify: `routes/console.php` (add schedule entry)

**Step 1: Generate**

```bash
php artisan make:command TikTokSyncContentStats
```

**Step 2: Implement the command** — re-sync all posted content updated in the last 30 days:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokContentStats;
use App\Models\Content;
use Illuminate\Console\Command;

class TikTokSyncContentStats extends Command
{
    protected $signature = 'tiktok:sync-content-stats {--days=30 : Only re-sync content posted within N days}';

    protected $description = 'Dispatch TikTok Shop video stats sync jobs for recently posted content';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $query = Content::query()
            ->where('stage', 'posted')
            ->whereNotNull('platform_account_id')
            ->whereNotNull('tiktok_post_id')
            ->where('posted_at', '>=', now()->subDays($days));

        $count = 0;
        $query->chunk(100, function ($contents) use (&$count) {
            foreach ($contents as $content) {
                SyncTikTokContentStats::dispatch($content->id);
                $count++;
            }
        });

        $this->info("Dispatched {$count} sync jobs.");
        return self::SUCCESS;
    }
}
```

**Step 3: Schedule it daily** in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('tiktok:sync-content-stats')->dailyAt('03:00');
```

**Step 4: Smoke-test**

```bash
php artisan tiktok:sync-content-stats --days=7
```

Expected: `Dispatched N sync jobs.` (where N is the number of posted contents with a linked account).

**Step 5: Commit**

```bash
git add app/Console/Commands/TikTokSyncContentStats.php routes/console.php
git commit -m "feat(cms): schedule daily TikTok content stats sync"
```

---

## Phase 6 — Full Verification

### Task 17: Run the full affected test suite

```bash
php artisan test --compact --filter='Content|TikTok'
```

All tests should pass. If anything fails, **STOP** and fix before moving on.

### Task 18: Run Pint formatter

```bash
vendor/bin/pint --dirty
```

### Task 19: Final end-to-end smoke test

1. Create a new Content item linked to a TikTok Shop account with a real video URL.
2. Move it through stages to `posted`.
3. Verify the auto-dispatched job runs within 5 minutes and a `content_stats` row is created with `source='tiktok_api'` and a populated `raw_response`.
4. Click "Sync from TikTok" on the ContentDetail page and verify a second stat row is created.
5. Check [http://mudeerbedaie.test/cms/contents/{id}](http://mudeerbedaie.test/cms/contents/) renders the new stats.

### Task 20: Final commit + push

```bash
git status
git log --oneline -20
```

Confirm all changes are committed and push when ready.

---

## Rollback Plan

If the TikTok API integration fails in production:
1. Users can still use the existing "Add Stats" manual dialog — it's untouched (`source='manual'` default).
2. Disable the "Sync from TikTok" button by wrapping it in a feature flag env var check (`CMS_TIKTOK_AUTO_SYNC_ENABLED`).
3. Remove the scheduled command from `routes/console.php`.
4. Migrations are additive — safe to leave in place.

---

## Out of Scope (intentionally deferred)

- Short URL resolution (`vt.tiktok.com/...`) — parser returns null; user must provide full URL.
- Multi-platform (Instagram, YouTube) — current plan is TikTok Shop only.
- Historical stat trend charts — `ContentStat` already supports time-series, but charting is a separate UI task.
- Push notifications when stats exceed ad-flag thresholds — auto-flagging already happens server-side.
- TikTok Display API / creator API (non-shop videos) — requires separate OAuth app, different scopes.
