# Live Session Verification Link Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Let admin PIC explicitly link each scheduled `LiveSession` to a TikTok "actual" live record during verification, block verification until linked, and lock GMV from the linked record.

**Architecture:** New unified `actual_live_records` table (source: `csv_import` | `api_sync`). New `live_sessions.matched_actual_live_record_id` FK (unique). New `verify-link` controller action + tightened `verify` gate. Inertia modal shows same-host same-day candidate list with "Suggested" badge on closest time. New `live_session_verification_events` audit table. CSV import pipeline writes into the new table. Existing `LiveSessionMatcher` auto-matching is retired from the critical path.

**Tech Stack:** Laravel 12, Livewire Volt (for admin session view if touched), Inertia/React (live host PIC dashboard at `/livehost/*`), Pest 4, Flux UI, SQLite (dev) + MySQL (prod) — all migrations must work on both.

**Design doc:** [docs/plans/2026-04-24-live-session-verification-link-design.md](2026-04-24-live-session-verification-link-design.md)

---

## Phase 1 — Database foundation

### Task 1: Create `actual_live_records` table

**Files:**
- Create: `database/migrations/2026_04_24_000001_create_actual_live_records_table.php`
- Test: `tests/Feature/LiveHost/ActualLiveRecord/MigrationTest.php`

**Step 1: Write the failing test**

