# Live Host Commission Tier System — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace flat per-platform commission % + flat host-level L1/L2 override % with per-host-per-platform GMV tier schedules. Each tier row drives internal %, L1 %, L2 % off a single monthly GMV lookup — one tier row, three wallets.

**Architecture:** New `live_host_platform_commission_tiers` table holds the schedule. Payroll calculator resolves a tier per (host, platform, month) and applies that row's three percentages to the downline's GMV. Existing flat rate columns are kept for the transition, read-only after cutover; a later migration drops them.

**Tech Stack:** Laravel 12 + Livewire/Flux (server side) • Inertia.js + React 19 (host detail page) • Pest 4 for tests • MySQL (prod) / SQLite (dev) — migrations must support both per project convention.

**Design doc:** [docs/plans/2026-04-24-live-host-commission-tiers-design.md](2026-04-24-live-host-commission-tiers-design.md)

---

## Behavior change to flag

Current `CommissionCalculator` + `LiveHostPayrollService::computeOverrideLevel()` compute upline overrides as `sum(downline.gmv_commission_myr) × upline.override_rate_l{1,2}_percent`. The new model computes overrides as `sum(downline.net_gmv_myr × downline_tier.l{1,2}_percent)` where the tier row comes from the **downline's** schedule, not the upline's profile. This is not just a shape refactor — it changes payout math. Existing hosts receive a backfilled tier with their current `internal_percent` so the internal-commission math is preserved during migration; the L1/L2 piece changes meaningfully the moment a real tier schedule is configured.

---

## Phase 1 — Data Model

### Task 1: Migration for the tiers table

**Files:**
- Create: `database/migrations/2026_04_24_000000_create_live_host_platform_commission_tiers_table.php`

**Step 1: Generate migration**

```bash
php artisan make:migration create_live_host_platform_commission_tiers_table --no-interaction
```

Rename the generated file to `2026_04_24_000000_create_live_host_platform_commission_tiers_table.php` so ordering is deterministic.

**Step 2: Write the migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_platform_commission_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->unsignedTinyInteger('tier_number');
            $table->decimal('min_gmv_myr', 12, 2);
            $table->decimal('max_gmv_myr', 12, 2)->nullable();
            $table->decimal('internal_percent', 5, 2);
            $table->decimal('l1_percent', 5, 2);
            $table->decimal('l2_percent', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['user_id', 'platform_id', 'tier_number', 'effective_from'],
                'lh_tier_unique'
            );
            $table->index(['user_id', 'platform_id', 'is_active'], 'lh_tier_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_platform_commission_tiers');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_04_24_000000_create_live_host_platform_commission_tiers_table`.

**Step 4: Commit**

```bash
git add database/migrations/2026_04_24_000000_create_live_host_platform_commission_tiers_table.php
git commit -m "feat(livehost): add commission tiers table"
```

---

### Task 2: Schema test for the new table

**Files:**
- Create: `tests/Feature/LiveHost/Commission/CommissionTierSchemaTest.php`

**Step 1: Write the failing test**

```php
<?php

use Illuminate\Support\Facades\Schema;

it('has the expected columns', function () {
    expect(Schema::hasTable('live_host_platform_commission_tiers'))->toBeTrue();

    $columns = [
        'id', 'user_id', 'platform_id', 'tier_number',
        'min_gmv_myr', 'max_gmv_myr',
        'internal_percent', 'l1_percent', 'l2_percent',
        'effective_from', 'effective_to', 'is_active',
        'created_at', 'updated_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('live_host_platform_commission_tiers', $column))
            ->toBeTrue("missing column: {$column}");
    }
});

