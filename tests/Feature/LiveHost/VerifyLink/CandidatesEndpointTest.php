<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;

it('exposes both total and live-attributed GMV for each candidate', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'csh',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'pending',
        'scheduled_start_at' => now(),
    ]);
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'csh',
        'launched_time' => now(),
        'source' => 'api_sync',
        'gmv_myr' => 301.60,
        'live_attributed_gmv_myr' => -1,
    ]);

    $admin = User::factory()->create(['role' => 'admin_livehost']);

    $this->actingAs($admin)
        ->getJson("/livehost/sessions/{$session->id}/candidates")
        ->assertOk()
        ->assertJsonPath('candidates.0.id', $record->id)
        ->assertJsonPath('candidates.0.gmvMyr', 301.6)
        ->assertJsonPath('candidates.0.liveAttributedGmvMyr', -1)
        ->assertJsonPath('candidates.0.source', 'api_sync')
        ->assertJsonPath('candidates.0.isSuggested', true);
});

it('blocks livehost assistants from reading candidates', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'csh',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'pending',
        'scheduled_start_at' => now(),
    ]);

    $assistant = User::factory()->create(['role' => 'livehost_assistant']);

    $this->actingAs($assistant)
        ->getJson("/livehost/sessions/{$session->id}/candidates")
        ->assertForbidden();
});