```php
// tests/Feature/LiveHost/ActualLiveRecord/MigrationTest.php
<?php

use Illuminate\Support\Facades\Schema;

it('creates actual_live_records table with expected columns', function () {
    expect(Schema::hasTable('actual_live_records'))->toBeTrue();

    $expected = [
        'id', 'platform_account_id', 'source', 'source_record_id',
        'import_id', 'creator_platform_user_id', 'creator_handle',
        'launched_time', 'ended_time', 'duration_seconds',
        'gmv_myr', 'live_attributed_gmv_myr',
        'viewers', 'views', 'comments', 'shares', 'likes', 'new_followers',
        'products_added', 'products_sold', 'items_sold', 'sku_orders',
        'unique_customers', 'avg_price_myr', 'click_to_order_rate', 'ctr',
        'raw_json', 'created_at', 'updated_at',
    ];

    foreach ($expected as $col) {
        expect(Schema::hasColumn('actual_live_records', $col))
            ->toBeTrue("missing column: {$col}");
    }
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=MigrationTest
```
Expected: FAIL (`actual_live_records` table doesn't exist yet).

**Step 3: Write the migration**

```php
// database/migrations/2026_04_24_000001_create_actual_live_records_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actual_live_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')
                ->constrained('platform_accounts')
                ->cascadeOnDelete();

            $table->enum('source', ['csv_import', 'api_sync']);
            $table->string('source_record_id')->nullable();

            $table->foreignId('import_id')
                ->nullable()
                ->constrained('tiktok_report_imports')
                ->nullOnDelete();

            $table->string('creator_platform_user_id')->nullable();
            $table->string('creator_handle')->nullable();

            $table->timestamp('launched_time');
            $table->timestamp('ended_time')->nullable();
            $table->integer('duration_seconds')->nullable();

            $table->decimal('gmv_myr', 15, 2)->default(0);
            $table->decimal('live_attributed_gmv_myr', 15, 2)->default(0);

            $table->unsignedInteger('viewers')->nullable();
            $table->unsignedInteger('views')->nullable();
            $table->unsignedInteger('comments')->nullable();
            $table->unsignedInteger('shares')->nullable();
            $table->unsignedInteger('likes')->nullable();
            $table->unsignedInteger('new_followers')->nullable();

            $table->unsignedInteger('products_added')->nullable();
            $table->unsignedInteger('products_sold')->nullable();
            $table->unsignedInteger('items_sold')->nullable();
            $table->unsignedInteger('sku_orders')->nullable();

            $table->unsignedInteger('unique_customers')->nullable();
            $table->decimal('avg_price_myr', 15, 2)->nullable();
            $table->decimal('click_to_order_rate', 8, 4)->nullable();
            $table->decimal('ctr', 8, 4)->nullable();

            $table->json('raw_json')->nullable();

            $table->timestamps();

            $table->index(
                ['platform_account_id', 'creator_platform_user_id', 'launched_time'],
                'alr_candidate_idx'
            );
            $table->unique(['source', 'source_record_id'], 'alr_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actual_live_records');
    }
};
```

**Step 4: Run test to verify it passes**

```bash
php artisan migrate && php artisan test --compact --filter=MigrationTest
```
Expected: PASS.

**Step 5: Commit**

```bash
git add database/migrations/2026_04_24_000001_create_actual_live_records_table.php tests/Feature/LiveHost/ActualLiveRecord/MigrationTest.php
git commit -m "feat(livehost): create actual_live_records table for unified live data"
```

---

### Task 2: Add `matched_actual_live_record_id` to `live_sessions`

**Files:**
- Create: `database/migrations/2026_04_24_000002_add_matched_actual_live_record_id_to_live_sessions_table.php`
- Modify test: `tests/Feature/LiveHost/ActualLiveRecord/MigrationTest.php` (add assertion)

**Step 1: Add assertion to existing migration test**

Add to the same test file:
```php
it('adds matched_actual_live_record_id column to live_sessions', function () {
    expect(Schema::hasColumn('live_sessions', 'matched_actual_live_record_id'))->toBeTrue();
});
```

**Step 2: Run, verify fail, write migration**

```bash
php artisan test --compact --filter=MigrationTest
```
Expected: second it() FAILs.

```php
// database/migrations/2026_04_24_000002_add_matched_actual_live_record_id_to_live_sessions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->foreignId('matched_actual_live_record_id')
                ->nullable()
                ->unique()
                ->after('live_host_platform_account_id')
                ->constrained('actual_live_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropForeign(['matched_actual_live_record_id']);
            $table->dropUnique(['matched_actual_live_record_id']);
            $table->dropColumn('matched_actual_live_record_id');
        });
    }
};
```

**Step 3: Migrate + test**

```bash
php artisan migrate && php artisan test --compact --filter=MigrationTest
```
Expected: PASS.

**Step 4: Commit**

```bash
git add database/migrations/2026_04_24_000002_add_matched_actual_live_record_id_to_live_sessions_table.php tests/Feature/LiveHost/ActualLiveRecord/MigrationTest.php
git commit -m "feat(livehost): add matched_actual_live_record_id to live_sessions"
```

---

### Task 3: Create `live_session_verification_events` audit table

**Files:**
- Create: `database/migrations/2026_04_24_000003_create_live_session_verification_events_table.php`
- Update test: `tests/Feature/LiveHost/ActualLiveRecord/MigrationTest.php`

**Step 1: Add assertion**

```php
it('creates live_session_verification_events audit table', function () {
    expect(Schema::hasTable('live_session_verification_events'))->toBeTrue();
    foreach (['id','live_session_id','actual_live_record_id','action','user_id','gmv_snapshot','notes','created_at'] as $col) {
        expect(Schema::hasColumn('live_session_verification_events', $col))->toBeTrue();
    }
});
```

**Step 2: Migration**

```php
// database/migrations/2026_04_24_000003_create_live_session_verification_events_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_session_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignId('actual_live_record_id')->nullable()->constrained('actual_live_records')->nullOnDelete();
            $table->enum('action', ['verify_link', 'unverify', 'reject', 'link_changed']);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('gmv_snapshot', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            // No updated_at — append-only.

            $table->index(['live_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_verification_events');
    }
};
```

**Step 3: Migrate + test + commit**

```bash
php artisan migrate && php artisan test --compact --filter=MigrationTest
git add database/migrations/2026_04_24_000003_create_live_session_verification_events_table.php tests/Feature/LiveHost/ActualLiveRecord/MigrationTest.php
git commit -m "feat(livehost): create live_session_verification_events audit table"
```

---

## Phase 2 — Models

### Task 4: `ActualLiveRecord` model

**Files:**
- Create: `app/Models/ActualLiveRecord.php`
- Create: `database/factories/ActualLiveRecordFactory.php`
- Test: `tests/Feature/LiveHost/ActualLiveRecord/ModelTest.php`

**Step 1: Test**

```php
// tests/Feature/LiveHost/ActualLiveRecord/ModelTest.php
<?php

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('casts launched_time to carbon and raw_json to array', function () {
    $record = ActualLiveRecord::factory()->create([
        'raw_json' => ['foo' => 'bar'],
    ]);

    expect($record->launched_time)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($record->raw_json)->toBe(['foo' => 'bar']);
});

it('belongs to a platform account', function () {
    $account = PlatformAccount::factory()->create();
    $record = ActualLiveRecord::factory()->create(['platform_account_id' => $account->id]);

    expect($record->platformAccount->id)->toBe($account->id);
});
```

**Step 2: Factory**

```php
// database/factories/ActualLiveRecordFactory.php
<?php

namespace Database\Factories;

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActualLiveRecordFactory extends Factory
{
    protected $model = ActualLiveRecord::class;

    public function definition(): array
    {
        return [
            'platform_account_id' => PlatformAccount::factory(),
            'source' => 'csv_import',
            'source_record_id' => null,
            'creator_platform_user_id' => (string) fake()->numerify('##############'),
            'creator_handle' => fake()->userName(),
            'launched_time' => now()->subHours(fake()->numberBetween(1, 48)),
            'duration_seconds' => fake()->numberBetween(600, 7200),
            'gmv_myr' => fake()->randomFloat(2, 100, 5000),
            'live_attributed_gmv_myr' => fake()->randomFloat(2, 50, 4000),
            'viewers' => fake()->numberBetween(10, 5000),
            'raw_json' => [],
        ];
    }

    public function apiSync(): self
    {
        return $this->state([
            'source' => 'api_sync',
            'source_record_id' => (string) fake()->numerify('################'),
        ]);
    }
}
```

**Step 3: Model**

```php
// app/Models/ActualLiveRecord.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActualLiveRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_account_id', 'source', 'source_record_id', 'import_id',
        'creator_platform_user_id', 'creator_handle',
        'launched_time', 'ended_time', 'duration_seconds',
        'gmv_myr', 'live_attributed_gmv_myr',
        'viewers', 'views', 'comments', 'shares', 'likes', 'new_followers',
        'products_added', 'products_sold', 'items_sold', 'sku_orders',
        'unique_customers', 'avg_price_myr', 'click_to_order_rate', 'ctr',
        'raw_json',
    ];

    protected function casts(): array
    {
        return [
            'launched_time' => 'datetime',
            'ended_time' => 'datetime',
            'gmv_myr' => 'decimal:2',
            'live_attributed_gmv_myr' => 'decimal:2',
            'avg_price_myr' => 'decimal:2',
            'click_to_order_rate' => 'decimal:4',
            'ctr' => 'decimal:4',
            'raw_json' => 'array',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function matchedLiveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class, 'matched_actual_live_record_id');
    }
}
```

**Step 4: Run + commit**

```bash
php artisan test --compact --filter=ActualLiveRecord/ModelTest
git add app/Models/ActualLiveRecord.php database/factories/ActualLiveRecordFactory.php tests/Feature/LiveHost/ActualLiveRecord/ModelTest.php
git commit -m "feat(livehost): add ActualLiveRecord model and factory"
```

---

### Task 5: Update `LiveSession` model — relation + fillable

**Files:**
- Modify: `app/Models/LiveSession.php`
- Test: `tests/Feature/LiveHost/LiveSession/MatchedRecordRelationTest.php`

**Step 1: Test**

```php
// tests/Feature/LiveHost/LiveSession/MatchedRecordRelationTest.php
<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('belongs to a matched actual live record', function () {
    $record = ActualLiveRecord::factory()->create();
    $session = LiveSession::factory()->create([
        'matched_actual_live_record_id' => $record->id,
    ]);

    expect($session->matchedActualLiveRecord->id)->toBe($record->id);
});
```

**Step 2: Update LiveSession model**

Add to `$fillable`: `'matched_actual_live_record_id'`

Add relation method:
```php
public function matchedActualLiveRecord(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(ActualLiveRecord::class, 'matched_actual_live_record_id');
}

public function verificationEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(LiveSessionVerificationEvent::class);
}
```

**Step 3: Run + commit**

```bash
php artisan test --compact --filter=LiveSession/MatchedRecordRelationTest
git add app/Models/LiveSession.php tests/Feature/LiveHost/LiveSession/MatchedRecordRelationTest.php
git commit -m "feat(livehost): LiveSession.matchedActualLiveRecord relation"
```

---

### Task 6: `LiveSessionVerificationEvent` model

**Files:**
- Create: `app/Models/LiveSessionVerificationEvent.php`
- Create: `database/factories/LiveSessionVerificationEventFactory.php`
- Test: `tests/Feature/LiveHost/VerificationEvent/ModelTest.php`

**Step 1: Test**

```php
it('persists a verification event with user, session, and action', function () {
    $session = LiveSession::factory()->create();
    $user = User::factory()->create(['role' => 'admin_livehost']);
    $record = ActualLiveRecord::factory()->create();

    $event = LiveSessionVerificationEvent::create([
        'live_session_id' => $session->id,
        'actual_live_record_id' => $record->id,
        'action' => 'verify_link',
        'user_id' => $user->id,
        'gmv_snapshot' => 1234.56,
    ]);

    expect($event->action)->toBe('verify_link')
        ->and($event->gmv_snapshot)->toBe('1234.56')
        ->and($event->liveSession->id)->toBe($session->id)
        ->and($event->user->id)->toBe($user->id)
        ->and($event->actualLiveRecord->id)->toBe($record->id);
});
```

**Step 2: Model + factory**

```php
// app/Models/LiveSessionVerificationEvent.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionVerificationEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'live_session_id', 'actual_live_record_id', 'action',
        'user_id', 'gmv_snapshot', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'gmv_snapshot' => 'decimal:2',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function actualLiveRecord(): BelongsTo
    {
        return $this->belongsTo(ActualLiveRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Factory similarly minimal.

**Step 3: Run + commit**

```bash
php artisan test --compact --filter=VerificationEvent/ModelTest
git add app/Models/LiveSessionVerificationEvent.php database/factories/LiveSessionVerificationEventFactory.php tests/Feature/LiveHost/VerificationEvent/ModelTest.php
git commit -m "feat(livehost): add LiveSessionVerificationEvent append-only audit model"
```

---

## Phase 3 — Data migration: existing 134 rows

### Task 7: Backfill `actual_live_records` from `tiktok_live_reports`

**Files:**
- Create: `database/migrations/2026_04_24_000004_backfill_actual_live_records_from_tiktok_live_reports.php`
- Test: `tests/Feature/LiveHost/ActualLiveRecord/BackfillTest.php`

**Step 1: Test**

```php
// tests/Feature/LiveHost/ActualLiveRecord/BackfillTest.php
<?php

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Models\TiktokReportImport;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('copies each tiktok_live_report row into actual_live_records on migrate', function () {
    $account = PlatformAccount::factory()->create();
    $import = TiktokReportImport::factory()->create([
        'platform_account_id' => $account->id,
        'report_type' => 'live_analysis',
    ]);

    // Directly seed a row representing pre-migration state.
    TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '123456789',
        'creator_nickname' => 'host_a',
        'launched_time' => now()->subHour(),
        'duration_seconds' => 3600,
        'gmv_myr' => 1500.00,
        'live_attributed_gmv_myr' => 1200.00,
        'viewers' => 250,
        'raw_row_json' => ['source' => 'test'],
    ]);

    // Re-run the backfill (safe: it's idempotent in the migration)
    Artisan::call('migrate:refresh', ['--step' => 1]);

    expect(ActualLiveRecord::count())->toBe(TiktokLiveReport::count());
});
```

**Step 2: Migration — data copy**

```php
<?php

