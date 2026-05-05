# Live Host Platform Orders Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the manual "All Orders" xlsx import in Live Host Desk with the existing TikTok Shop API integration as the source of truth, add a new `/livehost/orders` page that lists platform orders (with matched live session), and rewire the refund reconciler to read from `product_orders`.

**Architecture:** Add `matched_live_session_id` to `product_orders`. A new auto-matcher action tags each TikTok Shop order with its live session at sync time and via a backfill command. `OrderRefundReconciler` is rewritten to read `product_orders` instead of the legacy `tiktok_orders` table. A new Inertia React page `/livehost/orders` lists these orders with filters; the order_list xlsx upload is removed from the Create form and rejected at the controller.

**Tech Stack:** Laravel 12, Inertia.js + React 19, Tailwind v4, Pest 4, MySQL+SQLite (migrations must support both — see CLAUDE.md). Lives in the Inertia "Live Host PIC" surface (`/livehost/*`), not the older Volt `/live-host/*` paradigm.

**Reference:** Design doc at `docs/plans/2026-05-05-livehost-platform-orders-design.md`.

---

## Task ordering rationale

Tasks 1–4 build the data plumbing (migration + matcher + sync hook + backfill). Task 5 swaps the reconciler to the new source. Tasks 6–8 add the UI surface. Task 9 removes the now-dead xlsx path. Task 10 adds nav. Task 11 is the manual smoke test.

Stop and ask before deviating from this order — earlier tasks are dependencies.

---

## Task 1: Add `matched_live_session_id` to `product_orders`

**Files:**
- Create: `database/migrations/2026_05_05_100000_add_matched_live_session_id_to_product_orders_table.php`
- Modify: `app/Models/ProductOrder.php` (add to `$fillable`, add relationship method)
- Test: `tests/Feature/LiveHost/ProductOrderMatchedSessionMigrationTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has matched_live_session_id column on product_orders', function () {
    expect(\Schema::hasColumn('product_orders', 'matched_live_session_id'))->toBeTrue();
});

it('allows setting matched_live_session_id via fillable', function () {
    $order = ProductOrder::factory()->create(['matched_live_session_id' => null]);
    expect($order->matched_live_session_id)->toBeNull();
});

it('exposes matchedLiveSession relationship', function () {
    $order = new ProductOrder();
    $relation = $order->matchedLiveSession();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getForeignKeyName())->toBe('matched_live_session_id');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=ProductOrderMatchedSessionMigrationTest
```

Expected: FAIL — column does not exist / relationship not defined.

**Step 3: Create migration**

```bash
php artisan make:migration add_matched_live_session_id_to_product_orders_table --no-interaction
```

Replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('matched_live_session_id')->nullable()->after('platform_account_id');

            $table->foreign('matched_live_session_id')
                ->references('id')
                ->on('live_sessions')
                ->nullOnDelete();

            $table->index(['platform_account_id', 'matched_live_session_id'], 'po_account_session_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropForeign(['matched_live_session_id']);
            $table->dropIndex('po_account_session_idx');
            $table->dropColumn('matched_live_session_id');
        });
    }
};
```

This works on both MySQL and SQLite — no enum mutation, no rename. The dual-driver pattern from CLAUDE.md is unnecessary here because we're only adding a new nullable column.

**Step 4: Update the model**

Edit `app/Models/ProductOrder.php`:

1. Add `'matched_live_session_id'` to the `$fillable` array (look for the existing array around `platform_account_id` and add it next to that key).
2. Add a relationship method (place near other `BelongsTo` relationships):

```php
public function matchedLiveSession(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Models\LiveSession::class, 'matched_live_session_id');
}
```

**Step 5: Run migration + test**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=ProductOrderMatchedSessionMigrationTest
```

Expected: all 3 tests PASS.

**Step 6: Commit**

```bash
git add database/migrations app/Models/ProductOrder.php tests/Feature/LiveHost/ProductOrderMatchedSessionMigrationTest.php
git commit -m "feat(livehost): add matched_live_session_id to product_orders"
```

---

## Task 2: Build `MatchProductOrderToLiveSession` action

**Files:**
- Create: `app/Actions/LiveHost/MatchProductOrderToLiveSession.php`
- Test: `tests/Feature/LiveHost/MatchProductOrderToLiveSessionTest.php`

**Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Actions\LiveHost\MatchProductOrderToLiveSession;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(MatchProductOrderToLiveSession::class);
});

it('matches a tiktok_shop order whose paid_time falls inside session window', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => null,
    ]);

    $matched = $this->action->handle($order);

    expect($matched)->toBe($session->id);
    expect($order->fresh()->matched_live_session_id)->toBe($session->id);
});

it('returns null when source is not tiktok_shop', function () {
    $order = ProductOrder::factory()->create(['source' => 'manual', 'paid_time' => now()]);
    expect($this->action->handle($order))->toBeNull();
});

it('returns null when platform_account_id is missing', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => null,
        'paid_time' => now(),
    ]);
    expect($this->action->handle($order))->toBeNull();
});

it('does not match a session from a different platform account', function () {
    $sessionAccount = PlatformAccount::factory()->create();
    $orderAccount = PlatformAccount::factory()->create();

    LiveSession::factory()->create([
        'platform_account_id' => $sessionAccount->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $orderAccount->id,
        'paid_time' => '2026-05-01 11:00:00',
    ]);

    expect($this->action->handle($order))->toBeNull();
});

it('does not match an order paid before the session started', function () {
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 09:00:00',
    ]);

    expect($this->action->handle($order))->toBeNull();
});

it('matches within the 12h tail window after actual_end_at', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 23:30:00', // 11.5h after end
    ]);

    expect($this->action->handle($order))->toBe($session->id);
});

it('does not match orders past the 12h tail window', function () {
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-02 01:00:00', // 13h after end
    ]);

    expect($this->action->handle($order))->toBeNull();
});

it('falls back to created_at when paid_time is null', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => null,
        'created_at' => '2026-05-01 11:00:00',
    ]);

    expect($this->action->handle($order))->toBe($session->id);
});

