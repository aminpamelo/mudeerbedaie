<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveHostPlatformCommissionTier;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveHostPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Shared three-tier schedule used by the override tests.
 *
 *   Tier 1: 15k–30k  — internal 6.00, l1 1.00, l2 2.00
 *   Tier 2: 30k–60k  — internal 6.00, l1 1.30, l2 2.30
 *   Tier 3: 60k+     — internal 6.00, l1 1.50, l2 2.50
 */
function tierOverrideSeedTiers(User $host, Platform $platform): void
{
    $common = [
        'user_id' => $host->id,
        'platform_id' => $platform->id,
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
}

/**
 * Seed N verified April 2026 sessions for the host on the given platform.
 * Splits $totalGmv evenly across sessions; all sessions are inside the
 * April payroll period window.
 */
function seedTierOverrideSessions(User $host, Platform $platform, int $count, float $totalGmv): void
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

    $perSession = round($totalGmv / $count, 2);

    for ($i = 0; $i < $count; $i++) {
        $start = Carbon::parse('2026-04-15 08:00:00')->addHours($i);
        $session = LiveSession::factory()->create([
            'platform_account_id' => $account->id,
            'live_host_platform_account_id' => $pivot->id,
            'live_host_id' => $host->id,
            'status' => 'ended',
            'verification_status' => 'pending',
            'scheduled_start_at' => $start,
            'actual_start_at' => $start,
            'actual_end_at' => $start->copy()->addMinutes(50),
            'duration_minutes' => 50,
            'gmv_amount' => $perSession,
            'gmv_adjustment' => 0,
        ]);
        $session->verification_status = 'verified';
        $session->save();
    }
}

beforeEach(function () {
    // A (downline) → B (upline L1) → C (upline L2)
    $this->hostC = User::factory()->create(['role' => 'live_host']);
    LiveHostCommissionProfile::create([
        'user_id' => $this->hostC->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'upline_user_id' => null,
        'override_rate_l1_percent' => 0,
        'override_rate_l2_percent' => 0,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $this->hostB = User::factory()->create(['role' => 'live_host']);
    LiveHostCommissionProfile::create([
        'user_id' => $this->hostB->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'upline_user_id' => $this->hostC->id,
        'override_rate_l1_percent' => 0,
        'override_rate_l2_percent' => 0,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $this->hostA = User::factory()->create(['role' => 'live_host']);
    LiveHostCommissionProfile::create([
        'user_id' => $this->hostA->id,
        'base_salary_myr' => 0,
        'per_live_rate_myr' => 0,
        'upline_user_id' => $this->hostB->id,
        'override_rate_l1_percent' => 0,
        'override_rate_l2_percent' => 0,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $this->tiktok = Platform::factory()->create(['slug' => 'tiktok-override-test']);
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('pays L1 override from downline tier l1_percent', function () {
    tierOverrideSeedTiers($this->hostA, $this->tiktok);
    seedTierOverrideSessions($this->hostA, $this->tiktok, 4, 40000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $items = $run->items->keyBy('user_id');

    // Expected: 40000 × 1.30% = 520.00 (tier 2 l1_percent)
    expect((float) $items[$this->hostB->id]->override_l1_myr)->toEqual(520.00);
});

it('pays L2 override from downline tier l2_percent', function () {
    tierOverrideSeedTiers($this->hostA, $this->tiktok);
    seedTierOverrideSessions($this->hostA, $this->tiktok, 4, 40000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $items = $run->items->keyBy('user_id');

    // Expected: 40000 × 2.30% = 920.00 (tier 2 l2_percent)
    expect((float) $items[$this->hostC->id]->override_l2_myr)->toEqual(920.00);
});

it('generates zero override when downline is below tier 1 floor', function () {
    tierOverrideSeedTiers($this->hostA, $this->tiktok);
    seedTierOverrideSessions($this->hostA, $this->tiktok, 2, 8000); // below 15k floor

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $items = $run->items->keyBy('user_id');

    expect((float) $items[$this->hostB->id]->override_l1_myr)->toEqual(0.00);
    expect((float) $items[$this->hostC->id]->override_l2_myr)->toEqual(0.00);
});

it('separates override by platform', function () {
    $shopee = Platform::factory()->create(['slug' => 'shopee-override-test']);

    // TikTok: 3-tier schedule (so A lands on tier 2 at 40k → l1 1.30%)
    tierOverrideSeedTiers($this->hostA, $this->tiktok);

    // Shopee: single tier 1 at 15-30k with l1_percent = 1.00% (so A at 20k → l1 1.00%)
    LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->hostA->id,
        'platform_id' => $shopee->id,
        'tier_number' => 1,
        'min_gmv_myr' => 15000,
        'max_gmv_myr' => 30000,
        'internal_percent' => 5.00,
        'l1_percent' => 1.00,
        'l2_percent' => 2.00,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    seedTierOverrideSessions($this->hostA, $this->tiktok, 4, 40000);
    seedTierOverrideSessions($this->hostA, $shopee, 2, 20000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $items = $run->items->keyBy('user_id');

    // Expected: 40000 × 1.30% + 20000 × 1.00% = 520 + 200 = 720.00
    expect((float) $items[$this->hostB->id]->override_l1_myr)->toEqual(720.00);
});

it('records tier_id in breakdown JSON for audit', function () {
    tierOverrideSeedTiers($this->hostA, $this->tiktok);
    seedTierOverrideSessions($this->hostA, $this->tiktok, 4, 40000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $items = $run->items->keyBy('user_id');
    $breakdown = $items[$this->hostB->id]->calculation_breakdown_json;

    expect($breakdown)->toHaveKey('overrides_l1');
    expect($breakdown['overrides_l1'])->not->toBeEmpty();

    $entry = $breakdown['overrides_l1'][0];
    expect($entry)->toHaveKeys([
        'downline_user_id', 'platform_id', 'monthly_gmv_myr',
        'tier_id', 'override_rate_percent', 'override_amount',
    ]);

    // tier_id should reference the real tier 2 row for (hostA, tiktok)
    $tier2 = LiveHostPlatformCommissionTier::where('user_id', $this->hostA->id)
        ->where('platform_id', $this->tiktok->id)
        ->where('tier_number', 2)
        ->firstOrFail();

    expect($entry['tier_id'])->toBe($tier2->id);
    expect((float) $entry['override_rate_percent'])->toEqual(1.30);
    expect((float) $entry['override_amount'])->toEqual(520.00);
});