use App\Models\ActualLiveRecord;
use App\Models\TiktokLiveReport;
use App\Models\TiktokReportImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: skip if already backfilled.
        if (ActualLiveRecord::where('source', 'csv_import')->exists()) {
            return;
        }

        TiktokLiveReport::query()
            ->with('import:id,platform_account_id')
            ->chunkById(500, function ($reports) {
                $rows = $reports->map(function (TiktokLiveReport $r) {
                    return [
                        'platform_account_id' => $r->import?->platform_account_id,
                        'source' => 'csv_import',
                        'source_record_id' => null,
                        'import_id' => $r->import_id,
                        'creator_platform_user_id' => $r->tiktok_creator_id,
                        'creator_handle' => $r->creator_nickname ?? $r->creator_display_name,
                        'launched_time' => $r->launched_time,
                        'duration_seconds' => $r->duration_seconds,
                        'gmv_myr' => $r->gmv_myr ?? 0,
                        'live_attributed_gmv_myr' => $r->live_attributed_gmv_myr ?? 0,
                        'viewers' => $r->viewers,
                        'views' => $r->views,
                        'comments' => $r->comments,
                        'shares' => $r->shares,
                        'likes' => $r->likes,
                        'new_followers' => $r->new_followers,
                        'products_added' => $r->products_added,
                        'products_sold' => $r->products_sold,
                        'items_sold' => $r->items_sold,
                        'sku_orders' => $r->sku_orders,
                        'unique_customers' => $r->unique_customers,
                        'avg_price_myr' => $r->avg_price_myr,
                        'click_to_order_rate' => $r->click_to_order_rate,
                        'ctr' => $r->ctr,
                        'raw_json' => json_encode($r->raw_row_json ?? []),
                        'created_at' => $r->created_at,
                        'updated_at' => $r->updated_at,
                    ];
                })->filter(fn ($row) => $row['platform_account_id'] !== null && $row['launched_time'] !== null)
                  ->values()
                  ->all();

                if (! empty($rows)) {
                    DB::table('actual_live_records')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // Reversing deletes only the csv_import rows that came from this backfill.
        DB::table('actual_live_records')->where('source', 'csv_import')->delete();
    }
};
```

**Step 3: Migrate on dev + verify count**

```bash
php artisan migrate
php artisan tinker --execute="echo 'reports='.App\Models\TiktokLiveReport::count().' records='.App\Models\ActualLiveRecord::where('source','csv_import')->count();"
```
Expected: reports and records counts match (134 on the dev DB).

**Step 4: Commit**

```bash
git add database/migrations/2026_04_24_000004_backfill_actual_live_records_from_tiktok_live_reports.php tests/Feature/LiveHost/ActualLiveRecord/BackfillTest.php
git commit -m "feat(livehost): backfill actual_live_records from tiktok_live_reports"
```

---

## Phase 4 — Candidate search service

### Task 8: `ActualLiveRecordCandidateFinder` service

**Files:**
- Create: `app/Services/LiveHost/ActualLiveRecordCandidateFinder.php`
- Test: `tests/Feature/LiveHost/ActualLiveRecord/CandidateFinderTest.php`

**Step 1: Test**

```php
// tests/Feature/LiveHost/ActualLiveRecord/CandidateFinderTest.php
<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Services\LiveHost\ActualLiveRecordCandidateFinder;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Fix "today" for deterministic KL-day boundary tests
    Carbon::setTestNow('2026-04-24 12:00:00');  // UTC noon → KL 8pm
});

