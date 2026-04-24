<?php

use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveHostPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Seed N verified live sessions for $host totaling $totalGmv with a single
 * adjustment of $totalAdjustment applied to session #0. Sessions are spread
 * across April 2026 at one-hour increments starting 2026-04-15 08:00 so they
 * all fall within the payroll period.
 *
 * We call saveQuietly() after the first save because the
 * LiveSessionVerifiedObserver only fires when verification_status becomes
 * dirty. We save twice on purpose so the observer writes the snapshot.
 */
function seedSessionsForHost(User $host, Platform $platform, int $count, float $totalGmv, float $totalAdjustment = 0.0): \Illuminate\Support\Collection
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);

    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);

    $perSessionGmv = round($totalGmv / $count, 2);

    $sessions = collect();
    for ($i = 0; $i < $count; $i++) {
        $start = Carbon::parse('2026-04-15 08:00:00')->addHours($i);
        $end = $start->copy()->addMinutes(50);

        $session = LiveSession::factory()->create([
            'platform_account_id' => $account->id,
            'live_host_platform_account_id' => $pivot->id,
            'live_host_id' => $host->id,
            'status' => 'ended',
            'verification_status' => 'pending',
            'scheduled_start_at' => $start,
            'actual_start_at' => $start,
            'actual_end_at' => $end,
            'duration_minutes' => 50,
            'gmv_amount' => $perSessionGmv,
            'gmv_adjustment' => $i === 0 ? $totalAdjustment : 0,
        ]);

        // Trigger observer to lock the GMV and write commission_snapshot_json.
        $session->verification_status = 'verified';
        $session->save();

        $sessions->push($session->fresh());
    }

    return $sessions;
}

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $this->sarah = User::where('email', 'sarah@livehost.com')->first();
    $this->amin = User::where('email', 'amin@livehost.com')->first();
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
});

it('generates payroll matching the design §5.3 worked example', function () {
    seedSessionsForHost($this->ahmad, $this->tiktok, 8, 12000, -200);
    seedSessionsForHost($this->sarah, $this->tiktok, 12, 18000, -500);
    seedSessionsForHost($this->amin, $this->tiktok, 10, 22000, -300);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    expect($run->status)->toBe('draft');
    expect($run->items)->toHaveCount(3);
    expect($run->cutoff_date->toDateString())->toBe('2026-05-14');

    $items = $run->items->keyBy('user_id');

    // Ahmad — top of chain. Under the zero-override backfill strategy used
    // by the seeder, every host's tier row has l1_percent = l2_percent = 0,
    // so Ahmad's L1 (Sarah) and L2 (Amin) overrides are both 0.00 even
    // though Sarah and Amin have real monthly GMV. `internal_percent` is
    // unchanged, so Ahmad's own GMV commission (472) and per-live (240) are
    // the same as before the tier refactor.
    $ahmadItem = $items->get($this->ahmad->id);
    expect((float) $ahmadItem->base_salary_myr)->toEqual(2000.00);
    expect((int) $ahmadItem->sessions_count)->toEqual(8);
    expect((float) $ahmadItem->total_gmv_myr)->toEqual(12000.00);
    expect((float) $ahmadItem->total_gmv_adjustment_myr)->toEqual(-200.00);
    expect((float) $ahmadItem->net_gmv_myr)->toEqual(11800.00);
    expect((float) $ahmadItem->gmv_commission_myr)->toEqual(472.00);
    expect((float) $ahmadItem->total_per_live_myr)->toEqual(240.00);
    expect((float) $ahmadItem->override_l1_myr)->toEqual(0.00);
    expect((float) $ahmadItem->override_l2_myr)->toEqual(0.00);
    expect((float) $ahmadItem->gross_total_myr)->toEqual(2712.00);
    expect((float) $ahmadItem->net_payout_myr)->toEqual(2712.00);

    // Sarah — L1 under Ahmad, has Amin as L1 downline. Amin's tier L1 is 0%
    // under zero-override backfill, so Sarah earns no override.
    $sarahItem = $items->get($this->sarah->id);
    expect((float) $sarahItem->base_salary_myr)->toEqual(1800.00);
    expect((int) $sarahItem->sessions_count)->toEqual(12);
    expect((float) $sarahItem->net_gmv_myr)->toEqual(17500.00);
    expect((float) $sarahItem->gmv_commission_myr)->toEqual(875.00);
    expect((float) $sarahItem->total_per_live_myr)->toEqual(300.00);
    expect((float) $sarahItem->override_l1_myr)->toEqual(0.00);
    expect((float) $sarahItem->override_l2_myr)->toEqual(0.00);
    expect((float) $sarahItem->net_payout_myr)->toEqual(2975.00);

    // Amin — L2 under Ahmad, no downlines
    $aminItem = $items->get($this->amin->id);
    expect((float) $aminItem->base_salary_myr)->toEqual(0.00);
    expect((int) $aminItem->sessions_count)->toEqual(10);
    expect((float) $aminItem->net_gmv_myr)->toEqual(21700.00);
    expect((float) $aminItem->gmv_commission_myr)->toEqual(1302.00);
    expect((float) $aminItem->total_per_live_myr)->toEqual(500.00);
    expect((float) $aminItem->override_l1_myr)->toEqual(0.00);
    expect((float) $aminItem->override_l2_myr)->toEqual(0.00);
    expect((float) $aminItem->net_payout_myr)->toEqual(1802.00);

    // Aggregate total (zero overrides: 2712 + 2975 + 1802 = 7489)
    $total = $run->items->sum(fn ($i) => (float) $i->net_payout_myr);
    expect(round($total, 2))->toEqual(7489.00);
});