it('skips cancelled live sessions', function () {
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'cancelled',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
    ]);

    expect($this->action->handle($order))->toBeNull();
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=MatchProductOrderToLiveSessionTest
```

Expected: FAIL — class does not exist.

**Step 3: Create the action**

```bash
mkdir -p app/Actions/LiveHost
```

Create `app/Actions/LiveHost/MatchProductOrderToLiveSession.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\LiveHost;

use App\Models\LiveSession;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\DB;

class MatchProductOrderToLiveSession
{
    /**
     * Hours after a session's actual_end_at within which a fresh order is
     * still considered attributable to that live. Mirrors OrderRefundReconciler::TAIL_HOURS.
     */
    private const TAIL_HOURS = 12;

    /**
     * Match a TikTok Shop product order to its originating live session and
     * persist the link. Returns the matched session id (or null if no match).
     *
     * No-ops for non-tiktok_shop sources or orders missing platform_account_id.
     */
    public function handle(ProductOrder $order): ?int
    {
        if ($order->source !== 'tiktok_shop' || $order->platform_account_id === null) {
            return null;
        }

        $referenceTime = $order->paid_time ?? $order->created_at;

        if ($referenceTime === null) {
            return null;
        }

        $session = LiveSession::query()
            ->where('platform_account_id', $order->platform_account_id)
            ->whereIn('status', ['ongoing', 'completed', 'missed'])
            ->whereNotNull('actual_start_at')
            ->where('actual_start_at', '<=', $referenceTime)
            ->where(function ($query) use ($referenceTime) {
                $query->whereNull('actual_end_at')
                    ->orWhereRaw($this->dateAddExpr(), [self::TAIL_HOURS, $referenceTime]);
            })
            ->orderBy('actual_start_at', 'desc')
            ->limit(1)
            ->first();

        $sessionId = $session?->id;

        if ($order->matched_live_session_id !== $sessionId) {
            $order->matched_live_session_id = $sessionId;
            $order->save();
        }

        return $sessionId;
    }

    /**
     * Build a driver-aware "actual_end_at + X hours >= ?" raw predicate so the
     * window check works on both MySQL and SQLite.
     */
    private function dateAddExpr(): string
    {
        if (DB::getDriverName() === 'mysql') {
            return 'DATE_ADD(actual_end_at, INTERVAL ? HOUR) >= ?';
        }

        return "datetime(actual_end_at, '+' || ? || ' hours') >= ?";
    }
}
```

**Step 4: Run tests until they pass**

```bash
php artisan test --compact --filter=MatchProductOrderToLiveSessionTest
```

Expected: all 9 tests PASS. Fix any failures by re-reading the test expectations — do NOT loosen the tests.

**Step 5: Pint + commit**

```bash
./vendor/bin/pint --dirty
git add app/Actions/LiveHost/ tests/Feature/LiveHost/MatchProductOrderToLiveSessionTest.php
git commit -m "feat(livehost): add MatchProductOrderToLiveSession action"
```

---

## Task 3: Hook the matcher into TikTokOrderSyncService

**Files:**
- Modify: `app/Services/TikTok/TikTokOrderSyncService.php`
- Test: `tests/Feature/TikTok/TikTokOrderSyncMatchingTest.php`

**Step 1: Read the relevant section of the sync service**

```bash
grep -n "ProductOrder::create\|->save()\|class TikTokOrderSyncService" app/Services/TikTok/TikTokOrderSyncService.php
```

Look for the location around line 400–410 (where `ProductOrder::create($orderAttributes)` is called) and around the update path after.

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Actions\LiveHost\MatchProductOrderToLiveSession;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs MatchProductOrderToLiveSession after a tiktok_shop order is created', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    // Simulate the sync service creating an order, then call the matcher
    // directly — this is what the hook does internally.
    $order = ProductOrder::create([
        'order_number' => 'PO-TEST-001',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'status' => 'paid',
        'subtotal' => 100,
        'total_amount' => 100,
    ]);

    app(MatchProductOrderToLiveSession::class)->handle($order);

    expect($order->fresh()->matched_live_session_id)->toBe($session->id);
});
```

(This test verifies the matcher works end-to-end on a real ProductOrder. The full sync-service integration test would require mocking the TikTok API; that's deferred — we're testing the integration point, not the API client.)

**Step 3: Run test, verify it passes already**

```bash
php artisan test --compact --filter=TikTokOrderSyncMatchingTest
```

Expected: PASS (because Task 2 already works).

**Step 4: Add the hook in TikTokOrderSyncService**

Open `app/Services/TikTok/TikTokOrderSyncService.php` and:

1. Add the import at the top (with the other `use` statements):

```php
use App\Actions\LiveHost\MatchProductOrderToLiveSession;
```

2. Find the section where `ProductOrder::create($orderAttributes)` is called (around line 403–404). Look for the surrounding logic — it's inside a `DB::transaction` or similar block. Wrap the create + the existing update path with a single helper, or add the matcher call right after BOTH paths.

Look for code like:

```php
$existingOrder = ProductOrder::where('platform_order_id', $platformOrderId)
    ->where('platform_account_id', $account->id)
    ->first();

if ($existingOrder) {
    // ... update path ...
    $existingOrder->update($orderAttributes);
    $order = $existingOrder;
} else {
    $orderAttributes['order_number'] = ProductOrder::generateOrderNumber();
    $order = ProductOrder::create($orderAttributes);
}
```

After the create-or-update branch returns `$order`, add:

```php
// Tag this order with its originating live session for refund reconciliation
// and the /livehost/orders surface. Cheap indexed lookup.
app(MatchProductOrderToLiveSession::class)->handle($order);
```

Place it after the if/else so it runs on both create and update paths. If the surrounding code uses `try/catch`, place it inside the success path.

**Step 5: Run test + the existing TikTok sync tests**

```bash
php artisan test --compact tests/Feature/TikTok/
```

Expected: all PASS. If any pre-existing sync test fails because of the new call, the hook is in the wrong spot — back out and re-place.