it('returns same-host same-day records ordered by time proximity', function () {
    $pivot = LiveHostPlatformAccount::factory()->create([
        'creator_platform_user_id' => 'creator_x',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $near = ActualLiveRecord::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'creator_platform_user_id' => 'creator_x',
        'launched_time' => Carbon::parse('2026-04-24 14:15:00', 'Asia/Kuala_Lumpur'),
    ]);
    $far = ActualLiveRecord::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'creator_platform_user_id' => 'creator_x',
        'launched_time' => Carbon::parse('2026-04-24 22:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);

    expect($results->pluck('id')->all())->toBe([$near->id, $far->id]);
});

it('excludes records already linked to another session', function () {
    $pivot = LiveHostPlatformAccount::factory()->create(['creator_platform_user_id' => 'host_y']);
    $sessionA = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);
    $sessionB = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 15:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $linkedElsewhere = ActualLiveRecord::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'creator_platform_user_id' => 'host_y',
        'launched_time' => Carbon::parse('2026-04-24 14:30:00', 'Asia/Kuala_Lumpur'),
    ]);
    $sessionA->update(['matched_actual_live_record_id' => $linkedElsewhere->id]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($sessionB);
    expect($results->pluck('id')->all())->not->toContain($linkedElsewhere->id);
});

it('returns empty when host has no creator_platform_user_id', function () {
    $pivot = LiveHostPlatformAccount::factory()->create(['creator_platform_user_id' => null]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => now(),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);
    expect($results)->toBeEmpty();
});

it('respects asia kuala lumpur timezone for day boundary', function () {
    $pivot = LiveHostPlatformAccount::factory()->create(['creator_platform_user_id' => 'host_tz']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        // Scheduled at 2026-04-24 KL time (UTC: 2026-04-23 23:00)
        'scheduled_start_at' => Carbon::parse('2026-04-24 07:00', 'Asia/Kuala_Lumpur'),
    ]);

    // Record launched at 2026-04-24 KL time but 2026-04-23 UTC
    $sameDayKl = ActualLiveRecord::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'creator_platform_user_id' => 'host_tz',
        'launched_time' => Carbon::parse('2026-04-24 00:30', 'Asia/Kuala_Lumpur'),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);
    expect($results->pluck('id')->all())->toContain($sameDayKl->id);
});
```

**Step 2: Service**

```php
// app/Services/LiveHost/ActualLiveRecordCandidateFinder.php
<?php

declare(strict_types=1);

namespace App\Services\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;
use Illuminate\Support\Collection;

class ActualLiveRecordCandidateFinder
{
    private const TIMEZONE = 'Asia/Kuala_Lumpur';
    private const MAX_CANDIDATES = 20;

