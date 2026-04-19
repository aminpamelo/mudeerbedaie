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
 * Build an ended, verified session wired to the seeded Ahmad host and the
 * TikTok Shop platform so the commission snapshot can resolve a real rate.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeAdjustmentSession(User $host, Platform $platform, array $overrides = []): LiveSession
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

it('PIC can add a negative adjustment and session gmv_adjustment updates', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -120,
            'reason' => 'Order ABC refunded',
        ])
        ->assertRedirect();

    $session->refresh();

    expect(LiveSessionGmvAdjustment::where('live_session_id', $session->id)->count())->toBe(1);

    $row = LiveSessionGmvAdjustment::where('live_session_id', $session->id)->first();
    expect((float) $row->amount_myr)->toBe(-120.0);
    expect($row->reason)->toBe('Order ABC refunded');
    expect($row->adjusted_by)->toBe($this->pic->id);
    expect($row->adjusted_at)->not->toBeNull();

    expect((float) $session->gmv_adjustment)->toBe(-120.0);
});

it('adding a second adjustment sums to gmv_adjustment', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -120,
            'reason' => 'Refund 1',
        ])
        ->assertRedirect();

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -50,
            'reason' => 'Refund 2',
        ])
        ->assertRedirect();

    $session->refresh();

    expect(LiveSessionGmvAdjustment::where('live_session_id', $session->id)->count())->toBe(2);
    expect((float) $session->gmv_adjustment)->toBe(-170.0);
});

it('positive adjustments are allowed', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => 30,
            'reason' => 'Late order matched',
        ])
        ->assertRedirect();

    $session->refresh();

    expect((float) $session->gmv_adjustment)->toBe(30.0);
});

it('amount=0 is rejected', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => 0,
            'reason' => 'Zero is not a real adjustment',
        ])
        ->assertSessionHasErrors('amount');

    expect(LiveSessionGmvAdjustment::where('live_session_id', $session->id)->count())->toBe(0);
});

it('live_host role cannot add adjustments (403)', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -50,
            'reason' => 'Should be blocked',
        ])
        ->assertForbidden();

    expect(LiveSessionGmvAdjustment::where('live_session_id', $session->id)->count())->toBe(0);
});

it('adjusting a session whose month is in a LOCKED payroll run is 403', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    LiveHostPayrollRun::create([
        'period_start' => $session->actual_end_at->copy()->startOfMonth()->toDateString(),
        'period_end' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'cutoff_date' => $session->actual_end_at->copy()->endOfMonth()->toDateString(),
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->pic->id,
    ]);

    $response = actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -100,
            'reason' => 'Too late — should be blocked',
        ]);

    $response->assertForbidden();
    expect(strtolower($response->exception?->getMessage() ?? ''))->toContain('payroll locked');

    expect(LiveSessionGmvAdjustment::where('live_session_id', $session->id)->count())->toBe(0);
});

it('verified sessions get commission_snapshot_json re-snapshotted after adjustment', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    // Trigger verification through the observer so a snapshot is persisted.
    $session->forceFill(['verification_status' => 'verified'])->save();

    $session->refresh();
    expect($session->gmv_locked_at)->not->toBeNull();
    expect($session->commission_snapshot_json)->toBeArray();
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(1000.0);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -200,
            'reason' => 'Post-verify refund',
        ])
        ->assertRedirect();

    $session->refresh();

    expect((float) $session->gmv_adjustment)->toBe(-200.0);
    expect($session->commission_snapshot_json)->toBeArray();
    expect((float) $session->commission_snapshot_json['net_gmv'])->toBe(800.0);
});

it('PIC can delete an adjustment and the session aggregate updates', function () {
    $session = makeAdjustmentSession($this->ahmad, $this->tiktok);

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -120,
            'reason' => 'Refund 1',
        ])
        ->assertRedirect();

    actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -50,
            'reason' => 'Refund 2',
        ])
        ->assertRedirect();

    $first = LiveSessionGmvAdjustment::where('live_session_id', $session->id)
        ->where('amount_myr', -120)
        ->firstOrFail();

    actingAs($this->pic)
        ->delete("/livehost/sessions/{$session->id}/adjustments/{$first->id}")
        ->assertRedirect();

    $session->refresh();

    expect(LiveSessionGmvAdjustment::where('live_session_id', $session->id)->count())->toBe(1);
    expect((float) $session->gmv_adjustment)->toBe(-50.0);
});