it('enforces the tier uniqueness index', function () {
    $indexes = collect(Schema::getIndexes('live_host_platform_commission_tiers'))
        ->pluck('name');

    expect($indexes)->toContain('lh_tier_unique');
});
```

**Step 2: Run**

```bash
php artisan test --compact --filter=CommissionTierSchema
```

Expected: PASS — migration already ran in Task 1.

**Step 3: Commit**

```bash
git add tests/Feature/LiveHost/Commission/CommissionTierSchemaTest.php
git commit -m "test(livehost): add commission tier schema assertions"
```

---

### Task 3: Eloquent model `LiveHostPlatformCommissionTier`

**Files:**
- Create: `app/Models/LiveHostPlatformCommissionTier.php`
- Create: `database/factories/LiveHostPlatformCommissionTierFactory.php`

**Step 1: Generate model + factory**

```bash
php artisan make:model LiveHostPlatformCommissionTier --factory --no-interaction
```

**Step 2: Write the model**

Mirror the shape of `app/Models/LiveHostPlatformCommissionRate.php` for consistency.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostPlatformCommissionTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform_id',
        'tier_number',
        'min_gmv_myr',
        'max_gmv_myr',
        'internal_percent',
        'l1_percent',
        'l2_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tier_number' => 'integer',
            'min_gmv_myr' => 'decimal:2',
            'max_gmv_myr' => 'decimal:2',
            'internal_percent' => 'decimal:2',
            'l1_percent' => 'decimal:2',
            'l2_percent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

**Step 3: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveHostPlatformCommissionTierFactory extends Factory
{
    protected $model = LiveHostPlatformCommissionTier::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform_id' => Platform::factory(),
            'tier_number' => 1,
            'min_gmv_myr' => 0,
            'max_gmv_myr' => null,
            'internal_percent' => 6.00,
            'l1_percent' => 1.00,
            'l2_percent' => 2.00,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
```

**Step 4: Commit**

```bash
git add app/Models/LiveHostPlatformCommissionTier.php database/factories/LiveHostPlatformCommissionTierFactory.php
git commit -m "feat(livehost): add LiveHostPlatformCommissionTier model and factory"
```

---

### Task 4: Model relationship + scope tests

**Files:**
- Create: `tests/Feature/LiveHost/Commission/CommissionTierModelTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;

it('belongs to a host user and a platform', function () {
    $tier = LiveHostPlatformCommissionTier::factory()->create();

    expect($tier->user)->toBeInstanceOf(User::class);
    expect($tier->platform)->toBeInstanceOf(Platform::class);
});

it('scopes to active tiers', function () {
    LiveHostPlatformCommissionTier::factory()->create(['is_active' => true]);
    LiveHostPlatformCommissionTier::factory()->create(['is_active' => false]);

    expect(LiveHostPlatformCommissionTier::active()->count())->toBe(1);
});
```

**Step 2: Run**

```bash
php artisan test --compact --filter=CommissionTierModel
```

Expected: PASS.

**Step 3: Commit**

```bash
git add tests/Feature/LiveHost/Commission/CommissionTierModelTest.php
git commit -m "test(livehost): cover commission tier model relationships"
```

---

## Phase 2 — Domain Service

### Task 5: Tier resolver — failing test first

**Files:**
- Create: `tests/Feature/LiveHost/Commission/CommissionTierResolverTest.php`

**Step 1: Write tests covering the four cases from the design**

```php
<?php

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use App\Services\LiveHost\CommissionTierResolver;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->platform = Platform::factory()->create();
    $this->asOf = Carbon::parse('2026-04-15');

    // Seed a 3-tier schedule: 15-30k / 30-60k / 60k+
    $common = [
        'user_id' => $this->user->id,
        'platform_id' => $this->platform->id,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ];

    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 1, 'min_gmv_myr' => 15000, 'max_gmv_myr' => 30000,
        'internal_percent' => 6.00, 'l1_percent' => 1.00, 'l2_percent' => 2.00,
    ]);
    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 60000,
        'internal_percent' => 6.00, 'l1_percent' => 1.30, 'l2_percent' => 2.30,
    ]);
    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 3, 'min_gmv_myr' => 60000, 'max_gmv_myr' => null,
        'internal_percent' => 6.00, 'l1_percent' => 1.50, 'l2_percent' => 2.50,
    ]);
});

it('returns null when gmv is below tier 1 floor', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 8000, $this->asOf);

    expect($tier)->toBeNull();
});

it('resolves the tier at the lower boundary (inclusive)', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 30000, $this->asOf);

    expect($tier->tier_number)->toBe(2);
});

it('resolves the tier below the upper boundary (exclusive)', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 29999.99, $this->asOf);

    expect($tier->tier_number)->toBe(1);
});

it('resolves the open-ended top tier for very large gmv', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 500000, $this->asOf);

    expect($tier->tier_number)->toBe(3);
});

it('ignores tiers outside their effective window', function () {
    LiveHostPlatformCommissionTier::query()
        ->where('user_id', $this->user->id)
        ->update(['effective_to' => '2026-03-31', 'is_active' => false]);

    $resolver = app(CommissionTierResolver::class);
    $tier = $resolver->resolveTier($this->user, $this->platform, 50000, $this->asOf);

    expect($tier)->toBeNull();
});
```