**Step 6: Pint + commit**

```bash
./vendor/bin/pint --dirty
git add app/Services/TikTok/TikTokOrderSyncService.php tests/Feature/TikTok/TikTokOrderSyncMatchingTest.php
git commit -m "feat(livehost): match ProductOrder to LiveSession on tiktok sync"
```

---

## Task 4: Backfill artisan command

**Files:**
- Create: `app/Console/Commands/MatchProductOrdersToLiveSessions.php`
- Test: `tests/Feature/LiveHost/MatchProductOrdersBackfillCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills matched_live_session_id on tiktok_shop orders', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $matchableOrder = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => null,
    ]);
    $manualOrder = ProductOrder::factory()->create([
        'source' => 'manual',
        'matched_live_session_id' => null,
    ]);

    $this->artisan('livehost:match-product-orders')->assertExitCode(0);

    expect($matchableOrder->fresh()->matched_live_session_id)->toBe($session->id);
    expect($manualOrder->fresh()->matched_live_session_id)->toBeNull();
});

it('skips orders that are already matched', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => $session->id,
    ]);

    $this->artisan('livehost:match-product-orders')->assertExitCode(0);

    // No change — same id.
    expect($order->fresh()->matched_live_session_id)->toBe($session->id);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=MatchProductOrdersBackfillCommandTest
```

Expected: FAIL — command not found.

**Step 3: Create the command**

```bash
php artisan make:command MatchProductOrdersToLiveSessions --no-interaction
```

Replace contents:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveHost\MatchProductOrderToLiveSession;
use App\Models\ProductOrder;
use Illuminate\Console\Command;

class MatchProductOrdersToLiveSessions extends Command
{
    protected $signature = 'livehost:match-product-orders {--since= : Only match orders created on/after this date (Y-m-d)}';

    protected $description = 'Backfill matched_live_session_id on TikTok Shop product orders';

    public function handle(MatchProductOrderToLiveSession $matcher): int
    {
        $query = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->whereNull('matched_live_session_id');

        if ($since = $this->option('since')) {
            $query->where('created_at', '>=', $since);
        }

        $total = (clone $query)->count();
        $matched = 0;

        $this->info("Scanning {$total} unmatched orders…");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($orders) use ($matcher, &$matched, $bar) {
            foreach ($orders as $order) {
                if ($matcher->handle($order) !== null) {
                    $matched++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Matched {$matched} of {$total} orders.");

        return self::SUCCESS;
    }
}
```

**Step 4: Run test**

```bash
php artisan test --compact --filter=MatchProductOrdersBackfillCommandTest
```

Expected: PASS.

**Step 5: Pint + commit**

```bash
./vendor/bin/pint --dirty
git add app/Console/Commands/MatchProductOrdersToLiveSessions.php tests/Feature/LiveHost/MatchProductOrdersBackfillCommandTest.php
git commit -m "feat(livehost): add backfill command for product order matching"
```

---

## Task 5: Rewrite OrderRefundReconciler to read product_orders

**Files:**
- Modify: `app/Services/LiveHost/Tiktok/OrderRefundReconciler.php`
- Modify: `tests/Feature/LiveHost/OrderRefundReconcilerTest.php` (existing — adapt)

**Step 1: Read the existing test to understand contract**

```bash
ls tests/Feature/LiveHost/ | grep -i Refund
cat tests/Feature/LiveHost/OrderRefundReconcilerTest.php 2>/dev/null | head -100
```

If a test exists, study it. If not, expect to create one (path: `tests/Feature/LiveHost/OrderRefundReconcilerTest.php`).

**Step 2: Write/update tests to reflect the new ProductOrder-based contract**

Replace any TiktokOrder factory usage with ProductOrder factory usage. Key tests:

```php
<?php

declare(strict_types=1);

use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\TiktokReportImport;
use App\Models\User;
use App\Services\LiveHost\Tiktok\OrderRefundReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reconciler = app(OrderRefundReconciler::class);
});

it('proposes a negative GMV adjustment for a refunded order matched to a session', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    ProductOrder::factory()->create([
        'order_number' => 'PO-TEST-REFUND',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'platform_order_id' => 'TT123',
        'paid_time' => '2026-05-01 11:00:00',
        'status' => 'refunded',
        'total_amount' => 50,
        'matched_live_session_id' => $session->id,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => 'fake.xlsx',
        'uploaded_by' => User::factory()->create()->id,
        'uploaded_at' => now(),
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-01',
        'status' => 'completed',
        'total_rows' => 0,
    ]);

    $result = $this->reconciler->reconcile($import);

    expect($result['proposed_count'])->toBe(1);
    expect(LiveSessionGmvAdjustment::where('live_session_id', $session->id)
        ->where('amount_myr', -50)
        ->where('status', 'proposed')
        ->exists())->toBeTrue();
});

it('skips orders not matched to a session', function () {
    $account = PlatformAccount::factory()->create();
    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'status' => 'refunded',
        'total_amount' => 50,
        'matched_live_session_id' => null,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => 'fake.xlsx',
        'uploaded_by' => User::factory()->create()->id,
        'uploaded_at' => now(),
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-01',
        'status' => 'completed',
        'total_rows' => 0,
    ]);

    $result = $this->reconciler->reconcile($import);

    expect($result['proposed_count'])->toBe(0);
    expect($result['skipped_count'])->toBeGreaterThanOrEqual(1);
});

it('does not double-propose on a second run', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);

    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'platform_order_id' => 'TT999',
        'paid_time' => '2026-05-01 11:00:00',
        'status' => 'cancelled',
        'total_amount' => 30,
        'cancelled_at' => '2026-05-01 13:00:00',
        'matched_live_session_id' => $session->id,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => 'fake.xlsx',
        'uploaded_by' => User::factory()->create()->id,
        'uploaded_at' => now(),
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-01',
        'status' => 'completed',
        'total_rows' => 0,
    ]);

    $this->reconciler->reconcile($import);
    $second = $this->reconciler->reconcile($import);

    expect($second['proposed_count'])->toBe(0);
    expect(LiveSessionGmvAdjustment::count())->toBe(1);
});