it('ignores unverified sessions', function () {
    // Create one verified session (observer fires) and one pending session.
    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->tiktok->id,
        'user_id' => $this->ahmad->id,
    ]);
    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $this->ahmad->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);

    $shared = [
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $this->ahmad->id,
        'status' => 'ended',
        'scheduled_start_at' => Carbon::parse('2026-04-10 10:00'),
        'actual_start_at' => Carbon::parse('2026-04-10 10:00'),
        'actual_end_at' => Carbon::parse('2026-04-10 10:50'),
        'duration_minutes' => 50,
        'gmv_amount' => 1000,
        'gmv_adjustment' => 0,
    ];

    $verified = LiveSession::factory()->create(array_merge($shared, ['verification_status' => 'pending']));
    $verified->verification_status = 'verified';
    $verified->save();

    LiveSession::factory()->create(array_merge($shared, [
        'verification_status' => 'pending',
        'scheduled_start_at' => Carbon::parse('2026-04-11 10:00'),
        'actual_start_at' => Carbon::parse('2026-04-11 10:00'),
        'actual_end_at' => Carbon::parse('2026-04-11 10:50'),
    ]));

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $ahmadItem = $run->items->firstWhere('user_id', $this->ahmad->id);
    expect((int) $ahmadItem->sessions_count)->toBe(1);
    expect((float) $ahmadItem->net_gmv_myr)->toBe(1000.00);
});

it('ignores sessions outside the period', function () {
    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->tiktok->id,
        'user_id' => $this->ahmad->id,
    ]);
    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $this->ahmad->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);

    // March session — outside April run
    $marchSession = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $this->ahmad->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'scheduled_start_at' => Carbon::parse('2026-03-15 10:00'),
        'actual_start_at' => Carbon::parse('2026-03-15 10:00'),
        'actual_end_at' => Carbon::parse('2026-03-15 10:50'),
        'duration_minutes' => 50,
        'gmv_amount' => 5000,
        'gmv_adjustment' => 0,
    ]);
    $marchSession->verification_status = 'verified';
    $marchSession->save();

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $ahmadItem = $run->items->firstWhere('user_id', $this->ahmad->id);
    expect((int) $ahmadItem->sessions_count)->toBe(0);
    expect((float) $ahmadItem->net_gmv_myr)->toBe(0.00);
    // Base salary still applies
    expect((float) $ahmadItem->base_salary_myr)->toBe(2000.00);
});

