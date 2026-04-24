<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use App\Models\User;

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
        ->and((string) $event->gmv_snapshot)->toBe('1234.56')
        ->and($event->liveSession->id)->toBe($session->id)
        ->and($event->user->id)->toBe($user->id)
        ->and($event->actualLiveRecord->id)->toBe($record->id);
});