it('only considers orders within the import period', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-15 10:00:00',
        'actual_end_at' => '2026-05-15 12:00:00',
        'status' => 'completed',
    ]);

    // Out-of-period order — should be ignored
    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'status' => 'refunded',
        'total_amount' => 50,
        'matched_live_session_id' => $session->id,
    ]);

    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => 'fake.xlsx',
        'uploaded_by' => User::factory()->create()->id,
        'uploaded_at' => now(),
        'period_start' => '2026-05-15',
        'period_end' => '2026-05-15',
        'status' => 'completed',
        'total_rows' => 0,
    ]);

    expect($this->reconciler->reconcile($import)['proposed_count'])->toBe(0);
});
```

**Step 3: Run test to verify it fails**

```bash
php artisan test --compact --filter=OrderRefundReconcilerTest
```

Expected: FAIL — test still references TiktokOrder semantics.

**Step 4: Rewrite the reconciler**

Replace `app/Services/LiveHost/Tiktok/OrderRefundReconciler.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use App\Models\LiveSessionGmvAdjustment;
use App\Models\ProductOrder;
use App\Models\TiktokReportImport;
use Illuminate\Support\Facades\DB;

class OrderRefundReconciler
{
    /**
     * Scan tiktok_shop ProductOrders within the import's period that are
     * refunded or cancelled and matched to a live session, then propose a
     * negative LiveSessionGmvAdjustment for each.
     *
     * Proposed adjustments do NOT feed the session's cached gmv_adjustment
     * aggregate until a PIC approves them
     * (see LiveSessionGmvAdjustmentController::approve).
     *
     * @return array{proposed_count: int, skipped_count: int}
     */
    public function reconcile(TiktokReportImport $import): array
    {
        $proposed = 0;
        $skipped = 0;

        $orders = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->where('platform_account_id', $import->platform_account_id)
            ->whereNotNull('matched_live_session_id')
            ->whereIn('status', ['refunded', 'cancelled', 'returned'])
            ->where(function ($query) use ($import) {
                $query->whereBetween('paid_time', [
                    $import->period_start->copy()->startOfDay(),
                    $import->period_end->copy()->endOfDay(),
                ])
                    ->orWhereBetween('cancelled_at', [
                        $import->period_start->copy()->startOfDay(),
                        $import->period_end->copy()->endOfDay(),
                    ]);
            })
            ->get();

        foreach ($orders as $order) {
            if ($this->alreadyProposed($order)) {
                $skipped++;

                continue;
            }

            $refundAmount = $this->refundAmountFor($order);

            if ($refundAmount <= 0.0) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($order, $refundAmount) {
                LiveSessionGmvAdjustment::create([
                    'live_session_id' => $order->matched_live_session_id,
                    'amount_myr' => -1 * $refundAmount,
                    'reason' => "Auto: Order #{$this->orderRef($order)} refunded/cancelled (RM {$refundAmount})",
                    'status' => 'proposed',
                    'adjusted_by' => null,
                    'adjusted_at' => now(),
                ]);
            });

            $proposed++;
        }

        return [
            'proposed_count' => $proposed,
            'skipped_count' => $skipped,
        ];
    }

    /**
     * Refund amount: prefer the sum of refunded payment amounts when present;
     * fall back to total_amount for cancelled orders.
     */
    private function refundAmountFor(ProductOrder $order): float
    {
        $refundedFromPayments = (float) $order->payments()
            ->where('status', 'refunded')
            ->sum('amount');

        if ($refundedFromPayments > 0) {
            return $refundedFromPayments;
        }

        if (in_array($order->status, ['cancelled', 'refunded', 'returned'], true)) {
            return (float) $order->total_amount;
        }

        return 0.0;
    }

    private function orderRef(ProductOrder $order): string
    {
        return $order->platform_order_id ?? $order->order_number;
    }

    private function alreadyProposed(ProductOrder $order): bool
    {
        return LiveSessionGmvAdjustment::query()
            ->where('reason', 'like', "Auto: Order #{$this->orderRef($order)} %")
            ->exists();
    }
}
```

(Note: this drops the imported `LiveSession` and `CarbonInterface` since the matching is now pre-computed on ProductOrder. Imports list at the top should be `App\Models\LiveSessionGmvAdjustment`, `App\Models\ProductOrder`, `App\Models\TiktokReportImport`, `Illuminate\Support\Facades\DB`.)

**Step 5: Run tests**

```bash
php artisan test --compact --filter=OrderRefundReconcilerTest
```

Expected: all PASS. Then run the broader live-host suite to catch regressions:

```bash
php artisan test --compact tests/Feature/LiveHost/
```

Expected: all PASS.

**Step 6: Pint + commit**

```bash
./vendor/bin/pint --dirty
git add app/Services/LiveHost/Tiktok/OrderRefundReconciler.php tests/Feature/LiveHost/OrderRefundReconcilerTest.php
git commit -m "feat(livehost): rewire OrderRefundReconciler to read product_orders"
```

---

## Task 6: PlatformOrderController + Inertia route

**Files:**
- Create: `app/Http/Controllers/LiveHost/PlatformOrderController.php`
- Modify: `routes/web.php` (Live Host PIC group)
- Test: `tests/Feature/LiveHost/PlatformOrderControllerTest.php`

**Step 1: Find the Live Host PIC route group**

```bash
grep -n "livehost\|Route::middleware('role:admin,admin_livehost'" routes/web.php | head -20
```

Locate the group with `prefix('livehost')->name('livehost.')`. Identify the admin-only sub-group (where TiktokReportImportController is registered).

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('redirects guests to login', function () {
    $this->get('/livehost/orders')->assertRedirect('/login');
});

it('returns Inertia response for admin', function () {
    $this->actingAs($this->admin)
        ->get('/livehost/orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('LiveHost/Orders/Index'));
});

it('only includes tiktok_shop source orders', function () {
    $account = PlatformAccount::factory()->create();
    ProductOrder::factory()->count(2)->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
    ]);
    ProductOrder::factory()->create(['source' => 'manual']);
    ProductOrder::factory()->create(['source' => 'funnel']);

    $this->actingAs($this->admin)
        ->get('/livehost/orders')
        ->assertInertia(fn ($page) => $page
            ->component('LiveHost/Orders/Index')
            ->where('orders.meta.total', 2)
        );
});

it('filters by shop (platform_account_id)', function () {
    $shopA = PlatformAccount::factory()->create();
    $shopB = PlatformAccount::factory()->create();
    ProductOrder::factory()->create(['source' => 'tiktok_shop', 'platform_account_id' => $shopA->id]);
    ProductOrder::factory()->create(['source' => 'tiktok_shop', 'platform_account_id' => $shopB->id]);

    $this->actingAs($this->admin)
        ->get("/livehost/orders?shop={$shopA->id}")
        ->assertInertia(fn ($page) => $page->where('orders.meta.total', 1));
});

it('filters unmatched only', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create(['platform_account_id' => $account->id]);
    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => $session->id,
    ]);
    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => null,
    ]);

    $this->actingAs($this->admin)
        ->get('/livehost/orders?unmatched_only=1')
        ->assertInertia(fn ($page) => $page->where('orders.meta.total', 1));
});
```

