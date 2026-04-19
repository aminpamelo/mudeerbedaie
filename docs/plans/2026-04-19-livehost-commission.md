# Live Host Commission — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship a 3-layer commission system (base salary + GMV % + per-live rate) with 2-level MLM overrides and TikTok Seller Center xlsx reconciliation for the Live Host Desk.

**Architecture:** New tables for commission profiles, per-platform rates, payroll runs, and TikTok import reports. Extend `live_sessions`, `live_session_slots`, and the `live_host_platform_account` pivot. A `CommissionCalculator` service is the single source of truth for math. A `LiveSessionVerifiedObserver` snapshots commission on PIC verify. Monthly `LiveHostPayrollService` runs the payroll batch. TikTok xlsx parsed with `phpoffice/phpspreadsheet`, matched to sessions by `(tiktok_creator_id, launched_time ±30min)`.

**Tech Stack:** Laravel 12, Inertia.js + React 19, Flux UI, Pest 4, PhpSpreadsheet (new dependency), SQLite (dev) + MySQL (prod).

**Reference design:** [docs/plans/2026-04-19-livehost-commission-design.md](2026-04-19-livehost-commission-design.md)

---

## Table of Contents

- [Phase 1 — Schema & Profiles](#phase-1--schema--profiles) (Tasks 1–8)
- [Phase 2 — Host-facing GMV Entry](#phase-2--host-facing-gmv-entry) (Tasks 9–11)
- [Phase 3 — PIC Verification + Snapshot](#phase-3--pic-verification--snapshot) (Tasks 12–17)
- [Phase 4 — Admin Surfaces](#phase-4--admin-surfaces) (Tasks 18–24)
- [Phase 5 — Monthly Payroll](#phase-5--monthly-payroll) (Tasks 25–31)
- [Phase 6 — TikTok Reconciliation](#phase-6--tiktok-reconciliation) (Tasks 32–40)

**Conventions for every task:**
- All tests are Pest feature tests in `tests/Feature/LiveHost/Commission/` unless noted.
- Run affected tests with `php artisan test --compact --filter=<TestName>`.
- Migrations follow the dual-driver pattern from [CLAUDE.md lines 195–217](/CLAUDE.md) — use `DB::getDriverName()` branching for enum/column changes.
- All new FormRequests authorize via `role in [admin, admin_livehost]`, mirroring [app/Http/Requests/LiveHost/VerifyLiveSessionRequest.php](app/Http/Requests/LiveHost/VerifyLiveSessionRequest.php).
- Run `vendor/bin/pint --dirty` before every commit.
- Commit messages use existing style: `feat(livehost): …`, `test(livehost): …`, `fix(livehost): …`.

---

## Phase 1 — Schema & Profiles

Foundation. Nothing user-visible yet. Exit: tinker can create profiles, assign upline, commission calculator returns correct numbers for a fixture.

### Task 1: Migration — `live_host_commission_profiles`

**Files:**
- Create: `database/migrations/2026_04_19_<hhmmss>_create_live_host_commission_profiles_table.php`
- Test: `tests/Feature/LiveHost/Commission/CommissionProfileSchemaTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('can insert a commission profile row with all expected columns', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $upline = User::factory()->create(['role' => 'live_host']);

    \DB::table('live_host_commission_profiles')->insert([
        'user_id' => $host->id,
        'base_salary_myr' => 2000.00,
        'per_live_rate_myr' => 30.00,
        'upline_user_id' => $upline->id,
        'override_rate_l1_percent' => 10.00,
        'override_rate_l2_percent' => 5.00,
        'effective_from' => now(),
        'effective_to' => null,
        'is_active' => true,
        'notes' => 'initial profile',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    assertDatabaseHas('live_host_commission_profiles', [
        'user_id' => $host->id,
        'base_salary_myr' => 2000.00,
    ]);
});
```

**Step 2: Verify it fails**

Run: `php artisan test --compact --filter=CommissionProfileSchemaTest`
Expected: FAIL with "no such table" / "Base table doesn't exist".

**Step 3: Create migration**

```bash
php artisan make:migration create_live_host_commission_profiles_table --no-interaction
```

Migration content:
```php
public function up(): void
{
    Schema::create('live_host_commission_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
        $table->decimal('base_salary_myr', 10, 2)->default(0);
        $table->decimal('per_live_rate_myr', 10, 2)->default(0);
        $table->foreignId('upline_user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->decimal('override_rate_l1_percent', 5, 2)->default(0);
        $table->decimal('override_rate_l2_percent', 5, 2)->default(0);
        $table->timestamp('effective_from')->useCurrent();
        $table->timestamp('effective_to')->nullable();
        $table->boolean('is_active')->default(true);
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index(['user_id', 'is_active']);
        $table->index('upline_user_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('live_host_commission_profiles');
}
```

Run: `php artisan migrate --no-interaction`

**Step 4: Verify test passes**

Run: `php artisan test --compact --filter=CommissionProfileSchemaTest`
Expected: PASS.

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/*_create_live_host_commission_profiles_table.php tests/Feature/LiveHost/Commission/CommissionProfileSchemaTest.php
git commit -m "feat(livehost): add live_host_commission_profiles table"
```

### Task 2: Migration — `live_host_platform_commission_rates`

**Files:**
- Create: `database/migrations/<ts>_create_live_host_platform_commission_rates_table.php`
- Test: `tests/Feature/LiveHost/Commission/PlatformCommissionRateSchemaTest.php`

**Step 1: Test** — insert a (user_id, platform_id, rate) row; assert row exists. Assert unique(user_id, platform_id, effective_from).

**Step 2: Run → FAIL** (table missing).

**Step 3: Schema:**
```php
Schema::create('live_host_platform_commission_rates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
    $table->decimal('commission_rate_percent', 5, 2)->default(0);
    $table->timestamp('effective_from')->useCurrent();
    $table->timestamp('effective_to')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['user_id', 'platform_id', 'effective_from'], 'uniq_host_platform_rate_effective');
    $table->index(['user_id', 'platform_id', 'is_active']);
});
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): add per-platform commission rates table`.

### Task 3: Migration — extend `live_host_platform_account` pivot + `live_session_slots` + `live_sessions`

**Files:**
- Create: `database/migrations/<ts>_add_commission_fields_to_live_host_tables.php`
- Test: `tests/Feature/LiveHost/Commission/LiveHostTablesExtensionTest.php`

**Step 1: Test**
- Assert `live_host_platform_account` has `creator_handle`, `creator_platform_user_id`, `is_primary`.
- Assert `live_session_slots` has `live_host_platform_account_id` (nullable).
- Assert `live_sessions` has `live_host_platform_account_id`, `gmv_amount`, `gmv_adjustment`, `gmv_source`, `gmv_locked_at`, `commission_snapshot_json`.

Use `Schema::hasColumn('table', 'col')` assertions.

**Step 2: Run → FAIL.**

**Step 3: Migration (dual-driver for any enum). `gmv_source` can be a string column (no enum) to keep SQLite simple — app-level validation.**

```php
public function up(): void
{
    Schema::table('live_host_platform_account', function (Blueprint $table) {
        $table->string('creator_handle')->nullable()->after('platform_account_id');
        $table->string('creator_platform_user_id')->nullable()->after('creator_handle');
        $table->boolean('is_primary')->default(false)->after('creator_platform_user_id');
        $table->index('creator_platform_user_id');
    });

    Schema::table('live_session_slots', function (Blueprint $table) {
        $table->foreignId('live_host_platform_account_id')->nullable()
            ->after('platform_account_id')
            ->constrained('live_host_platform_account', 'id')
            ->nullOnDelete();
    });

    Schema::table('live_sessions', function (Blueprint $table) {
        $table->foreignId('live_host_platform_account_id')->nullable()
            ->after('platform_account_id')
            ->constrained('live_host_platform_account', 'id')
            ->nullOnDelete();
        $table->decimal('gmv_amount', 12, 2)->nullable()->after('duration_minutes');
        $table->decimal('gmv_adjustment', 12, 2)->default(0)->after('gmv_amount');
        $table->string('gmv_source')->default('manual')->after('gmv_adjustment');
        $table->timestamp('gmv_locked_at')->nullable()->after('gmv_source');
        $table->json('commission_snapshot_json')->nullable()->after('gmv_locked_at');
    });
}

public function down(): void
{
    Schema::table('live_sessions', function (Blueprint $table) {
        $table->dropConstrainedForeignId('live_host_platform_account_id');
        $table->dropColumn(['gmv_amount', 'gmv_adjustment', 'gmv_source', 'gmv_locked_at', 'commission_snapshot_json']);
    });
    Schema::table('live_session_slots', function (Blueprint $table) {
        $table->dropConstrainedForeignId('live_host_platform_account_id');
    });
    Schema::table('live_host_platform_account', function (Blueprint $table) {
        $table->dropColumn(['creator_handle', 'creator_platform_user_id', 'is_primary']);
    });
}
```

**Note**: `live_host_platform_account` pivot table uses composite key `(user_id, platform_account_id)`, no `id` by default. Check current pivot — if no `id`, first add one:

```php
// If needed as a PRE-step in the migration:
// Schema::table('live_host_platform_account', fn($t) => $t->id()->first());
```

Verify by inspecting the existing pivot migration. If it already has `id()`, skip.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): extend live host tables with commission + creator identity fields`.

### Task 4: Migrations — audit log, payroll runs, payroll items, TikTok imports

Four independent migrations, batched into one task for size (still each atomic).

**Files:**
- Create: `<ts>_create_live_session_gmv_adjustments_table.php`
- Create: `<ts>_create_live_host_payroll_runs_table.php`
- Create: `<ts>_create_live_host_payroll_items_table.php`
- Create: `<ts>_create_tiktok_report_tables.php` (imports + live_reports + orders)
- Test: `tests/Feature/LiveHost/Commission/CommissionSupportingTablesSchemaTest.php`

**Step 1: Test** — for each table, assert it exists and has the exact columns listed in the [design doc Section 4.1](2026-04-19-livehost-commission-design.md#41-new-tables).

**Step 2: Run → FAIL.**

**Step 3: Migration schemas** — follow the design doc verbatim. Use `->constrained()->cascadeOnDelete()` for FKs. Use `->json()` for JSON columns. Use `->index()` on match fields (`tiktok_creator_id`, `launched_time`, `matched_live_session_id`). Unique keys: `tiktok_orders.tiktok_order_id`, `live_host_payroll_items(payroll_run_id, user_id)`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): add commission audit + payroll + tiktok import schema`.

### Task 5: Eloquent Models

**Files:**
- Create: `app/Models/LiveHostCommissionProfile.php`
- Create: `app/Models/LiveHostPlatformCommissionRate.php`
- Create: `app/Models/LiveSessionGmvAdjustment.php`
- Create: `app/Models/LiveHostPayrollRun.php`
- Create: `app/Models/LiveHostPayrollItem.php`
- Create: `app/Models/TiktokReportImport.php`
- Create: `app/Models/TiktokLiveReport.php`
- Create: `app/Models/TiktokOrder.php`
- Modify: `app/Models/User.php` — add `commissionProfile()`, `platformCommissionRates()`, `upline()`, `directDownlines()`, `l2Downlines()`.
- Modify: `app/Models/LiveSession.php` — add `gmvAdjustments()`, `commissionProfile` accessor.
- Test: `tests/Feature/LiveHost/Commission/CommissionModelRelationshipsTest.php`

**Step 1: Test** — create fixtures, assert:
- `$host->commissionProfile` returns a `LiveHostCommissionProfile`.
- `$host->platformCommissionRates` returns rates where `is_active=true`.
- `$host->upline` returns upline user.
- `$host->directDownlines` returns hosts whose profile's `upline_user_id = $host->id`.
- `$host->l2Downlines` returns hosts 2 levels below.
- `$session->gmvAdjustments` returns audit rows.

**Step 2: Run → FAIL** (models don't exist / relationships missing).

**Step 3: Implement models**

```php
// app/Models/LiveHostCommissionProfile.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostCommissionProfile extends Model
{
    protected $fillable = [
        'user_id', 'base_salary_myr', 'per_live_rate_myr',
        'upline_user_id', 'override_rate_l1_percent', 'override_rate_l2_percent',
        'effective_from', 'effective_to', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary_myr' => 'decimal:2',
            'per_live_rate_myr' => 'decimal:2',
            'override_rate_l1_percent' => 'decimal:2',
            'override_rate_l2_percent' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(User::class, 'upline_user_id');
    }
}
```

Follow the same shape for the other models. All timestamps/decimals cast appropriately. JSON columns use `'array'` cast.

In `User.php`:
```php
public function commissionProfile(): HasOne
{
    return $this->hasOne(LiveHostCommissionProfile::class)->where('is_active', true);
}

public function platformCommissionRates(): HasMany
{
    return $this->hasMany(LiveHostPlatformCommissionRate::class)->where('is_active', true);
}

public function upline(): BelongsTo
{
    return $this->belongsTo(User::class, 'upline_user_id')
        ->through('commissionProfile')   // pseudo; implement via accessor if needed
        ;
}

// Simpler: use accessor
public function getUplineAttribute(): ?User
{
    return optional($this->commissionProfile)->upline;
}

public function directDownlines()
{
    // Users whose active commission profile has upline_user_id = this.id
    return User::query()
        ->whereHas('commissionProfile', fn ($q) => $q->where('upline_user_id', $this->id));
}

public function l2Downlines()
{
    $directIds = $this->directDownlines()->pluck('users.id');
    return User::query()
        ->whereHas('commissionProfile', fn ($q) => $q->whereIn('upline_user_id', $directIds));
}
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): commission eloquent models and user relationships`.

### Task 6: Factories + Seeder

**Files:**
- Create: `database/factories/LiveHostCommissionProfileFactory.php`
- Create: `database/factories/LiveHostPlatformCommissionRateFactory.php`
- Create: `database/seeders/LiveHostCommissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (only if it already runs live-host seeders; otherwise just expose the new seeder for manual calls).
- Test: `tests/Feature/LiveHost/Commission/CommissionFactoryTest.php`

**Step 1: Test** — factories produce valid rows; seeder creates 3 hosts matching the design's worked example (Ahmad, Sarah, Amin) with correct upline chain.

**Step 2: Run → FAIL.**

**Step 3: Factories**

```php
// LiveHostCommissionProfileFactory
public function definition(): array
{
    return [
        'user_id' => User::factory()->create(['role' => 'live_host'])->id,
        'base_salary_myr' => fake()->randomElement([0, 1500, 1800, 2000, 2500]),
        'per_live_rate_myr' => fake()->randomElement([0, 20, 25, 30, 50]),
        'upline_user_id' => null,
        'override_rate_l1_percent' => 10,
        'override_rate_l2_percent' => 5,
        'effective_from' => now(),
        'is_active' => true,
    ];
}

public function withUpline(User $upline): static
{
    return $this->state(fn () => ['upline_user_id' => $upline->id]);
}
```

Seeder constructs the worked-example fixture used by later tests.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): commission factories + worked-example seeder`.

### Task 7: `CommissionCalculator` service — per-session math

**Files:**
- Create: `app/Services/LiveHost/CommissionCalculator.php`
- Test: `tests/Feature/LiveHost/Commission/CommissionCalculatorPerSessionTest.php`

**Step 1: Test** — for a seeded host with rate 4% TikTok, per_live_rate RM30, a session with `gmv_amount=1500`, `gmv_adjustment=-200`, `platform=TikTok`:
- `CommissionCalculator::forSession($session)` returns array:
  ```php
  ['net_gmv' => 1300.00, 'gmv_commission' => 52.00, 'per_live_rate' => 30.00, 'session_total' => 82.00, 'platform_rate_percent' => 4.00]
  ```
- Test missed-session case: `gmv_amount=0`, session is missed → `per_live_rate=0`, `session_total=0`.
- Test no-rate case: host has no platform rate → `gmv_commission=0`, `warnings` array includes `'missing_platform_rate'`.

**Step 2: Run → FAIL.**

**Step 3: Service**

```php
namespace App\Services\LiveHost;

use App\Models\LiveSession;

class CommissionCalculator
{
    public function forSession(LiveSession $session): array
    {
        $warnings = [];
        $netGmv = (float) ($session->gmv_amount ?? 0) + (float) ($session->gmv_adjustment ?? 0);

        $rate = $session->liveHost?->platformCommissionRates
            ?->firstWhere('platform_id', $session->platformAccount?->platform_id);

        if (! $rate && $netGmv > 0) {
            $warnings[] = 'missing_platform_rate';
        }

        $ratePercent = (float) ($rate?->commission_rate_percent ?? 0);
        $gmvCommission = round($netGmv * $ratePercent / 100, 2);

        $isMissed = $session->status === 'missed';
        $profile = $session->liveHost?->commissionProfile;
        $perLiveRate = $isMissed ? 0.0 : (float) ($profile?->per_live_rate_myr ?? 0);

        return [
            'net_gmv' => round($netGmv, 2),
            'platform_rate_percent' => $ratePercent,
            'gmv_commission' => $gmvCommission,
            'per_live_rate' => $perLiveRate,
            'session_total' => round($gmvCommission + $perLiveRate, 2),
            'warnings' => $warnings,
        ];
    }
}
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): CommissionCalculator::forSession`.

### Task 8: Upline validation — prevent circular chains

**Files:**
- Create: `app/Rules/NoCircularUpline.php`
- Test: `tests/Feature/LiveHost/Commission/NoCircularUplineTest.php`

**Step 1: Test**
- A (no upline). B.upline=A. C.upline=B. Setting A.upline=C → rule fails.
- Setting A.upline=B → rule fails (A would be own L2 ancestor).
- Setting A.upline=D (D unrelated) → rule passes.

**Step 2: Run → FAIL.**

**Step 3: Rule** — walks up from proposed upline; if target user id appears in the chain, fail.

```php
public function validate(string $attribute, mixed $value, Closure $fail): void
{
    if (! $value) return;
    $targetUserId = $this->targetUserId;  // passed in constructor
    $cursor = $value;
    $seen = [];
    for ($depth = 0; $depth < 20; $depth++) {
        if ((int) $cursor === (int) $targetUserId) {
            $fail('Circular upline detected.');
            return;
        }
        if (in_array($cursor, $seen, true)) return; // safety
        $seen[] = $cursor;
        $upline = LiveHostCommissionProfile::where('user_id', $cursor)
            ->where('is_active', true)
            ->value('upline_user_id');
        if (! $upline) return;
        $cursor = $upline;
    }
}
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): circular-upline validation rule`.

---

## Phase 2 — Host-facing GMV Entry

Live Host Pocket recap form now captures GMV + required screenshot. Exit: a host submitting a recap populates `live_sessions.gmv_amount` and the screenshot attachment.

### Task 9: Extend recap FormRequest + controller

**Files:**
- Modify: `app/Http/Controllers/LiveHostPocket/*RecapController.php` (find the one handling `POST /live-host/sessions/{session}/recap`).
- Modify: the existing recap FormRequest (grep `app/Http/Requests/LiveHostPocket/`).
- Test: `tests/Feature/LiveHostPocket/RecapGmvEntryTest.php`

**Step 1: Test**
- Host submits recap with `went_live=true`, `gmv_amount=1500.50`, valid attachment → session updates, `gmv_amount=1500.50`, `gmv_source='manual'`, `gmv_locked_at=null`.
- Submits with `went_live=true` without `gmv_amount` → 422.
- Submits with `went_live=false` → `gmv_amount=0` regardless of input.
- Submits without TikTok-screenshot attachment type → 422 with "screenshot required".

**Step 2: Run → FAIL.**

**Step 3: Implementation**
- Add `gmv_amount` to FormRequest rules: `required_if:went_live,true|numeric|min:0|max:9999999.99`.
- Add validation: when `went_live=true`, at least one attachment must have `type='tiktok_shop_screenshot'`. (Introduce this attachment type — extend the enum/constant used by attachments.)
- Controller writes `gmv_amount`, sets `gmv_source='manual'`, leaves `gmv_locked_at=null`.
- If `went_live=false`, force `gmv_amount=0`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost-pocket): recap form captures GMV + required tiktok screenshot`.

### Task 10: React recap form UI updates

**Files:**
- Modify: `resources/js/livehost-pocket/pages/SessionDetail.jsx` (recap form component) — add GMV number input + attachment-type selector.
- Test: `tests/Browser/LiveHostPocket/RecapGmvEntryBrowserTest.php`

**Step 1: Test (browser)**
- Visit `/live-host/sessions/{id}` as host.
- Click "Yes, I went live".
- Fill GMV field.
- Upload a file, pick `tiktok_shop_screenshot` as type.
- Submit.
- Assert GMV saved on the session.

**Step 2: Run → FAIL** (UI missing).

**Step 3: UI**
- Numeric input with RM prefix.
- "Upload TikTok Shop backend screenshot" required box with file preview.
- Small helper text: "We use this to verify your GMV."
- Preview card: "Estimated earnings: RM{calc}" — computed locally from host's profile if exposed in Inertia props.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost-pocket): GMV field + screenshot upload in recap UI`.

### Task 11: Expose host commission rate to pocket (for estimate preview)

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:41-62` — add `auth.user.commission` shape when role=live_host, containing `per_live_rate_myr`, `primary_platform_rate_percent`.
- Test: `tests/Feature/LiveHostPocket/SharedPropsTest.php`

**Step 1: Test** — logged-in host sees commission numbers in Inertia props.

**Step 2: Run → FAIL.**

**Step 3: Middleware** — add block:
```php
'commission' => $user && $user->role === 'live_host' ? [
    'per_live_rate_myr' => (float) optional($user->commissionProfile)->per_live_rate_myr,
    'primary_platform_rate_percent' => (float) optional($user->platformCommissionRates->first())->commission_rate_percent,
] : null,
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost-pocket): expose commission rate to recap estimate UI`.

---

## Phase 3 — PIC Verification + Snapshot

PIC verifies on Session Detail → locks GMV + snapshots commission. Exit: worked-example sessions produce correct snapshots.

### Task 12: `CommissionCalculator::snapshotForSession`

**Files:**
- Modify: `app/Services/LiveHost/CommissionCalculator.php`
- Test: `tests/Feature/LiveHost/Commission/CommissionSnapshotTest.php`

**Step 1: Test** — seeded session, call `CommissionCalculator::snapshot($session, $actor)`:
- Returns array matching `forSession()` output + `snapshotted_at`, `snapshotted_by_user_id`, `rate_source` (which platform rate id).
- Method does NOT persist; persistence is the caller's job.

**Step 2: Run → FAIL.**

**Step 3: Add method** — wraps `forSession()` and adds audit metadata.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): commission snapshot with audit metadata`.

### Task 13: `LiveSessionVerifiedObserver`

**Files:**
- Create: `app/Observers/LiveSessionVerifiedObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (or `EventServiceProvider`) — register observer.
- Test: `tests/Feature/LiveHost/Commission/LiveSessionVerifyObserverTest.php`

**Step 1: Test**
- Update session's `verification_status` from `pending` → `verified`. Assert `gmv_locked_at` set, `commission_snapshot_json` populated, matches calculator output.
- Update session already verified → no overwrite of existing snapshot (idempotent).

**Step 2: Run → FAIL.**

**Step 3: Observer**

```php
public function saving(LiveSession $session): void
{
    if (! $session->isDirty('verification_status')) return;
    if ($session->verification_status !== 'verified') return;
    if ($session->gmv_locked_at) return; // already locked

    $session->gmv_locked_at = now();
    $session->commission_snapshot_json = app(CommissionCalculator::class)
        ->snapshot($session, auth()->user());
}
```

Register in `AppServiceProvider::boot()`:
```php
LiveSession::observe(LiveSessionVerifiedObserver::class);
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): snapshot commission on session verify`.

### Task 14: Adjust VerifyLiveSessionRequest + controller

**Files:**
- Modify: `app/Http/Requests/LiveHost/VerifyLiveSessionRequest.php` — allow `gmv_amount_override` (PIC may correct at time of verify).
- Modify: `app/Http/Controllers/LiveHost/SessionController.php` (the verify action) — apply override, then save; observer picks up.
- Test: `tests/Feature/LiveHost/Commission/PicVerifyWithOverrideTest.php`

**Step 1: Test** — PIC verifies with `gmv_amount_override=888`, session ends with `gmv_amount=888`, `gmv_locked_at` set, snapshot has RM888 × rate.

**Step 2: Run → FAIL.**

**Step 3: Implementation** — add optional field to FormRequest; if present, set `$session->gmv_amount = $request->validated('gmv_amount_override')` before `save()`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): PIC can override GMV on verify`.

### Task 15: GMV adjustments CRUD (PIC) + audit log

**Files:**
- Create: `app/Http/Controllers/LiveHost/LiveSessionGmvAdjustmentController.php`
- Create: `app/Http/Requests/LiveHost/StoreLiveSessionGmvAdjustmentRequest.php`
- Modify: `routes/web.php` — add routes under `/livehost/sessions/{session}/adjustments`.
- Test: `tests/Feature/LiveHost/Commission/GmvAdjustmentTest.php`

**Step 1: Test**
- PIC POSTs `{amount: -120, reason: 'RM120 returned order #ABC'}` → adjustment row created; session's `gmv_adjustment` updated (sum of all adjustment amounts).
- Host (live_host role) attempts → 403.
- Attempt to adjust session whose month's payroll run is `locked` → 403 "payroll locked".
- Positive adjustment allowed (occasional correction) — just validates `amount != 0`.

**Step 2: Run → FAIL.**

**Step 3: Implementation**
- FormRequest rules: `amount: required|numeric|not_in:0`, `reason: required|string|max:500`.
- Controller wraps in DB transaction: create row, recompute session's `gmv_adjustment = sum(amounts)`, resnapshot commission.
- Guard: if payroll run for session's month is `locked`, 403.
- Route:
```php
Route::post('sessions/{session}/adjustments', [LiveSessionGmvAdjustmentController::class, 'store']);
Route::delete('sessions/{session}/adjustments/{adjustment}', [LiveSessionGmvAdjustmentController::class, 'destroy']);
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): GMV adjustment CRUD + audit log for returns/refunds`.

### Task 16: Session Detail React — Commission panel

**Files:**
- Modify: `resources/js/livehost/pages/sessions/Show.jsx` (or equivalent path — find it via `Inertia::render('sessions/Show')` in `SessionController`).
- Test: `tests/Browser/LiveHost/SessionDetailCommissionPanelBrowserTest.php`

**Step 1: Test (browser)**
- PIC opens verified session → sees Commission panel with GMV, adjustments list, snapshot breakdown (RM values), creator identity.
- PIC adds adjustment → row appears without full reload (Inertia partial).
- PIC on a payroll-locked session → "Add adjustment" button disabled with tooltip.

**Step 2: Run → FAIL.**

**Step 3: UI**
- Panel heading: "Commission".
- Fields: GMV (editable pre-verify, read-only post), "gmv locked at" timestamp, Creator handle (display).
- Table: adjustments (amount, reason, who, when).
- "Add adjustment" modal → POST.
- Collapsed "Snapshot" pre tag with JSON for auditors.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): session detail commission panel for PIC`.

### Task 17: Backfill `commission_snapshot_json` for already-verified sessions

**Files:**
- Create: `app/Console/Commands/BackfillLiveSessionCommissionSnapshots.php`
- Test: `tests/Feature/LiveHost/Commission/BackfillSnapshotsCommandTest.php`

**Step 1: Test** — pre-existing verified sessions (no snapshot) get populated after running the command.

**Step 2: Run → FAIL.**

**Step 3: Command**
```bash
php artisan make:command BackfillLiveSessionCommissionSnapshots --no-interaction
```
Iterates `LiveSession::whereNotNull('gmv_locked_at')->whereNull('commission_snapshot_json')` and applies calculator.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): backfill command for commission snapshots`.

---

## Phase 4 — Admin Surfaces

Host commission profile editing + overview matrix. Exit: PIC can set up the 3 hosts from the worked example end-to-end.

### Task 18: Commission profile CRUD routes + controller

**Files:**
- Create: `app/Http/Controllers/LiveHost/LiveHostCommissionProfileController.php` (only `store`, `update` — no separate index; nested under host edit).
- Create: `app/Http/Requests/LiveHost/StoreLiveHostCommissionProfileRequest.php`
- Create: `app/Http/Requests/LiveHost/UpdateLiveHostCommissionProfileRequest.php`
- Modify: `routes/web.php`.
- Test: `tests/Feature/LiveHost/Commission/CommissionProfileControllerTest.php`

**Step 1: Test**
- PIC POSTs profile for a host → row created.
- PUT updates an existing profile (sets prior row's `effective_to`, creates new active row).
- PUT with circular upline → 422.
- Host self (live_host role) can't POST → 403.

**Step 2: Run → FAIL.**

**Step 3: Implementation**
- FormRequest uses `NoCircularUpline` rule.
- Update logic wraps in transaction: deactivate existing active profile (`effective_to=now()`, `is_active=false`), insert new row.
- Routes:
```php
Route::post('hosts/{host}/commission-profile', [LiveHostCommissionProfileController::class, 'store']);
Route::put('hosts/{host}/commission-profile', [LiveHostCommissionProfileController::class, 'update']);
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): commission profile CRUD with effective-dating`.

### Task 19: Platform commission rate CRUD

**Files:**
- Create: `app/Http/Controllers/LiveHost/LiveHostPlatformCommissionRateController.php`
- Create: `app/Http/Requests/LiveHost/StoreLiveHostPlatformCommissionRateRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/LiveHost/Commission/PlatformRateControllerTest.php`

**Step 1: Test** — PIC adds TikTok rate 4% for host → row created. Update → prior deactivated, new active. Host self → 403.

**Step 2: Run → FAIL.**

**Step 3: Route:**
```php
Route::post('hosts/{host}/platform-rates', [LiveHostPlatformCommissionRateController::class, 'store']);
Route::put('hosts/{host}/platform-rates/{rate}', [LiveHostPlatformCommissionRateController::class, 'update']);
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): per-platform commission rate CRUD`.

### Task 20: Live Host Desk — Host detail: "Commission" tab

**Files:**
- Modify: `resources/js/livehost/pages/hosts/Show.jsx` (or Edit modal page — inspect the existing host detail UI).
- Test: `tests/Browser/LiveHost/HostCommissionTabBrowserTest.php`

**Step 1: Test (browser)**
- Navigate to `/livehost/hosts/{id}`.
- Click "Commission" tab.
- Set base salary, per-live rate, upline dropdown, override %s, TikTok rate.
- Save.
- Revisit — values persisted.
- Attempt circular upline → inline error shown.

**Step 2: Run → FAIL.**

**Step 3: UI**
- Tab pattern per [resources/js/cms/components/ui/tabs.jsx](resources/js/cms/components/ui/tabs.jsx). Extract/re-use.
- Form fields + submit using `router.post`/`router.put`.
- Upline dropdown: Combobox backed by `/livehost/hosts?search=…` search. Excludes self. Typeahead.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): commission tab on host detail`.

### Task 21: Host index — new Commission Plan column + filter

**Files:**
- Modify: `app/Http/Controllers/LiveHost/HostController.php` — include `commissionProfile` eager-loaded in index.
- Modify: `resources/js/livehost/pages/hosts/Index.jsx` — new column + filter.
- Test: `tests/Feature/LiveHost/Commission/HostIndexCommissionColumnTest.php`

**Step 1: Test**
- Inertia response for `/livehost/hosts` has `commission_plan` on each row: `RM2000 + 4% + RM30`.
- Filter `has_upline=1` returns only hosts whose profile's `upline_user_id IS NOT NULL`.

**Step 2: Run → FAIL.**

**Step 3: Implementation** — eager-load `commissionProfile`, `platformCommissionRates`. Add query filter clauses.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): commission plan column + upline filter on hosts index`.

### Task 22: New page — Commission Overview (`/livehost/commission`)

**Files:**
- Create: `app/Http/Controllers/LiveHost/CommissionOverviewController.php`
- Create: `resources/js/livehost/pages/commission/Index.jsx`
- Modify: `routes/web.php` — `Route::get('commission', ...)`.
- Modify: sidebar nav component — add "Commission" link.
- Test: `tests/Feature/LiveHost/Commission/CommissionOverviewTest.php`, `tests/Browser/LiveHost/CommissionOverviewBrowserTest.php`

**Step 1: Test** — Feature: Inertia props include array of all hosts × commission fields + upline name. Browser: inline-edit a cell → persists.

**Step 2: Run → FAIL.**

**Step 3: Implementation**
- Controller returns paginated hosts with profile+rates.
- React: table with columns per section 7.2. Inline edit via `router.put`.
- CSV export endpoint: `Route::get('commission/export', ...)`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): commission overview page with inline editing`.

### Task 23: Creator identity on Session Slot

**Files:**
- Modify: `app/Http/Requests/LiveHost/StoreSessionSlotRequest.php`
- Modify: `app/Http/Requests/LiveHost/UpdateSessionSlotRequest.php`
- Modify: `app/Http/Controllers/LiveHost/SessionSlotController.php`
- Modify: `resources/js/livehost/pages/session-slots/*.jsx` — add creator-identity dropdown.
- Test: `tests/Feature/LiveHost/SessionSlotCreatorIdentityTest.php`

**Step 1: Test**
- Create slot without `live_host_platform_account_id` → 422.
- Create with it → saved. When LiveSession is created from the slot (existing observer `LiveScheduleAssignmentObserver`), the session's `live_host_platform_account_id` inherits from the slot.

**Step 2: Run → FAIL.**

**Step 3: Implementation**
- Rule: `live_host_platform_account_id: required|exists:live_host_platform_account,id`.
- React: dropdown of `(shop - creator_handle)` options for the chosen host. Auto-picks primary.
- Modify observer to pass the value through on `updateOrCreate`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): require creator identity on session slot`.

### Task 24: Pivot management — add creator handle form on Platform Accounts linkage

**Files:**
- Modify: `resources/js/livehost/pages/platform-accounts/Show.jsx` (or wherever hosts are linked to platform accounts).
- Modify: controller that handles the attach/update.
- Test: `tests/Feature/LiveHost/Commission/PivotCreatorIdentityTest.php`

**Step 1: Test** — attach a host to a platform account with `creator_handle=@amar`, `creator_platform_user_id=6526684…`, `is_primary=true`. Stored correctly. Only one `is_primary=true` per (host × platform_account).

**Step 2: Run → FAIL.**

**Step 3: Implementation** — extend pivot attach with the new fields. Service method ensures `is_primary=true` is exclusive per (user × platform_account).

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): creator identity on host-platform-account pivot`.

---

## Phase 5 — Monthly Payroll

`/livehost/payroll` list + run detail. Draft → Lock → Mark Paid. Exit: worked-example produces exact RM values.

### Task 25: `LiveHostPayrollService::generateDraft`

**Files:**
- Create: `app/Services/LiveHost/LiveHostPayrollService.php`
- Test: `tests/Feature/LiveHost/Commission/PayrollGenerateDraftTest.php`

**Step 1: Test** — seed the worked example (Ahmad/Sarah/Amin + 30 verified sessions with known GMVs/returns). Call `generateDraft(Carbon $periodStart, Carbon $periodEnd, User $actor)`:
- Creates a `LiveHostPayrollRun` with `status=draft`.
- Creates `LiveHostPayrollItem` per active host with base salary, own earnings, overrides matching Section 5.3 of the design doc.
- Exact numbers: Ahmad 2,864.60 / Sarah 3,105.20 / Amin 1,802.00.

**Step 2: Run → FAIL.**

**Step 3: Service** — DB transaction. Aggregate `LiveSession` per host for verified + period. Walk upline chain for overrides using `directDownlines()` / `l2Downlines()`. Persist items with `calculation_breakdown_json`.

Key method:
```php
public function generateDraft(Carbon $periodStart, Carbon $periodEnd, User $actor): LiveHostPayrollRun
{
    return DB::transaction(function () use ($periodStart, $periodEnd, $actor) {
        $run = LiveHostPayrollRun::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'cutoff_date' => $periodEnd->copy()->addDays(14),
            'status' => 'draft',
        ]);

        foreach ($this->activeHostsForPeriod($periodStart, $periodEnd) as $host) {
            $this->createItemForHost($run, $host, $periodStart, $periodEnd);
        }

        return $run->fresh('items');
    });
}
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): LiveHostPayrollService::generateDraft with 2-level overrides`.

### Task 26: Recompute + Lock + Mark Paid methods

**Files:**
- Modify: `app/Services/LiveHost/LiveHostPayrollService.php`
- Test: `tests/Feature/LiveHost/Commission/PayrollLifecycleTest.php`

**Step 1: Test**
- `recompute($run)` on `draft` regenerates items; existing rows deleted + recreated.
- `recompute($run)` on `locked` → exception.
- `lock($run, $actor)` sets `status=locked`, `locked_at`, `locked_by`.
- `markPaid($run, $actor)` sets `status=paid`, `paid_at`. Requires `locked`.
- After lock: adjustments on sessions in this period → 403 (tested via controller, see Task 15 already).

**Step 2: Run → FAIL.**

**Step 3: Methods.**

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): payroll recompute/lock/paid lifecycle`.

### Task 27: Payroll Runs controller + routes

**Files:**
- Create: `app/Http/Controllers/LiveHost/LiveHostPayrollRunController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/LiveHost/Commission/PayrollRunControllerTest.php`

**Step 1: Test**
- `GET /livehost/payroll` → list (Inertia).
- `POST /livehost/payroll` with `period_start/period_end` → creates draft run.
- `POST /livehost/payroll/{run}/recompute` → succeeds if draft.
- `POST /livehost/payroll/{run}/lock` → succeeds if draft, returns locked.
- `POST /livehost/payroll/{run}/mark-paid` → succeeds if locked.
- `GET /livehost/payroll/{run}/export` → CSV download.

**Step 2: Run → FAIL.**

**Step 3: Routes + controller actions.** CSV export uses `response()->streamDownload`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): payroll run controller + CSV export`.

### Task 28: Payroll list page (`/livehost/payroll`)

**Files:**
- Create: `resources/js/livehost/pages/payroll/Index.jsx`
- Modify: sidebar nav — add "Payroll" link.
- Test: `tests/Browser/LiveHost/PayrollIndexBrowserTest.php`

**Step 1: Test (browser)** — PIC sees list of runs, badges for draft/locked/paid, click a row → goes to run detail.

**Step 2: Run → FAIL.**

**Step 3: UI** — mirror `sessions/Index.jsx` pattern: paginated table with status chips, filters, "New run" button.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): payroll list page`.

### Task 29: Payroll run detail page

**Files:**
- Create: `resources/js/livehost/pages/payroll/Show.jsx`
- Test: `tests/Browser/LiveHost/PayrollRunDetailBrowserTest.php`

**Step 1: Test (browser)**
- Shows per-host rows with columns per design Section 7.2.
- Click a row → side drawer with per-session + per-downline breakdown.
- Draft run shows "Recompute" and "Lock" buttons; locked shows only "Mark Paid" + "Export".

**Step 2: Run → FAIL.**

**Step 3: UI** — table with row expansion; action buttons gated by `status`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): payroll run detail with per-session drill-down`.

### Task 30: Artisan command — generate payroll for prior month

**Files:**
- Create: `app/Console/Commands/GenerateLiveHostPayrollDraft.php`
- Test: `tests/Feature/LiveHost/Commission/GeneratePayrollCommandTest.php`

**Step 1: Test** — `php artisan livehost:payroll-draft --period=2026-04` generates the April 2026 run.

**Step 2: Run → FAIL.**

**Step 3: Command** — parses `--period`, calls service.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): artisan command for payroll draft generation`.

### Task 31: Golden-path integration test

**Files:**
- Create: `tests/Feature/LiveHost/Commission/PayrollGoldenPathTest.php`

**Step 1: Test** — seed the worked example from `LiveHostCommissionSeeder`. Generate April payroll. Assert exact numbers:
- Ahmad net_payout = 2864.60
- Sarah net_payout = 3105.20
- Amin net_payout = 1802.00
- Total = 7771.80
- Effective payroll % of net GMV = 15.24%.

**Step 2: Run → PASS** (shouldn't fail by now; if it does, earlier tasks have bugs).

**Step 3: N/A.**

**Step 4: N/A.**

**Step 5: Commit** — `test(livehost): golden-path payroll integration test`.

---

## Phase 6 — TikTok Reconciliation

xlsx upload + match + variance. Exit: Live Analysis xlsx populates `gmv_amount` from TikTok on matched sessions. All Order xlsx auto-proposes adjustments for refunded orders.

### Task 32: Add PhpSpreadsheet dependency

**Files:**
- Modify: `composer.json`
- Commit includes `composer.lock`

**Step 1-4:** No test needed for install alone. Next task's test exercises it.

**Step 5: Commands**
```bash
composer require phpoffice/phpspreadsheet --no-interaction
git add composer.json composer.lock
git commit -m "chore: add phpoffice/phpspreadsheet for tiktok xlsx import"
```

### Task 33: xlsx parser service — Live Analysis

**Files:**
- Create: `app/Services/LiveHost/Tiktok/LiveAnalysisXlsxParser.php`
- Create: `tests/Feature/LiveHost/Commission/LiveAnalysisXlsxParserTest.php`
- Fixture: `tests/Fixtures/tiktok/live_analysis_sample.xlsx` (hand-crafted minimal file with 2 rows matching real column headers)

**Step 1: Test** — parser reads fixture, returns array of 25-column rows with correct types (decimals, datetimes, ints). Handles the "Date Range:" header row and "Column header" row structure (rows 1 + 3 of the real file).

**Step 2: Run → FAIL.**

**Step 3: Parser** — uses `PhpOffice\PhpSpreadsheet\IOFactory::load($path)`. Finds header row by matching `Creator ID` in column A. Iterates data rows. Duration string (`1h 40min`) → seconds helper. Returns collection.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): tiktok Live Analysis xlsx parser`.

### Task 34: xlsx parser service — All Order

**Files:**
- Create: `app/Services/LiveHost/Tiktok/AllOrderXlsxParser.php`
- Create: `tests/Feature/LiveHost/Commission/AllOrderXlsxParserTest.php`
- Fixture: `tests/Fixtures/tiktok/all_order_sample.xlsx` (3 rows: one Shipped, one Cancelled, one Delivered with refund)

**Step 1: Test** — parses 54-column rows; date strings (`18/04/2026 23:45:00`) → Carbon; amounts → decimals.

**Step 2-5:** Analogous to Task 33.

### Task 35: Matcher service — Live Analysis → LiveSession

**Files:**
- Create: `app/Services/LiveHost/Tiktok/LiveSessionMatcher.php`
- Test: `tests/Feature/LiveHost/Commission/LiveSessionMatcherTest.php`

**Step 1: Test**
- Report row with `tiktok_creator_id=X`, `launched_time=2026-04-18 22:14` → matches session where `live_host_platform_account.creator_platform_user_id=X` AND `actual_start_at within ±30min`.
- No match → returns null + flag.
- Multiple candidates → picks the closest-time match, flag "ambiguous".

**Step 2: Run → FAIL.**

**Step 3: Service**
```php
public function match(TiktokLiveReport $report): ?LiveSession
{
    return LiveSession::query()
        ->whereHas('liveHostPlatformAccount', fn ($q) => $q
            ->where('creator_platform_user_id', $report->tiktok_creator_id))
        ->whereBetween('actual_start_at', [
            $report->launched_time->copy()->subMinutes(30),
            $report->launched_time->copy()->addMinutes(30),
        ])
        ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, actual_start_at, ?))', [$report->launched_time])
        ->first();
}
```

(Use DB-agnostic ordering — for SQLite, use `julianday` arithmetic in a portable helper.)

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): tiktok live report matcher`.

### Task 36: Import controller + variance application

**Files:**
- Create: `app/Http/Controllers/LiveHost/TiktokReportImportController.php`
- Create: `app/Http/Requests/LiveHost/UploadTiktokReportRequest.php`
- Modify: `routes/web.php` — new routes under `/livehost/tiktok-imports`.
- Test: `tests/Feature/LiveHost/Commission/TiktokReportImportTest.php`

**Step 1: Test**
- PIC uploads fixture xlsx → `tiktok_report_imports` row + `tiktok_live_reports` rows. Match counts correct.
- `POST /livehost/tiktok-imports/{import}/apply` with `session_ids=[..]` → updates sessions' `gmv_amount`, `gmv_source='tiktok_import'`, resnapshots commission (re-verify path).
- Unauthorized → 403.

**Step 2: Run → FAIL.**

**Step 3: Implementation**
- Upload stores file to `storage/app/tiktok-imports/`, dispatches `ProcessTiktokImportJob` (queued).
- Job parses + matches + persists.
- Apply action updates sessions + records variance-applied audit.

Routes:
```php
Route::post('tiktok-imports', [TiktokReportImportController::class, 'store']);
Route::get('tiktok-imports/{import}', [TiktokReportImportController::class, 'show']);
Route::post('tiktok-imports/{import}/apply', [TiktokReportImportController::class, 'apply']);
```

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): tiktok Live Analysis xlsx import + variance apply`.

### Task 37: Queue job — `ProcessTiktokImportJob`

**Files:**
- Create: `app/Jobs/ProcessTiktokImportJob.php`
- Test: `tests/Feature/LiveHost/Commission/ProcessTiktokImportJobTest.php`

**Step 1: Test** — dispatch job sync with a pending `TiktokReportImport` row → parser runs, records inserted, matcher runs, `matched_live_session_id` populated, status = `completed`.

**Step 2: Run → FAIL.**

**Step 3: Job** — implements `ShouldQueue`. Uses parser + matcher. On error sets `status=failed` + `error_log_json`.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): queued job to process tiktok imports`.

### Task 38: All Order import → auto-adjustment proposals

**Files:**
- Create: `app/Services/LiveHost/Tiktok/OrderRefundReconciler.php`
- Test: `tests/Feature/LiveHost/Commission/OrderRefundReconcilerTest.php`

**Step 1: Test** — given a `tiktok_orders` row with `order_refund_amount=120`, `cancelled_time=…`, matched to a live session, service proposes a `LiveSessionGmvAdjustment` row with `amount=-120`, status `proposed`, reason="Auto: order {id} refunded RM120".

**Step 2: Run → FAIL.**

**Step 3: Service** — matches orders to sessions by creator+time-overlap (order `created_time` within live session bounds + 12h grace). Creates adjustment rows in status `proposed` (add `status` column to adjustment table in Task 4 retroactively OR use a "proposed_by_auto" metadata field — chose the former when Task 4 was planned; if not present, add in this task's migration).

Sub-task 38a: Add `status` enum (`proposed|approved|rejected`) to `live_session_gmv_adjustments`. Adjustment is applied to `live_sessions.gmv_adjustment` only when `status=approved`. Existing manual adjustments in Task 15 default to `status=approved` for back-compat.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): auto-propose GMV adjustments from tiktok order refunds`.

### Task 39: TikTok Imports page (`/livehost/tiktok-imports`)

**Files:**
- Create: `resources/js/livehost/pages/tiktok-imports/Index.jsx`
- Create: `resources/js/livehost/pages/tiktok-imports/Show.jsx`
- Modify: sidebar nav — add "TikTok Imports" link.
- Test: `tests/Browser/LiveHost/TiktokImportBrowserTest.php`

**Step 1: Test (browser)**
- PIC uploads a Live Analysis xlsx.
- Waits for status `completed` (poll via Inertia partial reload).
- Reviews variance table (host GMV vs TikTok GMV).
- Clicks "Apply TikTok values" for selected rows → sessions updated.
- Uploads All Order xlsx → proposed adjustments appear for review.

**Step 2: Run → FAIL.**

**Step 3: UI** — upload drop-zone, progress indicator, two tabs in Show page: "Matched / Unmatched / Proposed Adjustments". Approve/reject buttons per proposed adjustment.

**Step 4: Run → PASS.**

**Step 5: Commit** — `feat(livehost): tiktok imports page with variance review`.

### Task 40: End-to-end golden flow

**Files:**
- Create: `tests/Feature/LiveHost/Commission/EndToEndReconciliationFlowTest.php`

**Step 1: Test**
1. Seed 3 hosts + April sessions with host-entered GMV.
2. PIC verifies all sessions.
3. Payroll draft generated — assert the "manual" numbers.
4. Upload Live Analysis xlsx fixture with slightly different GMVs.
5. Apply TikTok values → sessions re-snapshot.
6. Upload All Order xlsx fixture with some refunds.
7. Approve proposed adjustments.
8. Recompute payroll draft → numbers shift to TikTok-authoritative.
9. Lock payroll.
10. Assert: can no longer add adjustments on locked-period sessions.

**Step 2-4:** Should pass if earlier tasks are correct.

**Step 5: Commit** — `test(livehost): end-to-end reconciliation + payroll golden flow`.

---

## Post-v1 Deferred (not in this plan)

- Host-facing earnings view in Live Host Pocket (`/live-host/earnings`)
- TikTok Partner API sync job replacing xlsx imports
- Shopee platform rates and sync
- Mid-month salary prorating
- Minimum-GMV threshold policy
- Inactive-upline override forfeiture policy

---

## Execution Notes

- After each task: `vendor/bin/pint --dirty`, then commit.
- Full suite before each phase completion: `php artisan test --compact --filter=Commission`.
- If a task spans both MySQL + SQLite concerns, run migrations on a MySQL dev DB (`APP_DB=mysql php artisan migrate:fresh`) as a sanity check — do **not** run `migrate:fresh` on the real dev DB (see user memory `feedback_no_migrate_fresh`).
- Stop and ask before deviating from design (docs/plans/2026-04-19-livehost-commission-design.md).