**Step 2: Run — expect failure**

```bash
php artisan test --compact --filter=CommissionTierResolver
```

Expected: FAIL — `Target class [App\Services\LiveHost\CommissionTierResolver] does not exist`.

**Step 3: Commit failing test**

```bash
git add tests/Feature/LiveHost/Commission/CommissionTierResolverTest.php
git commit -m "test(livehost): specify commission tier resolver behavior"
```

---

### Task 6: Implement `CommissionTierResolver`

**Files:**
- Create: `app/Services/LiveHost/CommissionTierResolver.php`

**Step 1: Implement**

```php
<?php

namespace App\Services\LiveHost;

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Carbon\CarbonInterface;

class CommissionTierResolver
{
    public function resolveTier(User $host, Platform $platform, float $monthlyGmv, CarbonInterface $asOf): ?LiveHostPlatformCommissionTier
    {
        return LiveHostPlatformCommissionTier::query()
            ->where('user_id', $host->id)
            ->where('platform_id', $platform->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOf);
            })
            ->where('min_gmv_myr', '<=', $monthlyGmv)
            ->where(function ($q) use ($monthlyGmv) {
                $q->whereNull('max_gmv_myr')->orWhere('max_gmv_myr', '>', $monthlyGmv);
            })
            ->orderByDesc('tier_number')
            ->first();
    }
}
```

**Step 2: Run**

```bash
php artisan test --compact --filter=CommissionTierResolver
```

Expected: PASS (all 5 tests).

**Step 3: Pint**

```bash
vendor/bin/pint --dirty
```

**Step 4: Commit**

```bash
git add app/Services/LiveHost/CommissionTierResolver.php
git commit -m "feat(livehost): resolve commission tier by gmv and effective window"
```

---

## Phase 3 — Payroll Integration

Read before starting this phase:
- [app/Services/LiveHost/CommissionCalculator.php](../../app/Services/LiveHost/CommissionCalculator.php) — `forSession()` L44-82 is where `gmv_commission` is computed with the flat rate
- [app/Services/LiveHost/LiveHostPayrollService.php](../../app/Services/LiveHost/LiveHostPayrollService.php) — `computeOwnAggregates()` L237-277 and `computeOverrideLevel()` L290-320 are the two call sites

### Task 7: Regression characterization test for current payroll math

Before changing payroll behavior, lock in the current math with a golden test so any semantic shift is visible. Read [tests/Feature/LiveHost/Commission/PayrollGoldenPathTest.php](../../tests/Feature/LiveHost/Commission/PayrollGoldenPathTest.php) and check whether it already covers the override calculation. If yes, skip this task. If no:

**Files:**
- Modify: `tests/Feature/LiveHost/Commission/PayrollGoldenPathTest.php` (append a test) OR create `PayrollOverrideCurrentBehaviorTest.php`

**Step 1: Add a test that asserts the current `override = gmv_commission × override_rate` math** for a 2-level tree, pinning monetary values exactly.

**Step 2: Run and confirm PASS on the current codebase.**

**Step 3: Commit**

```bash
git add tests/Feature/LiveHost/Commission/*
git commit -m "test(livehost): pin current override payroll math before tier refactor"
```

---

### Task 8: Rewrite `CommissionCalculator::forSession()` to use tier lookup

