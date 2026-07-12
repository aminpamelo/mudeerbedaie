<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Database\QueryException;

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
            'actual_live_record_id' => [$record->id],
        ]);

    $response->assertRedirect();

    $session->refresh();
    expect($session->verification_status)->toBe('verified')
        ->and($session->matched_actual_live_record_id)->toBe($record->id)
        ->and((float) $session->gmv_amount)->toBe(987.65)
        ->and($session->gmv_source)->toBe('tiktok_actual')
        ->and($session->gmv_locked_at)->not->toBeNull()
        ->and($session->verified_by)->not->toBeNull()
        ->and($session->actualLiveRecords()->count())->toBe(1);
});

it('links multiple records for a split live and sums the GMV', function () {
    [$session, $record] = makeSessionWithCandidate();
    $account = $session->platformAccount;
    // A second segment of the same live (blip/reconnect).
    $record2 = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'c1',
        'launched_time' => now()->addMinutes(10),
        'live_attributed_gmv_myr' => 12.35,
    ]);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => [$record->id, $record2->id],
        ])->assertRedirect();

    $session->refresh();
    expect($session->verification_status)->toBe('verified')
        // 987.65 + 12.35 = 1000.00
        ->and((float) $session->gmv_amount)->toBe(1000.00)
        ->and($session->actualLiveRecords()->count())->toBe(2)
        // Primary = earliest launched segment.
        ->and($session->matched_actual_live_record_id)->toBe($record->id);

    // One audit event per linked record.
    expect(LiveSessionVerificationEvent::where('live_session_id', $session->id)
        ->where('action', 'verify_link')->count())->toBe(2);
});

it('writes a verify_link audit event', function () {
    [$session, $record] = makeSessionWithCandidate();

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => [$record->id],
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
            'actual_live_record_id' => [$record->id],
        ])
        ->assertSessionHasErrors();
});

it('rejects when record belongs to different platform account', function () {
    [$session, $record] = makeSessionWithCandidate();
    $record->update(['platform_account_id' => PlatformAccount::factory()->create()->id]);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$session->id}/verify-link", [
            'actual_live_record_id' => [$record->id],
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
    $sessionB->actualLiveRecords()->attach($record->id, ['is_primary' => true]);

    $this->actingAs(makeAdmin())
        ->post("/livehost/sessions/{$sessionA->id}/verify-link", [
            'actual_live_record_id' => [$record->id],
        ])
        ->assertStatus(409);
});

it('prevents the same record from feeding two sessions at the DB level (no double-count)', function () {
    [$sessionA, $record] = makeSessionWithCandidate();
    $pivot = $sessionA->liveHostPlatformAccount;
    $sessionB = LiveSession::factory()->create([
        'platform_account_id' => $pivot->platform_account_id,
        'live_host_platform_account_id' => $pivot->id,
    ]);

    $sessionA->actualLiveRecords()->attach($record->id, ['is_primary' => true]);

    expect(fn () => $sessionB->actualLiveRecords()->attach($record->id))
        ->toThrow(QueryException::class);
});