    /**
     * Return candidate ActualLiveRecord rows for the given LiveSession, filtered to
     * same platform account + same creator + same calendar day (KL timezone),
     * excluding records already linked to other sessions, sorted by time
     * proximity to the session's scheduled_start_at.
     */
    public function forSession(LiveSession $session): Collection
    {
        $pivot = $session->liveHostPlatformAccount;
        $creatorId = $pivot?->creator_platform_user_id;

        if ($creatorId === null || $session->scheduled_start_at === null) {
            return collect();
        }

        $scheduledKl = $session->scheduled_start_at->copy()->setTimezone(self::TIMEZONE);
        $dayStartUtc = $scheduledKl->copy()->startOfDay()->utc();
        $dayEndUtc = $scheduledKl->copy()->endOfDay()->utc();

        return ActualLiveRecord::query()
            ->where('platform_account_id', $session->platform_account_id)
            ->where('creator_platform_user_id', $creatorId)
            ->whereBetween('launched_time', [$dayStartUtc, $dayEndUtc])
            ->whereNotIn('id', function ($q) use ($session) {
                $q->select('matched_actual_live_record_id')
                  ->from('live_sessions')
                  ->whereNotNull('matched_actual_live_record_id')
                  ->where('id', '!=', $session->id);
            })
            ->get()
            ->sortBy(fn (ActualLiveRecord $r) => abs(
                $r->launched_time->diffInSeconds($session->scheduled_start_at, true)
            ))
            ->take(self::MAX_CANDIDATES)
            ->values();
    }
}
```

**Step 3: Run + commit**

```bash
php artisan test --compact --filter=CandidateFinderTest
git add app/Services/LiveHost/ActualLiveRecordCandidateFinder.php tests/Feature/LiveHost/ActualLiveRecord/CandidateFinderTest.php
git commit -m "feat(livehost): add ActualLiveRecordCandidateFinder service"
```

---

## Phase 5 — verify-link endpoint

### Task 9: `VerifyLinkLiveSessionRequest` validator

**Files:**
- Create: `app/Http/Requests/LiveHost/VerifyLinkLiveSessionRequest.php`

**Step 1: Request class**

```php
<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLinkLiveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'actual_live_record_id' => ['required', 'integer', 'exists:actual_live_records,id'],
        ];
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Requests/LiveHost/VerifyLinkLiveSessionRequest.php
git commit -m "feat(livehost): VerifyLinkLiveSessionRequest validator"
```

---

### Task 10: `SessionController::verifyLink` action + route

**Files:**
- Modify: `app/Http/Controllers/LiveHost/SessionController.php`
- Modify: `routes/web.php` (add route)
- Test: `tests/Feature/LiveHost/VerifyLink/VerifyLinkTest.php`

**Step 1: Feature tests**

```php
// tests/Feature/LiveHost/VerifyLink/VerifyLinkTest.php
<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAdmin(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

function makeSessionWithCandidate(): array
{
    $pivot = LiveHostPlatformAccount::factory()->create(['creator_platform_user_id' => 'c1']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'pending',
        'scheduled_start_at' => now(),
    ]);
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'creator_platform_user_id' => 'c1',
        'launched_time' => now(),
        'live_attributed_gmv_myr' => 987.65,
    ]);
    return [$session, $record];
}

it('links record and flips session to verified atomically', function () {
    [$session, $record] = makeSessionWithCandidate();

    $response = $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => $record->id,
        ]);

    $response->assertRedirect();

    $session->refresh();
    expect($session->verification_status)->toBe('verified')
        ->and($session->matched_actual_live_record_id)->toBe($record->id)
        ->and((float) $session->gmv_amount)->toBe(987.65)
        ->and($session->gmv_source)->toBe('tiktok_actual')
        ->and($session->gmv_locked_at)->not->toBeNull()
        ->and($session->verified_by)->not->toBeNull();
});

it('writes a verify_link audit event', function () {
    [$session, $record] = makeSessionWithCandidate();

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => $record->id,
        ]);

    $event = LiveSessionVerificationEvent::where('live_session_id', $session->id)->first();
    expect($event->action)->toBe('verify_link')
        ->and($event->actual_live_record_id)->toBe($record->id)
        ->and((float) $event->gmv_snapshot)->toBe(987.65);
});

it('rejects when session not pending', function () {
    [$session, $record] = makeSessionWithCandidate();
    $session->update(['verification_status' => 'verified']);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => $record->id,
        ])
        ->assertSessionHasErrors();
});

it('rejects when record belongs to different platform account', function () {
    [$session, $record] = makeSessionWithCandidate();
    $record->update(['platform_account_id' => \App\Models\PlatformAccount::factory()->create()->id]);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => $record->id,
        ])
        ->assertSessionHasErrors();
});

it('returns 409 when record already linked to another session', function () {
    [$sessionA, $record] = makeSessionWithCandidate();
    $pivot = $sessionA->liveHostPlatformAccount;
    $sessionB = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'pending',
        'matched_actual_live_record_id' => $record->id,
    ]);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$sessionA->id}/verify-link", [
            'actual_live_record_id' => $record->id,
        ])
        ->assertStatus(409);
});
```

**Step 2: Controller method**

Add to `SessionController`:

```php
use App\Http\Requests\LiveHost\VerifyLinkLiveSessionRequest;
use App\Models\ActualLiveRecord;
use App\Models\LiveSessionVerificationEvent;
use Illuminate\Support\Facades\DB;