**Files:**
- Modify: `app/Services/LiveHost/CommissionCalculator.php` around lines 44-82

**Step 1: Add a failing test** in `tests/Feature/LiveHost/Commission/CommissionCalculatorPerSessionTest.php`:

```php
it('uses tier internal_percent when a matching tier exists', function () {
    // seed tier 2 (30k-60k, internal 6%) for the host/platform
    // seed a session with 40k GMV
    // expect gmv_commission_myr == 2400.00 (6% of 40000)
});

it('returns zero gmv_commission when monthly gmv is below tier 1 floor', function () {
    // seed tier 1 (15k-30k) only
    // seed a session whose monthly total is 10k
    // expect gmv_commission_myr == 0
});
```

Note: `forSession()` currently uses `resolveRateAt()` on a per-session basis with `platform_rate_percent`. To support tier lookup the calculator needs the host's **monthly GMV total** for the platform, not just this session's GMV. Decision: expose a new method `forSessionInMonthlyContext(Session $session, float $monthlyGmvForPlatform, CarbonInterface $asOf)` and call it from `LiveHostPayrollService::computeOwnAggregates()` after it has already accumulated the monthly total. Keep `forSession()` deprecated but working for other callers (browser log viewer, etc.) during transition.

**Step 2: Run test — expect FAIL.**

**Step 3: Implement** — inject `CommissionTierResolver` into the constructor, add the new method, compute `gmv_commission = monthlyGmv × tier.internal_percent / 100` when a tier matches; zero otherwise. Populate `rate_source` with `tier_id` + `internal_percent` for audit.

**Step 4: Run test — PASS. Run the existing `CommissionCalculatorPerSessionTest` — all still PASS.**

**Step 5: Commit**

```bash
git add app/Services/LiveHost/CommissionCalculator.php tests/Feature/LiveHost/Commission/CommissionCalculatorPerSessionTest.php
git commit -m "feat(livehost): compute session gmv commission from tier internal percent"
```

---

### Task 9: Rewrite override calculation in `LiveHostPayrollService`

**Files:**
- Modify: `app/Services/LiveHost/LiveHostPayrollService.php` — `computeOverrideLevel()` L290-320

**Step 1: Add failing tests** in `tests/Feature/LiveHost/Commission/PayrollOverrideTierTest.php`:

```php
it('pays L1 override from downline tier l1_percent', function () {
    // Host A (downline) has tier 2 on TikTok: 30k-60k → 6/1.3/2.3
    // Host A sells 40k on TikTok in the month
    // Host B is A's upline
    // Expect B's override_l1_myr == 40000 × 1.3% = 520.00
});

it('pays L2 override from downline tier l2_percent', function () {
    // Similar, verify L2 payout
});

it('generates zero override when downline is below tier 1', function () {
    // Downline sells 8k (below 15k floor)
    // Upline override == 0
});

it('separates override by platform', function () {
    // Downline sells 40k on TikTok AND 20k on Shopee (different tier schedules)
    // Upline override = TikTok piece + Shopee piece independently
});
```

**Step 2: Run — expect FAIL.**

**Step 3: Implement the new override logic.** For each upline, iterate the downline's platforms; for each platform, look up the downline's tier for that month's platform GMV; multiply `net_gmv × tier.l{1,2}_percent` and accumulate. Persist the tier IDs used inside the `calculation_breakdown_json` on the payroll item.

Pseudocode for `computeOverrideLevel($run, int $level)`:

```php
foreach ($runItems as $uplineItem) {
    $directDownlines = User::where('...upline_user_id', $uplineItem->user_id)->get();
    $overrideTotal = 0;
    $breakdown = [];

    foreach ($directDownlines as $downline) {
        foreach ($downline->platformsWithGmvInPeriod($run) as $platform) {
            $monthlyGmv = $this->sumDownlineGmv($downline, $platform, $run);
            $tier = $this->tierResolver->resolveTier($downline, $platform, $monthlyGmv, $run->period_end);
            if ($tier === null) continue;

            $percent = $level === 1 ? $tier->l1_percent : $tier->l2_percent;
            $amount = round($monthlyGmv * $percent / 100, 2);
            $overrideTotal += $amount;
            $breakdown[] = compact('downline', 'platform', 'monthlyGmv', 'tier_id', 'percent', 'amount');
        }
    }

    $uplineItem->{$level === 1 ? 'override_l1_myr' : 'override_l2_myr'} = $overrideTotal;
    $uplineItem->calculation_breakdown_json['overrides_received'][] = $breakdown;
    $uplineItem->save();
}
```