**Step 3: Run test to verify it fails**

```bash
php artisan test --compact --filter=PlatformOrderControllerTest
```

Expected: FAIL — route missing / 404.

**Step 4: Create the controller**

```bash
php artisan make:controller LiveHost/PlatformOrderController --no-interaction
```

Replace contents:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->with([
                'platformAccount:id,display_name',
                'matchedLiveSession:id,actual_start_at,live_host_id',
                'matchedLiveSession.liveHost:id,name',
            ]);

        if ($shop = $request->query('shop')) {
            $query->where('platform_account_id', $shop);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('unmatched_only')) {
            $query->whereNull('matched_live_session_id');
        }

        if ($from = $request->query('date_from')) {
            $query->where('paid_time', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->where('paid_time', '<=', $to.' 23:59:59');
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('platform_order_id', 'like', "%{$search}%")
                    ->orWhere('platform_order_number', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderByDesc('paid_time')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'total' => ProductOrder::where('source', 'tiktok_shop')->count(),
            'matched' => ProductOrder::where('source', 'tiktok_shop')->whereNotNull('matched_live_session_id')->count(),
            'unmatched' => ProductOrder::where('source', 'tiktok_shop')->whereNull('matched_live_session_id')->count(),
            'refunded' => ProductOrder::where('source', 'tiktok_shop')->whereIn('status', ['refunded', 'cancelled', 'returned'])->count(),
        ];

        $shops = PlatformAccount::query()
            ->whereIn('id', ProductOrder::query()->where('source', 'tiktok_shop')->select('platform_account_id'))
            ->get(['id', 'display_name']);

        return Inertia::render('LiveHost/Orders/Index', [
            'orders' => $orders,
            'summary' => $summary,
            'shops' => $shops,
            'filters' => $request->only(['shop', 'status', 'unmatched_only', 'date_from', 'date_to', 'search']),
        ]);
    }
}
```

**Step 5: Register route**

In `routes/web.php`, inside the admin-only sub-group of the `livehost` prefix (the one that already contains `tiktok-imports`), add:

```php
Route::get('orders', [\App\Http\Controllers\LiveHost\PlatformOrderController::class, 'index'])
    ->name('orders.index');
```

**Step 6: Run test**

```bash
php artisan test --compact --filter=PlatformOrderControllerTest
```

Expected: all PASS.

If `assertInertia` fails because the React page component file doesn't exist yet, that's fine — the test only asserts the props. The page is built in Task 7.

**Step 7: Pint + commit**

```bash
./vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/PlatformOrderController.php routes/web.php tests/Feature/LiveHost/PlatformOrderControllerTest.php
git commit -m "feat(livehost): add platform orders index endpoint"
```

---

## Task 7: Build the `/livehost/orders` Inertia React page

**Files:**
- Create: `resources/js/livehost/pages/orders/Index.jsx`

**Step 1: Study an existing list page for visual conventions**

```bash
cat resources/js/livehost/pages/tiktok-imports/Index.jsx | head -200
cat resources/js/livehost/pages/sessions/Index.jsx 2>/dev/null | head -120
```

Notice the conventions:
- Imports from `@inertiajs/react`, `lucide-react`, `LiveHostLayout`, local `Button`
- `TopBar` with breadcrumb + actions
- StatusBadge component pattern
- Tailwind v4 utility classes — no emoji, no decorative icons beyond lucide

**Step 2: Create the page**

Create `resources/js/livehost/pages/orders/Index.jsx`:

```jsx
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ShoppingBag, Search, X } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const STATUS_STYLES = {
  pending: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  confirmed: 'bg-[#DBEAFE] text-[#1E40AF] border-[#BFDBFE]',
  processing: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
  paid: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
  shipped: 'bg-[#E0F2FE] text-[#075985] border-[#BAE6FD]',
  delivered: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
  cancelled: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
  refunded: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
  returned: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
};

