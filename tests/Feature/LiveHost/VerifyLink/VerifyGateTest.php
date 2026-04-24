<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use App\Models\PlatformAccount;
use App\Models\User;

// Reuse or redeclare minimal helpers (avoid collision with VerifyLinkTest.php helpers)
function makeGateAdmin(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

it('returns 422 when verifying via status=verified without a link', function () {
    $session = LiveSession::factory()->create(['verification_status' => 'pending']);

    $this->actingAs(makeGateAdmin())
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'verified',
        ])
        ->assertStatus(422);
});

it('allows rejecting without a link', function () {
    $session = LiveSession::factory()->create(['verification_status' => 'pending']);

    $response = $this->actingAs(makeGateAdmin())
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

    $this->actingAs(makeGateAdmin())
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'rejected',
            'verification_notes' => 'test session',
        ]);

    $event = LiveSessionVerificationEvent::where('live_session_id', $session->id)->first();
    expect($event->action)->toBe('reject')
        ->and($event->notes)->toBe('test session');
});

it('clears link and gmv on unverify path', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create(['platform_account_id' => $account->id]);
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'live_attributed_gmv_myr' => 500,
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'verified',
        'matched_actual_live_record_id' => $record->id,
        'gmv_amount' => 500,
        'gmv_source' => 'tiktok_actual',
        'gmv_locked_at' => now(),
        'verified_by' => makeGateAdmin()->id,
        'verified_at' => now(),
    ]);

    $this->actingAs(makeGateAdmin())
        ->post("/livehost/sessions/{$session->id}/verify", [
            'verification_status' => 'pending',
        ]);

    $session->refresh();
    expect($session->verification_status)->toBe('pending')
        ->and($session->matched_actual_live_record_id)->toBeNull()
        ->and((float) $session->gmv_amount)->toBe(0.0)
        ->and($session->gmv_source)->toBeNull()
        ->and($session->gmv_locked_at)->toBeNull()
        ->and($session->verified_by)->toBeNull()
        ->and($session->verified_at)->toBeNull();

    $event = LiveSessionVerificationEvent::where('live_session_id', $session->id)->latest('id')->first();
    expect($event->action)->toBe('unverify');
});