**Step 4: Run tier override tests + existing `PayrollOverrideCurrentBehaviorTest` from Task 7** — the current-behavior test is expected to FAIL now. Update it to match the new expected values based on backfilled Tier 1 (internal % = old rate, L1/L2 still 0 for hosts who haven't configured tiers yet), or delete it and keep only the tier tests. Document which you chose in the commit message.

**Step 5: Run full commission test suite**

```bash
php artisan test --compact tests/Feature/LiveHost/Commission
```

Expected: all green.

**Step 6: Commit**

```bash
git add app/Services/LiveHost/LiveHostPayrollService.php tests/Feature/LiveHost/Commission/
git commit -m "feat(livehost): derive L1/L2 overrides from downline tier percentages"
```

---

### Task 10: Backfill existing hosts with a single-tier schedule

**Files:**
- Create: `database/migrations/2026_04_24_000100_backfill_live_host_commission_tiers.php`

**Step 1: Write the migration** (data-only, idempotent):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rates = DB::table('live_host_platform_commission_rates')->get();

        foreach ($rates as $rate) {
            $profile = DB::table('live_host_commission_profiles')
                ->where('user_id', $rate->user_id)
                ->where('is_active', true)
                ->first();

            $exists = DB::table('live_host_platform_commission_tiers')
                ->where('user_id', $rate->user_id)
                ->where('platform_id', $rate->platform_id)
                ->where('effective_from', $rate->effective_from)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('live_host_platform_commission_tiers')->insert([
                'user_id' => $rate->user_id,
                'platform_id' => $rate->platform_id,
                'tier_number' => 1,
                'min_gmv_myr' => 0,
                'max_gmv_myr' => null,
                'internal_percent' => $rate->commission_rate_percent,
                'l1_percent' => $profile->override_rate_l1_percent ?? 0,
                'l2_percent' => $profile->override_rate_l2_percent ?? 0,
                'effective_from' => $rate->effective_from,
                'effective_to' => $rate->effective_to,
                'is_active' => $rate->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: leave backfilled rows in place on rollback.
    }
};
```

**Step 2: Add a test** `tests/Feature/LiveHost/Commission/CommissionTierBackfillTest.php` that seeds a `live_host_platform_commission_rates` row + profile, runs the migration via `Artisan::call('migrate')`... **BUT** — the migration runs once at install time. Instead test the backfill logic by extracting the loop body into an invokable class `App\Services\LiveHost\BackfillTiersFromFlatRates` and call it from the migration. Then the test can call the class directly.

**Step 3: Refactor migration to delegate to the service, run test, PASS.**

**Step 4: Run `php artisan migrate`** in dev to backfill the local DB.

**Step 5: Commit**

```bash
git add database/migrations/2026_04_24_000100_backfill_live_host_commission_tiers.php \
        app/Services/LiveHost/BackfillTiersFromFlatRates.php \
        tests/Feature/LiveHost/Commission/CommissionTierBackfillTest.php
git commit -m "feat(livehost): backfill single-tier schedule from legacy flat rates"
```

---

## Phase 4 — API Surface

### Task 11: FormRequests for tier CRUD

**Files:**
- Create: `app/Http/Requests/LiveHost/StoreCommissionTierScheduleRequest.php`
- Create: `app/Http/Requests/LiveHost/UpdateCommissionTierRequest.php`

**Step 1: Write validation tests first** — `tests/Feature/LiveHost/Commission/StoreCommissionTierScheduleRequestTest.php`:

- Rejects overlapping ranges in the same schedule.
- Rejects non-contiguous tier numbers.
- Rejects gaps between ranges.
- Rejects percentages > 100.
- Rejects `max_gmv_myr <= min_gmv_myr`.
- Accepts the canonical 5-tier schedule from the design.
- Accepts a schedule where only the final tier has `max_gmv_myr = null`.

**Step 2: Implement `StoreCommissionTierScheduleRequest`**

```php
public function rules(): array
{
    return [
        'platform_id' => ['required', 'integer', 'exists:platforms,id'],
        'effective_from' => ['required', 'date'],
        'tiers' => ['required', 'array', 'min:1'],
        'tiers.*.min_gmv_myr' => ['required', 'numeric', 'min:0'],
        'tiers.*.max_gmv_myr' => ['nullable', 'numeric', 'min:0', 'gt:tiers.*.min_gmv_myr'],
        'tiers.*.internal_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        'tiers.*.l1_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        'tiers.*.l2_percent' => ['required', 'numeric', 'min:0', 'max:100'],
    ];
}

public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $tiers = collect($this->input('tiers', []))->values();

        // Non-overlapping + contiguous check
        $sorted = $tiers->sortBy('min_gmv_myr')->values();
        foreach ($sorted as $i => $tier) {
            if ($i === 0) continue;
            $prev = $sorted[$i - 1];
            if ((float)$tier['min_gmv_myr'] !== (float)$prev['max_gmv_myr']) {
                $validator->errors()->add('tiers', 'Tier ranges must be contiguous with no gaps or overlaps.');
                break;
            }
        }

        // Only last tier may have null max_gmv_myr
        $nullMaxCount = $tiers->whereNull('max_gmv_myr')->count();
        if ($nullMaxCount > 1) {
            $validator->errors()->add('tiers', 'Only the highest tier may be open-ended.');
        }
    });
}
```

**Step 3: Run — PASS.**

**Step 4: Commit**

```bash
git add app/Http/Requests/LiveHost/ tests/Feature/LiveHost/Commission/StoreCommissionTierScheduleRequestTest.php
git commit -m "feat(livehost): validate commission tier schedule payloads"
```

---

### Task 12: Controller actions on `HostController`

**Files:**
- Modify: `app/Http/Controllers/LiveHost/HostController.php`
- Modify: `routes/web.php` lines 241-249 (add new routes next to existing platform-rate routes)

**Step 1: Feature test first** — `tests/Feature/LiveHost/Commission/HostTierControllerTest.php`:

- `POST /livehost/hosts/{host}/platforms/{platform}/tiers` with a valid 5-tier payload creates 5 tier rows tied to that host+platform; returns Inertia redirect with flash success.
- `PATCH /livehost/hosts/{host}/tiers/{tier}` updates a single row's percentages.
- `DELETE /livehost/hosts/{host}/tiers/{tier}` archives the tier (sets `is_active = false`, `effective_to = today`). Prevent deleting mid-schedule tiers.
- Unauthorized users (no `admin_livehost` role) get 403.

**Step 2: Run — FAIL (missing routes).**

**Step 3: Add routes in `routes/web.php`** within the existing LiveHost group (around line 249):

```php
Route::post('/hosts/{host}/platforms/{platform}/tiers', [HostController::class, 'storeTierSchedule'])
    ->name('hosts.tiers.store');