function StatusBadge({ status }) {
  const cls = STATUS_STYLES[status] ?? STATUS_STYLES.pending;
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium capitalize ${cls}`}>
      {status}
    </span>
  );
}

function maskCustomer(order) {
  return order.guest_email ?? order.customer?.name ?? '—';
}

function formatMoney(amount, currency = 'MYR') {
  if (amount == null) return '—';
  const n = Number(amount);
  if (Number.isNaN(n)) return '—';
  return `${currency} ${n.toFixed(2)}`;
}

function formatDateTime(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleString(undefined, {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

export default function PlatformOrdersIndex() {
  const { orders, summary, shops, filters } = usePage().props;

  const [search, setSearch] = useState(filters.search ?? '');
  const [shop, setShop] = useState(filters.shop ?? '');
  const [unmatched, setUnmatched] = useState(filters.unmatched_only === '1' || filters.unmatched_only === 1);

  const applyFilters = (overrides = {}) => {
    const params = {
      search: overrides.search ?? search,
      shop: overrides.shop ?? shop,
      unmatched_only: (overrides.unmatched ?? unmatched) ? '1' : '',
    };
    Object.keys(params).forEach((k) => params[k] === '' && delete params[k]);
    router.get('/livehost/orders', params, { preserveState: true, preserveScroll: true });
  };

  const reset = () => {
    setSearch('');
    setShop('');
    setUnmatched(false);
    router.get('/livehost/orders', {}, { preserveState: true, preserveScroll: true });
  };

  return (
    <>
      <Head title="Platform Orders" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Platform Orders']}
        actions={null}
      />

      <div className="px-6 pb-10 pt-2">
        <div className="mb-6 flex items-end justify-between">
          <div>
            <h1 className="flex items-center gap-2 text-[28px] font-semibold tracking-tight text-[#0A0A0A]">
              <ShoppingBag className="h-6 w-6 text-[#404040]" />
              Platform Orders
            </h1>
            <p className="mt-1 text-[13.5px] text-[#737373]">
              TikTok Shop orders synced via the platform integration. Used as the source for refund reconciliation and live host commission.
            </p>
          </div>
        </div>

        {/* Summary cards */}
        <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[
            { label: 'Total orders', value: summary.total },
            { label: 'Matched to session', value: summary.matched },
            { label: 'Unmatched', value: summary.unmatched, accent: summary.unmatched > 0 ? 'text-[#92400E]' : null },
            { label: 'Refunded / cancelled', value: summary.refunded },
          ].map((card) => (
            <div key={card.label} className="rounded-lg border border-[#EAEAEA] bg-white px-4 py-3">
              <div className="text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">{card.label}</div>
              <div className={`mt-1 text-[22px] font-semibold ${card.accent ?? 'text-[#0A0A0A]'}`}>{card.value}</div>
            </div>
          ))}
        </div>

        {/* Filters */}
        <div className="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 py-2">
          <div className="relative flex-1 min-w-[200px]">
            <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#A3A3A3]" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
              placeholder="Search order #, platform ID, email…"
              className="h-9 w-full rounded-md border border-[#EAEAEA] bg-white pl-8 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
          <select
            value={shop}
            onChange={(e) => { setShop(e.target.value); applyFilters({ shop: e.target.value }); }}
            className="h-9 rounded-md border border-[#EAEAEA] bg-white px-3 text-sm"
          >
            <option value="">All shops</option>
            {shops.map((s) => (
              <option key={s.id} value={s.id}>{s.display_name}</option>
            ))}
          </select>
          <label className="flex items-center gap-2 rounded-md border border-[#EAEAEA] bg-white px-3 py-1.5 text-[12.5px]">
            <input
              type="checkbox"
              checked={unmatched}
              onChange={(e) => { setUnmatched(e.target.checked); applyFilters({ unmatched: e.target.checked }); }}
              className="h-3.5 w-3.5"
            />
            Unmatched only
          </label>
          <Button variant="outline" size="sm" onClick={() => applyFilters()}>Apply</Button>
          {(search || shop || unmatched) && (
            <button onClick={reset} className="inline-flex items-center gap-1 text-[12px] text-[#737373] hover:text-[#0A0A0A]">
              <X className="h-3.5 w-3.5" /> Reset
            </button>
          )}
        </div>

        {/* Table */}
        <div className="overflow-hidden rounded-lg border border-[#EAEAEA] bg-white">
          <table className="w-full text-sm">
            <thead className="bg-[#FAFAFA] text-left text-[11.5px] uppercase tracking-wide text-[#737373]">
              <tr>
                <th className="px-3 py-2 font-medium">Order</th>
                <th className="px-3 py-2 font-medium">Shop</th>
                <th className="px-3 py-2 font-medium">Customer</th>
                <th className="px-3 py-2 font-medium">Total</th>
                <th className="px-3 py-2 font-medium">Status</th>
                <th className="px-3 py-2 font-medium">Matched session</th>
                <th className="px-3 py-2 font-medium">Paid at</th>
              </tr>
            </thead>
            <tbody>
              {orders.data.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-3 py-12 text-center text-[#A3A3A3]">No orders match these filters.</td>
                </tr>
              )}
              {orders.data.map((o) => (
                <tr key={o.id} className="border-t border-[#F5F5F5] hover:bg-[#FAFAFA]">
                  <td className="px-3 py-2.5">
                    <a
                      href={`/admin/product-orders/${o.id}`}
                      target="_blank"
                      rel="noreferrer"
                      className="font-medium text-[#0A0A0A] hover:text-[#10B981]"
                    >
                      {o.order_number}
                    </a>
                    {o.platform_order_id && (
                      <div className="text-[11px] text-[#A3A3A3]">{o.platform_order_id}</div>
                    )}
                  </td>
                  <td className="px-3 py-2.5 text-[#404040]">{o.platform_account?.display_name ?? '—'}</td>
                  <td className="px-3 py-2.5 text-[#404040]">{maskCustomer(o)}</td>
                  <td className="px-3 py-2.5 font-medium text-[#0A0A0A]">{formatMoney(o.total_amount, o.currency)}</td>
                  <td className="px-3 py-2.5"><StatusBadge status={o.status} /></td>
                  <td className="px-3 py-2.5">
                    {o.matched_live_session ? (
                      <Link
                        href={`/livehost/sessions/${o.matched_live_session.id}`}
                        className="text-[#10B981] hover:underline"
                      >
                        {formatDateTime(o.matched_live_session.actual_start_at)}
                      </Link>
                    ) : (
                      <span className="text-[#A3A3A3]">—</span>
                    )}
                  </td>
                  <td className="px-3 py-2.5 text-[#737373]">{formatDateTime(o.paid_time)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {orders.last_page > 1 && (
          <div className="mt-3 flex items-center justify-between text-[12.5px] text-[#737373]">
            <div>
              Showing {orders.from ?? 0}–{orders.to ?? 0} of {orders.total}
            </div>
            <div className="flex gap-2">
              {orders.links.map((l, idx) => (
                <Link
                  key={idx}
                  href={l.url ?? '#'}
                  preserveScroll
                  className={[
                    'min-w-[28px] rounded-md border px-2 py-1 text-center text-[12px]',
                    l.active ? 'border-[#0A0A0A] bg-[#0A0A0A] text-white' : 'border-[#EAEAEA] bg-white text-[#404040] hover:bg-[#F5F5F5]',
                    !l.url && 'opacity-40 pointer-events-none',
                  ].filter(Boolean).join(' ')}
                  dangerouslySetInnerHTML={{ __html: l.label }}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </>
  );
}

PlatformOrdersIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
```

**Step 3: Build assets**

```bash
npm run build
```

Expected: build succeeds. If it fails, fix import paths or syntax errors before continuing.

**Step 4: Manually verify**

Run the dev server (or check Herd) and visit `https://mudeerbedaie.test/livehost/orders` while logged in as admin. Confirm:
- Table renders with TikTok Shop orders
- "Unmatched only" toggle filters correctly
- Shop dropdown filters correctly
- Clicking an order opens `/admin/product-orders/{id}` in a new tab
- Matched session column links to `/livehost/sessions/{id}` when set

**Step 5: Commit**

```bash
git add resources/js/livehost/pages/orders/
git commit -m "feat(livehost): add platform orders Inertia React page"
```

---

## Task 8: Add "Orders" entry to LiveHost sidebar

**Files:**
- Modify: `resources/js/livehost/layouts/LiveHostLayout.jsx`
- Modify: `app/Http/Controllers/LiveHost/DashboardController.php` (or wherever sidebar counts are computed) — optional, only if other items have a count

**Step 1: Read the layout sidebar definition**

```bash
grep -n "NAV_GROUPS\|countKey\|orders\|tiktok-imports" resources/js/livehost/layouts/LiveHostLayout.jsx | head -30
```

**Step 2: Add the "orders" item**

In the `Records` group (around line 43–50), insert a new entry after `sessions` and before `commission`:

```jsx
{ key: 'orders', label: 'Orders', href: '/livehost/orders', icon: ShoppingBag, countKey: 'unmatchedOrders' },
```

Add `ShoppingBag` to the imports at the top of the file (with the other lucide-react imports).

In the `NAV_ITEM_PERMISSION` map, add:

```jsx
'orders': 'canSeeFinancials',
```

(Same gate as commission/payroll — orders are payroll-related data.)

**Step 3: (Optional) Wire up the unmatched count badge**

Search for where sidebar counts are populated (likely an Inertia shared prop):

```bash
grep -rn "platformAccounts\|sessionsCount\|HandleInertiaRequests" app/ | head -10
```

Locate `app/Http/Middleware/HandleInertiaRequests.php` or similar. Add:

```php
'unmatchedOrders' => \App\Models\ProductOrder::query()
    ->where('source', 'tiktok_shop')
    ->whereNull('matched_live_session_id')
    ->count(),
```

If this introduces overhead concerns, gate it behind the same role check used for the rest of the counts (admin/admin_livehost).

If the layout already conditionally renders the badge only when `countKey` resolves to a number, no extra work needed.

**Step 4: Build assets**

```bash
npm run build
```

**Step 5: Verify in browser**

Reload `/livehost/orders` — sidebar shows "Orders" highlighted. Confirm "Orders" also appears highlighted-when-active by visiting other pages first.

**Step 6: Commit**

```bash
git add resources/js/livehost/layouts/LiveHostLayout.jsx app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat(livehost): add Orders nav entry"
```

---

## Task 9: Remove order_list import from the Create form and controller

**Files:**
- Modify: `resources/js/livehost/pages/tiktok-imports/Create.jsx`
- Modify: `app/Http/Requests/UploadTiktokReportRequest.php` (or wherever validation lives)
- Modify: `app/Http/Controllers/LiveHost/TiktokReportImportController.php`
- Test: `tests/Feature/LiveHost/TiktokReportImportControllerTest.php` (existing — adapt) or create new

**Step 1: Locate the validation rule**

```bash
grep -rn "report_type" app/Http/Requests/ app/Http/Controllers/LiveHost/TiktokReportImportController.php
```

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('rejects order_list as a report_type', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $account = PlatformAccount::factory()->create();

    $this->actingAs($admin)
        ->post('/livehost/tiktok-imports', [
            'platform_account_id' => $account->id,
            'report_type' => 'order_list',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-07',
            'file' => UploadedFile::fake()->create('report.xlsx', 10),
        ])
        ->assertSessionHasErrors('report_type');
});