public function verifyLink(VerifyLinkLiveSessionRequest $request, LiveSession $session): RedirectResponse
{
    if ($session->verification_status !== 'pending') {
        return back()->withErrors([
            'verification' => 'Session is not pending verification.',
        ]);
    }

    $record = ActualLiveRecord::findOrFail($request->integer('actual_live_record_id'));

    if ($record->platform_account_id !== $session->platform_account_id) {
        return back()->withErrors([
            'actual_live_record_id' => 'Record does not belong to this platform account.',
        ]);
    }

    $alreadyLinked = LiveSession::where('matched_actual_live_record_id', $record->id)
        ->where('id', '!=', $session->id)
        ->exists();

    if ($alreadyLinked) {
        return back()->withErrors([
            'actual_live_record_id' => 'This record is already linked to another session.',
        ], 'verification')->setStatusCode(409);
    }

    try {
        DB::transaction(function () use ($session, $record, $request) {
            $session->update([
                'matched_actual_live_record_id' => $record->id,
                'gmv_amount' => $record->live_attributed_gmv_myr,
                'gmv_source' => 'tiktok_actual',
                'gmv_locked_at' => now(),
                'verification_status' => 'verified',
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);

            LiveSessionVerificationEvent::create([
                'live_session_id' => $session->id,
                'actual_live_record_id' => $record->id,
                'action' => 'verify_link',
                'user_id' => $request->user()->id,
                'gmv_snapshot' => $record->live_attributed_gmv_myr,
            ]);
        });
    } catch (\Illuminate\Database\QueryException $e) {
        // Unique-index safety net
        return back()->withErrors([
            'actual_live_record_id' => 'Record was just linked elsewhere — refresh and retry.',
        ])->setStatusCode(409);
    }

    return back()->with('success', 'Session verified with linked TikTok record.');
}
```

**Step 3: Route**

Add to `routes/web.php` inside the live host group (near line 304):
```php
Route::post('sessions/{session}/verify-link', [\App\Http\Controllers\LiveHost\SessionController::class, 'verifyLink'])
    ->name('sessions.verify-link');
```

**Step 4: Run + commit**

```bash
php artisan test --compact --filter=VerifyLink/VerifyLinkTest
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/SessionController.php routes/web.php tests/Feature/LiveHost/VerifyLink/VerifyLinkTest.php
git commit -m "feat(livehost): verify-link endpoint locks session to actual record"
```

---

### Task 11: Tighten existing `verify` endpoint — block verified without link

**Files:**
- Modify: `app/Http/Controllers/LiveHost/SessionController.php` (method `verify`)
- Modify: `app/Http/Requests/LiveHost/VerifyLiveSessionRequest.php`
- Test: `tests/Feature/LiveHost/VerifyLink/VerifyGateTest.php`

**Step 1: Test**

```php
it('returns 422 when verifying via status=verified without a link', function () {
    $session = LiveSession::factory()->create(['verification_status' => 'pending']);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
        ])
        ->assertStatus(422);
});

it('allows rejecting without a link', function () {
    $session = LiveSession::factory()->create(['verification_status' => 'pending']);

    $response = $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'rejected',
        ]);

    $response->assertRedirect();
    $session->refresh();
    expect($session->verification_status)->toBe('rejected')
        ->and($session->matched_actual_live_record_id)->toBeNull();
});

it('writes reject event on reject', function () {
    $session = LiveSession::factory()->create(['verification_status' => 'pending']);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'rejected',
            'verification_notes' => 'test session',
        ]);

    $event = LiveSessionVerificationEvent::where('live_session_id', $session->id)->first();
    expect($event->action)->toBe('reject')
        ->and($event->notes)->toBe('test session');
});
```

**Step 2: Tighten the controller**

Modify `SessionController::verify`:

```php
public function verify(VerifyLiveSessionRequest $request, LiveSession $session): RedirectResponse
{
    $data = $request->validated();
    $nextStatus = $data['verification_status'];

    // NEW GATE: verified status must go through verify-link
    if ($nextStatus === 'verified') {
        abort(422, 'Use verify-link with an actual_live_record_id.');
    }

    $isUnverify = $nextStatus === 'pending';
    $wasVerified = $session->verification_status === 'verified';

    $attributes = [
        'verification_status' => $nextStatus,
        'verification_notes' => $data['verification_notes'] ?? null,
        'verified_by' => $isUnverify ? null : $request->user()?->id,
        'verified_at' => $isUnverify ? null : now(),
    ];

    // Unverify: also clear the link + GMV lock
    if ($isUnverify) {
        $attributes['matched_actual_live_record_id'] = null;
        $attributes['gmv_amount'] = 0;
        $attributes['gmv_source'] = null;
        $attributes['gmv_locked_at'] = null;
    }

    DB::transaction(function () use ($session, $attributes, $nextStatus, $wasVerified, $request, $data) {
        $priorRecordId = $session->matched_actual_live_record_id;
        $session->update($attributes);

        $action = match ($nextStatus) {
            'rejected' => 'reject',
            'pending' => 'unverify',
            default => null,
        };

        if ($action !== null) {
            LiveSessionVerificationEvent::create([
                'live_session_id' => $session->id,
                'actual_live_record_id' => $action === 'unverify' ? $priorRecordId : null,
                'action' => $action,
                'user_id' => $request->user()?->id,
                'gmv_snapshot' => $wasVerified ? ($session->getOriginal('gmv_amount') ?? 0) : 0,
                'notes' => $data['verification_notes'] ?? null,
            ]);
        }
    });

    $flash = match ($nextStatus) {
        'rejected' => 'Session rejected.',
        'pending' => 'Verification reset.',
        default => 'Session updated.',
    };

    return back()->with('success', $flash);
}
```

**Step 3: Drop `gmv_amount_override` from the request**

In `VerifyLiveSessionRequest::rules()`, remove the `gmv_amount_override` rule (GMV override is no longer exposed here — it always comes from the linked record).

**Step 4: Run + commit**

```bash
php artisan test --compact --filter=VerifyLink
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/SessionController.php app/Http/Requests/LiveHost/VerifyLiveSessionRequest.php tests/Feature/LiveHost/VerifyLink/VerifyGateTest.php
git commit -m "feat(livehost): block verify status without link, add audit events"
```

---

## Phase 6 — Inertia frontend (PIC verification modal)

### Task 12: Surface candidate list from `SessionController::show`

**Files:**
- Modify: `app/Http/Controllers/LiveHost/SessionController.php` (`show` method)
- Test: `tests/Feature/LiveHost/VerifyLink/ShowExposesCandidatesTest.php`

**Step 1: Test**

```php
it('includes candidate list in show response props', function () {
    $pivot = LiveHostPlatformAccount::factory()->create(['creator_platform_user_id' => 'csh']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'pending',
        'scheduled_start_at' => now(),
    ]);
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'creator_platform_user_id' => 'csh',
        'launched_time' => now(),
    ]);

    $this->actingAs(makeAdmin())
        ->get("/livehost/sessions/{$session->id}")
        ->assertInertia(fn ($page) => $page
            ->component('sessions/Show')
            ->has('candidates.0')
            ->where('candidates.0.id', $record->id)
        );
});
```

**Step 2: Wire candidates prop**

In `SessionController::show`, call the finder and pass the collection as `candidates`:

```php
public function show(LiveSession $session): Response
{
    $session->load([...existing...]);

    $candidates = app(ActualLiveRecordCandidateFinder::class)
        ->forSession($session)
        ->map(fn (ActualLiveRecord $r) => [
            'id' => $r->id,
            'launchedTime' => $r->launched_time?->toIso8601String(),
            'endedTime' => $r->ended_time?->toIso8601String(),
            'durationSeconds' => $r->duration_seconds,
            'gmvMyr' => (float) $r->gmv_myr,
            'liveAttributedGmvMyr' => (float) $r->live_attributed_gmv_myr,
            'viewers' => $r->viewers,
            'itemsSold' => $r->items_sold,
            'creatorHandle' => $r->creator_handle,
            'source' => $r->source,
            'isSuggested' => false,  // set below
        ]);

    // Mark top candidate as suggested
    if ($candidates->isNotEmpty()) {
        $candidates = $candidates->values();
        $candidates[0]['isSuggested'] = true;
    }

    return Inertia::render('sessions/Show', [
        'session' => $this->mapSession($session, detailed: true),
        'analytics' => $this->mapAnalytics($session->analytics),
        'attachments' => $session->attachments->map(fn ($a) => $this->mapAttachment($a))->values(),
        'candidates' => $candidates,
    ]);
}
```

**Step 3: Run + commit**

```bash
php artisan test --compact --filter=ShowExposesCandidatesTest
git add app/Http/Controllers/LiveHost/SessionController.php tests/Feature/LiveHost/VerifyLink/ShowExposesCandidatesTest.php
git commit -m "feat(livehost): expose actual record candidates on session show"
```

---

### Task 13: React `VerifyLinkPanel` component

**Files:**
- Create: `resources/js/livehost/pages/sessions/VerifyLinkPanel.jsx`
- Modify: `resources/js/livehost/pages/sessions/Show.jsx` (render the panel)

**Step 1: Component**

```jsx
// resources/js/livehost/pages/sessions/VerifyLinkPanel.jsx
import { useState } from 'react';
import { router } from '@inertiajs/react';