Route::patch('/hosts/{host}/tiers/{tier}', [HostController::class, 'updateTier'])
    ->name('hosts.tiers.update');
Route::delete('/hosts/{host}/tiers/{tier}', [HostController::class, 'destroyTier'])
    ->name('hosts.tiers.destroy');
```

Use route-model binding: add a binding for `{tier}` → `LiveHostPlatformCommissionTier` if not implicit.

**Step 4: Add the three controller methods to `HostController`** — standard CRUD against `LiveHostPlatformCommissionTier`, returning `Inertia::location()` or `back()->with('success', ...)` to match the existing pattern in `storePlatformRate()`.

**Step 5: Run all tier tests** — PASS.

**Step 6: Commit**

```bash
git add app/Http/Controllers/LiveHost/HostController.php routes/web.php tests/Feature/LiveHost/Commission/HostTierControllerTest.php
git commit -m "feat(livehost): add commission tier CRUD endpoints"
```

---

### Task 13: Update `HostController::show()` to load tiers

**Files:**
- Modify: `app/Http/Controllers/LiveHost/HostController.php` — `show()` method L94-207

**Step 1: Feature test** — `tests/Feature/LiveHost/Commission/HostShowTierPropTest.php`:

```php
it('passes grouped commission tier schedules to Inertia', function () {
    // Seed host with 2 platforms, each with a 3-tier schedule
    // GET the show page
    // Assert Inertia prop `commissionTiers` is shape:
    //   [
    //     { platform_id: X, platform: {...}, effective_from: '...', tiers: [...] },
    //     ...
    //   ]
});
```

**Step 2: Run — FAIL.**

**Step 3: Update `show()`** to load tiers grouped by platform + effective_from:

```php
$commissionTiers = LiveHostPlatformCommissionTier::query()
    ->where('user_id', $host->id)
    ->where('is_active', true)
    ->with('platform')
    ->orderBy('platform_id')
    ->orderBy('tier_number')
    ->get()
    ->groupBy(fn ($t) => $t->platform_id . '|' . $t->effective_from->toDateString())
    ->map(fn ($group) => [
        'platform_id' => $group->first()->platform_id,
        'platform' => $group->first()->platform,
        'effective_from' => $group->first()->effective_from->toDateString(),
        'tiers' => $group->values()->all(),
    ])
    ->values();
