<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use App\Models\PlatformAccount;
use App\Models\User;

function makeAdmin(): User
{
    return User::factory()->create(['role' => 'admin_livehost']);
}

function makeSessionWithCandidate(): array
{
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'c1',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'pending',
        'scheduled_start_at' => now(),
    ]);
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
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
    $record->update(['platform_account_id' => PlatformAccount::factory()->create()->id]);

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
