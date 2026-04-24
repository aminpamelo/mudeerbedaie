<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('includes candidate list in show response props', function () {
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
    ]);

    $admin = User::factory()->create(['role' => 'admin_livehost']);

    $this->actingAs($admin)
        ->get("/livehost/sessions/{$session->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('sessions/Show', false)
            ->has('candidates.0')
            ->where('candidates.0.id', $record->id)
            ->where('candidates.0.isSuggested', true)
            ->etc()
        );
});

it('returns empty candidates array when none match', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => null,
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'verification_status' => 'pending',
        'scheduled_start_at' => now(),
    ]);

    $admin = User::factory()->create(['role' => 'admin_livehost']);

    $this->actingAs($admin)
        ->get("/livehost/sessions/{$session->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('sessions/Show', false)
            ->has('candidates', 0)
            ->etc()
        );
});