it('hosts with no sessions still get an item row with only base_salary', function () {
    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    expect($run->items)->toHaveCount(3);

    $ahmadItem = $run->items->firstWhere('user_id', $this->ahmad->id);
    expect((float) $ahmadItem->base_salary_myr)->toBe(2000.00);
    expect((int) $ahmadItem->sessions_count)->toBe(0);
    expect((float) $ahmadItem->gmv_commission_myr)->toBe(0.00);
    expect((float) $ahmadItem->total_per_live_myr)->toBe(0.00);
    expect((float) $ahmadItem->override_l1_myr)->toBe(0.00);
    expect((float) $ahmadItem->override_l2_myr)->toBe(0.00);
    expect((float) $ahmadItem->net_payout_myr)->toBe(2000.00);
});

it('runs do not conflict across periods', function () {
    // April
    seedSessionsForHost($this->ahmad, $this->tiktok, 2, 2000);

    // May
    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->tiktok->id,
        'user_id' => $this->ahmad->id,
    ]);
    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $this->ahmad->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);
    $mayStart = Carbon::parse('2026-05-10 10:00');
    $s = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $this->ahmad->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'scheduled_start_at' => $mayStart,
        'actual_start_at' => $mayStart,
        'actual_end_at' => $mayStart->copy()->addMinutes(50),
        'duration_minutes' => 50,
        'gmv_amount' => 500,
        'gmv_adjustment' => 0,
    ]);
    $s->verification_status = 'verified';
    $s->save();

    $aprilRun = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $mayRun = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-05-01'),
        Carbon::parse('2026-05-31')->endOfDay(),
        $this->pic,
    );

    expect(LiveHostPayrollRun::count())->toBe(2);

    $aprilAhmad = $aprilRun->items->firstWhere('user_id', $this->ahmad->id);
    $mayAhmad = $mayRun->items->firstWhere('user_id', $this->ahmad->id);

    expect((int) $aprilAhmad->sessions_count)->toBe(2);
    expect((int) $mayAhmad->sessions_count)->toBe(1);
    expect((float) $mayAhmad->net_gmv_myr)->toBe(500.00);
});

it('stores a structured calculation breakdown for each item', function () {
    seedSessionsForHost($this->ahmad, $this->tiktok, 3, 3000);
    seedSessionsForHost($this->sarah, $this->tiktok, 2, 2000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $ahmadItem = $run->items->firstWhere('user_id', $this->ahmad->id);
    $breakdown = $ahmadItem->calculation_breakdown_json;

    expect($breakdown)->toBeArray();
    expect($breakdown)->toHaveKeys(['sessions', 'overrides_l1', 'overrides_l2']);
    expect($breakdown['sessions'])->toHaveCount(3);

    // Under zero-override backfill, Sarah's tier has l1_percent = 0, so
    // Ahmad's L1 breakdown still emits a diagnostic row for her with
    // reason = 'zero_rate_in_tier' and override_amount = 0.00.
    expect($breakdown['overrides_l1'])->not->toBeEmpty();
    expect($breakdown['overrides_l1'][0])->toHaveKeys([
        'downline_user_id', 'downline_name', 'platform_id', 'monthly_gmv_myr',
        'tier_id', 'tier_number', 'override_rate_percent', 'override_amount',
        'reason',
    ]);
    expect($breakdown['overrides_l1'][0]['downline_user_id'])->toBe($this->sarah->id);
    expect($breakdown['overrides_l1'][0]['reason'])->toBe('zero_rate_in_tier');
    expect((float) $breakdown['overrides_l1'][0]['override_amount'])->toEqual(0.00);
});