export function VerifyLinkPanel({ session, candidates }) {
    const [selectedId, setSelectedId] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const canSubmit = selectedId !== null && session.verificationStatus === 'pending';

    const submit = () => {
        if (!canSubmit) return;
        setSubmitting(true);
        router.post(
            `/livehost/sessions/${session.id}/verify-link`,
            { actual_live_record_id: selectedId },
            { onFinish: () => setSubmitting(false) }
        );
    };

    const reject = () => {
        router.post(
            `/livehost/sessions/${session.id}/verify`,
            { verification_status: 'rejected' }
        );
    };

    if (session.verificationStatus !== 'pending') {
        return null;
    }

    if (candidates.length === 0) {
        return (
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p className="font-medium text-amber-900">No TikTok records found for this day + host.</p>
                <p className="mt-1 text-sm text-amber-800">
                    Upload the CSV report or wait for API sync. Verification is blocked until a record is linked.
                </p>
                <button
                    onClick={reject}
                    className="mt-3 rounded border border-red-400 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50"
                >
                    Reject this session (no actual live happened)
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <h3 className="font-semibold">Link this session to TikTok actual record</h3>
            <p className="text-sm text-zinc-600">
                Pick the TikTok live that matches this scheduled session. GMV will lock from the selected record.
            </p>
            <div className="space-y-2">
                {candidates.map((c) => (
                    <label
                        key={c.id}
                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                            selectedId === c.id ? 'border-blue-400 bg-blue-50' : 'border-zinc-200 hover:bg-zinc-50'
                        }`}
                    >
                        <input
                            type="radio"
                            name="candidate"
                            checked={selectedId === c.id}
                            onChange={() => setSelectedId(c.id)}
                            className="mt-1"
                        />
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <span className="font-medium">
                                    {new Date(c.launchedTime).toLocaleString('en-MY', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                </span>
                                {c.isSuggested && (
                                    <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800">
                                        Suggested
                                    </span>
                                )}
                                <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-600">
                                    {c.source === 'csv_import' ? 'CSV' : 'API'}
                                </span>
                            </div>
                            <div className="mt-1 grid grid-cols-3 gap-2 text-sm text-zinc-600">
                                <div>
                                    <span className="text-xs text-zinc-500">Live-attrib GMV</span>
                                    <div className="font-mono font-semibold">RM {c.liveAttributedGmvMyr.toFixed(2)}</div>
                                </div>
                                <div>
                                    <span className="text-xs text-zinc-500">Total GMV</span>
                                    <div className="font-mono">RM {c.gmvMyr.toFixed(2)}</div>
                                </div>
                                <div>
                                    <span className="text-xs text-zinc-500">Viewers / Items</span>
                                    <div>{c.viewers ?? '—'} / {c.itemsSold ?? '—'}</div>
                                </div>
                            </div>
                        </div>
                    </label>
                ))}
            </div>
            <div className="flex gap-2 pt-2">
                <button
                    disabled={!canSubmit || submitting}
                    onClick={submit}
                    className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {submitting ? 'Linking…' : 'Link & verify'}
                </button>
                <button
                    onClick={reject}
                    className="rounded border border-red-400 px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                >
                    Reject
                </button>
            </div>
        </div>
    );
}
```

**Step 2: Render on Show page**

In `resources/js/livehost/pages/sessions/Show.jsx`, import and render `<VerifyLinkPanel session={session} candidates={candidates} />` in the section currently showing Verify/Reject buttons (or replace the old panel entirely — your call based on current layout).

**Step 3: Manual browser test**

```bash
npm run dev  # keep running
```
Navigate to `/livehost/sessions/<pending-session-id>` as admin_livehost. Verify the panel renders, candidates list, radio selection, Link & verify button disabled until a candidate is picked.

**Step 4: Commit**

```bash
git add resources/js/livehost/pages/sessions/VerifyLinkPanel.jsx resources/js/livehost/pages/sessions/Show.jsx
git commit -m "feat(livehost): VerifyLinkPanel React component with candidate picker"
```

---

## Phase 7 — CSV import pipeline update

### Task 14: Write to `actual_live_records` on live-analysis import

**Files:**
- Modify: `app/Jobs/ProcessTiktokImportJob.php` (method `processLiveAnalysis`)
- Test: `tests/Feature/LiveHost/ActualLiveRecord/ImportWritesUnifiedRecordTest.php`

**Step 1: Test**

```php
it('creates actual_live_records row for each imported live-analysis row', function () {
    $account = PlatformAccount::factory()->create();
    $import = TiktokReportImport::factory()->create([
        'platform_account_id' => $account->id,
        'report_type' => 'live_analysis',
        'status' => 'pending',
    ]);

    // Stub the parser to return one row
    $this->mock(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class, function ($mock) {
        $mock->shouldReceive('parse')->andReturn([
            [
                'tiktok_creator_id' => 'c1',
                'creator_nickname' => 'host_a',
                'launched_time' => now(),
                'duration_seconds' => 3600,
                'gmv_myr' => 500,
                'live_attributed_gmv_myr' => 400,
                'viewers' => 100,
                'raw_row_json' => [],
            ],
        ]);
    });

    (new \App\Jobs\ProcessTiktokImportJob($import->id))->handle(
        app(\App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\AllOrderXlsxParser::class),
        app(\App\Services\LiveHost\Tiktok\LiveSessionMatcher::class),
        app(\App\Services\LiveHost\Tiktok\OrderRefundReconciler::class),
    );

    expect(ActualLiveRecord::where('import_id', $import->id)->count())->toBe(1);
});
```

**Step 2: Update `processLiveAnalysis`**

After the `TiktokLiveReport::create` call, also create the unified row:

```php
$alr = ActualLiveRecord::create([
    'platform_account_id' => $import->platform_account_id,
    'source' => 'csv_import',
    'source_record_id' => null,
    'import_id' => $import->id,
    'creator_platform_user_id' => $row['tiktok_creator_id'] ?? null,
    'creator_handle' => $row['creator_nickname'] ?? $row['creator_display_name'] ?? null,
    'launched_time' => $row['launched_time'],
    'duration_seconds' => $row['duration_seconds'] ?? null,
    'gmv_myr' => $row['gmv_myr'] ?? 0,
    'live_attributed_gmv_myr' => $row['live_attributed_gmv_myr'] ?? 0,
    'viewers' => $row['viewers'] ?? null,
    'views' => $row['views'] ?? null,
    'comments' => $row['comments'] ?? null,
    'shares' => $row['shares'] ?? null,
    'likes' => $row['likes'] ?? null,
    'new_followers' => $row['new_followers'] ?? null,
    'products_added' => $row['products_added'] ?? null,
    'products_sold' => $row['products_sold'] ?? null,
    'items_sold' => $row['items_sold'] ?? null,
    'sku_orders' => $row['sku_orders'] ?? null,
    'unique_customers' => $row['unique_customers'] ?? null,
    'avg_price_myr' => $row['avg_price_myr'] ?? null,
    'click_to_order_rate' => $row['click_to_order_rate'] ?? null,
    'ctr' => $row['ctr'] ?? null,
    'raw_json' => $row['raw_row_json'] ?? [],
]);
```

Keep the existing `TiktokLiveReport::create` call as-is (raw-import log). The old `LiveSessionMatcher` call stays but its output no longer drives verification.

**Step 3: Run + commit**

```bash
php artisan test --compact --filter=ImportWritesUnifiedRecordTest
vendor/bin/pint --dirty
git add app/Jobs/ProcessTiktokImportJob.php tests/Feature/LiveHost/ActualLiveRecord/ImportWritesUnifiedRecordTest.php
git commit -m "feat(livehost): CSV import writes to actual_live_records"
```

---

## Phase 8 — Polish + final verification

### Task 15: Full test suite + manual smoke test

**Step 1: Run full livehost suite**

```bash
php artisan test --compact tests/Feature/LiveHost/
```
Expected: all green.

**Step 2: Run full suite**

```bash
php artisan test --compact
```
Expected: all green. If existing tests break due to the `gmv_amount_override` removal, update them to match the new flow.

**Step 3: Manual browser smoke test**

In `composer run dev`, log in as `admin@example.com` → go to `/livehost/sessions` → find a pending session → open it → verify:

1. Candidate list shows (or the empty-state message)
2. "Suggested" badge on closest-time candidate
3. Selecting + clicking "Link & verify" → session becomes verified with GMV populated from record
4. Verified session shows "Unverify" option (if implemented in Show.jsx; otherwise via admin tools)
5. Unverifying returns session to pending with GMV cleared
6. Rejecting without picking a candidate works

**Step 4: Final commit**

```bash
vendor/bin/pint
git add -A
git commit -m "chore(livehost): verification-link feature complete"
```

---

## Rollback / safety notes

- All four migrations have working `down()` methods.
- The backfill migration is idempotent (short-circuits if `csv_import` rows already exist). Safe to re-run.
- `tiktok_live_reports` table is preserved as raw-import log. No data loss.
- `LiveSessionMatcher` is left in place but no longer on the critical path. Safe to delete in a later cleanup PR.

## Out of scope (deferred)

- API sync job (waits for TikTok scope approval — separate PR once unblocked)
- Widened candidate search (±3 days button) — do if users report it missing after launch
- Bulk link/verify from list view
- Commission-reversal flow on unverify (noted in design doc — tackle when payroll integrates with verification)
