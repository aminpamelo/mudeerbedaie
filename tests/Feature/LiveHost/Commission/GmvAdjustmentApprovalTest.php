<?php

use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Build a verified, ended session wired to the seeded Ahmad host and the
 * TikTok Shop platform so CommissionCalculator::snapshot can resolve a rate.
 */
function makeApprovalSession(User $host, Platform $platform, array $overrides = []): LiveSession
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

    return LiveSession::factory()->create(array_merge([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
        'scheduled_start_at' => now()->subHour(),
        'actual_start_at' => now()->subHour(),
        'actual_end_at' => now()->subMinutes(10),
        'duration_minutes' => 50,
        'gmv_amount' => 1000,
        'gmv_adjustment' => 0,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('PIC can approve a proposed adjustment and session cache updates', function () {
    $session = makeApprovalSession($this->ahmad, $this->tiktok);

    $adjustment = LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -120,
        'reason' => 'Auto: Order #ABC refunded/cancelled (RM 120)',
        'status' => 'proposed',
        'adjusted_by' => null,
        'adjusted_at' => now(),
    ]);

    // Cache not affected while proposed.
    $session->refresh();
    expect((float) $session->gmv_adjustment)->toBe(0.0);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments/{$adjustment->id}/approve")
        ->assertRedirect();

    $adjustment->refresh();
    $session->refresh();

    expect($adjustment->status)->toBe('approved');
    expect($adjustment->adjusted_by)->toBe($this->pic->id);
    expect((float) $session->gmv_adjustment)->toBe(-120.0);
});

it('PIC can reject a proposed adjustment, cache unchanged', function () {
    $session = makeApprovalSession($this->ahmad, $this->tiktok);

    $adjustment = LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -75,
        'reason' => 'Auto: Order #XYZ refunded/cancelled (RM 75)',
        'status' => 'proposed',
        'adjusted_by' => null,
        'adjusted_at' => now(),
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments/{$adjustment->id}/reject")
        ->assertRedirect();

    $adjustment->refresh();
    $session->refresh();

    expect($adjustment->status)->toBe('rejected');
    expect((float) $session->gmv_adjustment)->toBe(0.0);
});

it('already-approved adjustment cannot be approved again', function () {
    $session = makeApprovalSession($this->ahmad, $this->tiktok);

    $adjustment = LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -50,
        'reason' => 'Manual adjustment',
        'status' => 'approved',
        'adjusted_by' => $this->pic->id,
        'adjusted_at' => now(),
    ]);

    $response = actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments/{$adjustment->id}/approve");

    $response->assertStatus(422);
});

it('approving an adjustment in a locked payroll period is 403', function () {
    $session = makeApprovalSession($this->ahmad, $this->tiktok);

    $adjustment = LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -100,
        'reason' => 'Auto: Order #LOCKED refunded/cancelled (RM 100)',
        'status' => 'proposed',
        'adjusted_by' => null,
        'adjusted_at' => now(),
    ]);

    LiveHostPayrollRun::create([
        'period_start' => $session->actual_end_at->copy()->startOfMonth()->toDateString(),
        'period_end' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'cutoff_date' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments/{$adjustment->id}/approve")
        ->assertForbidden();

    $adjustment->refresh();
    expect($adjustment->status)->toBe('proposed');
});

it('approve re-snapshots commission for verified sessions', function () {
    $session = makeApprovalSession($this->ahmad, $this->tiktok);

    // Trigger verification through the observer to persist a snapshot.
    $session->forceFill(['verification_status' => 'verified'])->save();
    $session->refresh();

    expect($session->gmv_locked_at)->not->toBeNull();
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(1000.0);

    $adjustment = LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -300,
        'reason' => 'Auto: Order #POST refunded/cancelled (RM 300)',
        'status' => 'proposed',
        'adjusted_by' => null,
        'adjusted_at' => now(),
    ]);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments/{$adjustment->id}/approve")
        ->assertRedirect();

    $session->refresh();

    expect((float) $session->gmv_adjustment)->toBe(-300.0);
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(700.0);
});

it('live_host role cannot approve adjustments (403)', function () {
    $session = makeApprovalSession($this->ahmad, $this->tiktok);

    $adjustment = LiveSessionGmvAdjustment::create([
        'live_session_id' => $session->id,
        'amount_myr' => -100,
        'reason' => 'Auto: Order #HOST refunded/cancelled (RM 100)',
        'status' => 'proposed',
        'adjusted_by' => null,
        'adjusted_at' => now(),
    ]);

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post("/livehost/sessions/{$session->id}/adjustments/{$adjustment->id}/approve")
        ->assertForbidden();

    $adjustment->refresh();
    expect($adjustment->status)->toBe('proposed');
});