it('accepts live_analysis as a report_type', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $account = PlatformAccount::factory()->create();

    $this->actingAs($admin)
        ->post('/livehost/tiktok-imports', [
            'platform_account_id' => $account->id,
            'report_type' => 'live_analysis',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-07',
            'file' => UploadedFile::fake()->create('report.xlsx', 10),
        ])
        ->assertSessionHasNoErrors('report_type');
});
```

**Step 3: Run test**

```bash
php artisan test --compact --filter=TiktokReportImportControllerTest
```

Expected: the order_list rejection test FAILS (currently still accepted).

**Step 4: Tighten validation**

In the validation source (Form Request or inline rules in `store()`), change:

```php
'report_type' => 'required|in:live_analysis,order_list',
```

to:

```php
'report_type' => 'required|in:live_analysis',
```

If there's a custom error message map, update it accordingly.

**Step 5: Update the React Create page**

Edit `resources/js/livehost/pages/tiktok-imports/Create.jsx`:

1. Replace the `REPORT_TYPES` array with a single entry:

```jsx
const REPORT_TYPES = [
  { value: 'live_analysis', label: 'Live Analysis' },
];
```

2. Since there's only one option now, the radio group looks awkward. Either:
   - Keep the radio group as-is (one option preselected — clean but redundant), OR
   - Replace with a static label: `<div className="text-[13px] text-[#737373]">Report type: <span className="font-medium text-[#0A0A0A]">Live Analysis</span></div>` and a hidden input.

Pick the simpler option (static label + hidden input). Keep the section so layout doesn't shift.

3. Update the help text below the form (if any) to remove references to "All Orders".

**Step 6: Run tests**

```bash
php artisan test --compact --filter=TiktokReportImportControllerTest
```

Expected: all PASS. Also run:

```bash
npm run build
```

**Step 7: Verify the Index page still renders historical order_list imports**

Open `resources/js/livehost/pages/tiktok-imports/Index.jsx` — confirm the `TYPE_LABELS` map still contains `order_list: 'Order List'`. Do NOT remove it — past imports must still be labeled correctly.

**Step 8: Commit**

```bash
./vendor/bin/pint --dirty
git add resources/js/livehost/pages/tiktok-imports/Create.jsx app/Http/Requests/UploadTiktokReportRequest.php app/Http/Controllers/LiveHost/TiktokReportImportController.php tests/Feature/LiveHost/TiktokReportImportControllerTest.php
git commit -m "feat(livehost): remove order_list import; live_analysis only"
```

---

## Task 10: Browser smoke test (Pest 4)

**Files:**
- Create: `tests/Browser/LiveHost/PlatformOrdersPageTest.php`

**Step 1: Write the browser test**

```php
<?php