```

Pass `commissionTiers` in the `Inertia::render()` props array.

**Step 4: Run — PASS.**

**Step 5: Commit**

```bash
git add app/Http/Controllers/LiveHost/HostController.php tests/Feature/LiveHost/Commission/HostShowTierPropTest.php
git commit -m "feat(livehost): load commission tier schedules on host show page"
```

---

## Phase 5 — UI (React / Inertia)

Read before starting this phase:
- [resources/js/livehost/pages/hosts/Show.jsx](../../resources/js/livehost/pages/hosts/Show.jsx) — sections 02 (L585-622) and 03 (L624-656) are being replaced
- [resources/js/livehost/pages/hosts/Show.jsx](../../resources/js/livehost/pages/hosts/Show.jsx) — `<LiveField/>` pattern around L400s is how inline editing is wired

### Task 14: New `CommissionTierTable` React component

**Files:**
- Create: `resources/js/livehost/components/CommissionTierTable.jsx`

**Step 1: Build a pure presentational component** with these props:

```js
{
  platform, // { id, name }
  effectiveFrom, // '2026-04-01'
  tiers, // array of { id, tier_number, min_gmv_myr, max_gmv_myr, internal_percent, l1_percent, l2_percent }
  onEditRow, // (tierId, patch) => void
  onAddTier, // () => void
  onRemoveTier, // (tierId) => void (only allowed on the top tier)
  readOnly, // boolean
}
```

Layout: columns `Tier | Monthly GMV (min – max) | Internal % | L1 % | L2 %`. Inline `<LiveField/>` on each numeric cell. "∞" shown when `max_gmv_myr` is null. "+ Add tier" button at the bottom.

**Step 2: Storybook-free visual check** — run `composer run dev`, navigate to a seeded host's detail page, confirm the component renders when wired up in the next task (this task is code-only).

**Step 3: Commit**

```bash
git add resources/js/livehost/components/CommissionTierTable.jsx
git commit -m "feat(livehost): add commission tier table React component"
```

---

### Task 15: Replace sections 02 and 03 with one "Commission tiers" section

**Files:**
- Modify: `resources/js/livehost/pages/hosts/Show.jsx` — replace L585-656 (old sections 02 + 03) with a new `CommissionTiersSection` that renders one `<CommissionTierTable/>` per platform.

**Step 1: Wire up the new section** to call `router.patch` / `router.post` / `router.delete` against the new routes added in Task 12.

**Step 2: Remove the old `override_rate_l1_percent` / `override_rate_l2_percent` inline editors** (section 03) and the old per-platform flat % editor (section 02).

**Step 3: Renumber the remaining sections** so the ledger stays 01 / 02 / 03 / PROJ.

**Step 4: Build assets + visual check**

```bash
npm run build
```

Open the host detail page in a browser. Confirm the old Performance pay + Override earnings sections are gone and replaced by Commission tiers. Edit a cell, see it persist.

**Step 5: Commit**

```bash
git add resources/js/livehost/pages/hosts/Show.jsx
git commit -m "feat(livehost): replace Performance pay + Override earnings with tier table UI"
```

---

### Task 16: Update Monthly projection to use tier lookup

**Files:**
- Modify: `resources/js/livehost/pages/hosts/Show.jsx` — `MonthlyProjection` function L1037-1099

**Step 1: Add helper** `resolveTierClientSide(gmv, tiers) => tier | null` in the same file or a new `resources/js/livehost/utils/commissionTier.js`. Mirror the resolver logic (min inclusive, max exclusive, null max open-ended).

**Step 2: Rewire the projection slider UI**

- Each platform's GMV slider shows a tier badge ("Tier 3 — 60K–100K") when resolved, or a muted "Below Tier 1" state when below the lowest floor.
- Breakdown card shows three lines: host earnings (`internal% × gmv`), generated L1 upline override (`l1% × gmv`), generated L2 upline override (`l2% × gmv`) — the last two labeled as informational since they don't factor into this host's take-home.
- When below Tier 1 floor, all three values are shown as zero.

**Step 3: Build + visual check**

```bash
npm run build
```

Drag the slider through tier boundaries. Confirm the badge + earning values snap to the right tier.

**Step 4: Commit**

```bash
git add resources/js/livehost/pages/hosts/Show.jsx resources/js/livehost/utils/commissionTier.js
git commit -m "feat(livehost): show tier badge and payouts on monthly projection slider"
```

---

## Phase 6 — Verification

### Task 17: End-to-end browser test

**Files:**
- Create: `tests/Browser/LiveHost/CommissionTierFlowTest.php`

**Step 1: Write a Pest 4 browser test** that:

1. Logs in as an admin_livehost user.
2. Navigates to a seeded host's detail page.
3. Adds a tier schedule for TikTok Shop (5 rows matching the spreadsheet).
4. Asserts the rows appear in the UI.
5. Moves the monthly projection GMV slider to 80K.
6. Asserts the tier badge reads "Tier 3 — 60K–100K" and the host earning reads RM 4,800 (6% of 80K).
7. Moves the slider to 8K.
8. Asserts "Below Tier 1" state and RM 0 earning.

**Step 2: Run**

```bash
php artisan test --compact tests/Browser/LiveHost/CommissionTierFlowTest.php
```

Expected: PASS.

**Step 3: Commit**

```bash
git add tests/Browser/LiveHost/CommissionTierFlowTest.php
git commit -m "test(livehost): browse the full commission tier flow end-to-end"
```

---

### Task 18: Full test sweep + Pint

**Step 1: Full suite**

```bash
php artisan test --compact
```

Expected: all green. If any unrelated test broke, triage.

**Step 2: Pint**

```bash
vendor/bin/pint --dirty
```

**Step 3: If Pint made changes, commit them**

```bash
git commit -am "style: pint"
```

---

## Deferred / Out of scope

These are in the design doc's Open Questions and should NOT be done in this plan:

- Dropping deprecated columns (`commission_rate_percent`, `override_rate_l1_percent`, `override_rate_l2_percent`). Leave them read-only for the transition period; a follow-up migration drops them once production confirms the tier system is behaving.
- Template / copy-from-another-host for tier schedules.
- L3+ override depth.
- Tier locks (carry-over tier for a few months).

---

## Build sequence summary

```
Phase 1 (data) → Phase 2 (resolver) → Phase 3 (payroll math)
                                            ↓
Phase 4 (API) ← Phase 3 complete ──────────┘
    ↓
Phase 5 (UI)
    ↓
Phase 6 (verify)
```

Each task commits on completion. If a test in a later phase reveals a defect in an earlier phase, fix in place with a new commit rather than amending; keep history clean.