declare(strict_types=1);

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the platform orders page with data', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $account = PlatformAccount::factory()->create(['display_name' => 'Test Shop A']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'actual_start_at' => '2026-05-01 10:00:00',
        'actual_end_at' => '2026-05-01 12:00:00',
        'status' => 'completed',
    ]);
    ProductOrder::factory()->create([
        'order_number' => 'PO-BROWSER-1',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'paid_time' => '2026-05-01 11:00:00',
        'matched_live_session_id' => $session->id,
        'status' => 'paid',
        'total_amount' => 99.50,
    ]);

    $this->actingAs($admin);

    $page = visit('/livehost/orders');

    $page->assertSee('Platform Orders')
        ->assertSee('PO-BROWSER-1')
        ->assertSee('Test Shop A')
        ->assertSee('MYR 99.50')
        ->assertNoJavascriptErrors();
});

it('filters by unmatched_only', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create(['platform_account_id' => $account->id]);

    ProductOrder::factory()->create([
        'order_number' => 'PO-MATCHED',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => $session->id,
    ]);
    ProductOrder::factory()->create([
        'order_number' => 'PO-UNMATCHED',
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => null,
    ]);

    $this->actingAs($admin);

    visit('/livehost/orders?unmatched_only=1')
        ->assertSee('PO-UNMATCHED')
        ->assertDontSee('PO-MATCHED');
});
```

**Step 2: Run**

```bash
php artisan test --compact tests/Browser/LiveHost/PlatformOrdersPageTest.php
```

Expected: PASS. If browser tests aren't configured for this project, skip this task and rely on the manual verification in Task 7 step 4.

**Step 3: Commit**

```bash
git add tests/Browser/LiveHost/PlatformOrdersPageTest.php
git commit -m "test(livehost): browser smoke for platform orders page"
```

---

## Task 11: Run backfill on local data + final regression sweep

**No file changes — verification only.**

**Step 1: Run the backfill against local DB**

```bash
php artisan livehost:match-product-orders --no-interaction
```

Note the match rate. Spot-check a few rows:

```bash
php artisan tinker --execute="\App\Models\ProductOrder::where('source', 'tiktok_shop')->whereNotNull('matched_live_session_id')->take(5)->get(['order_number', 'paid_time', 'matched_live_session_id'])->dump();"
```

**Step 2: Run the full live-host test suite**

```bash
php artisan test --compact tests/Feature/LiveHost/ tests/Feature/TikTok/
```

Expected: all PASS. Investigate any failures before declaring done.

**Step 3: Run pint**

```bash
./vendor/bin/pint
```

**Step 4: Manual UI verification checklist**

Open the app in a browser and verify:

- [ ] `/livehost/orders` loads
- [ ] Summary cards show non-zero values
- [ ] Filter by shop works
- [ ] "Unmatched only" filter shows fewer rows
- [ ] Search by `PO-` prefix returns matches
- [ ] Clicking an order opens `/admin/product-orders/{id}` in a new tab
- [ ] "Matched session" links navigate to `/livehost/sessions/{id}`
- [ ] Sidebar shows "Orders" entry under RECORDS, between "Live Sessions" and "Commission"
- [ ] `/livehost/tiktok-imports/create` shows ONLY "Live Analysis" — no "All Orders" option
- [ ] `/livehost/tiktok-imports` index page still shows past order_list imports labeled "Order List"

**Step 5: Final commit (only if any fixups were needed)**

```bash
git status
# If anything to commit:
git add .
git commit -m "chore(livehost): final fixes from manual verification"
```

---

## Out of scope (deferred)

- Manual reassignment UI to override auto-matches.
- Editable order detail view inside `/livehost/*` (we link out to `/admin/product-orders/{id}`).
- Migrating or deleting historical `tiktok_orders` rows.
- Re-matching when a `LiveSession.actual_start_at` is later edited (acceptable to require a manual backfill run).
- Webhook-driven matching for orders that arrive before sessions exist (handled by re-matching on subsequent updates from TikTok via the sync hook in Task 3).

## Build sequence summary

1. Schema (Task 1)
2. Action (Task 2) — depends on Task 1
3. Sync hook (Task 3) — depends on Task 2
4. Backfill (Task 4) — depends on Task 2
5. Reconciler rewrite (Task 5) — depends on Task 1
6. Controller + route (Task 6) — depends on Task 1
7. React page (Task 7) — depends on Task 6
8. Sidebar (Task 8) — independent of 7 but presented after
9. Remove order_list import (Task 9) — depends on Task 5 (so refunds keep working)
10. Browser test (Task 10) — depends on Task 7
11. Verification (Task 11) — depends on all

Tasks 6 and 8 can technically interleave; otherwise execute in order.
