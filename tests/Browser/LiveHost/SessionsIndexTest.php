<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;

it('renders live sessions index page with data', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Alia Host']);
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'status' => 'live',
        'scheduled_start_at' => now()->subMinutes(5),
        'actual_start_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($pic);

    visit('/livehost/sessions')
        ->assertSee('Live Sessions')
        ->assertSee('TikTok Main')
        ->assertSee('Alia Host')
        ->assertNoJavascriptErrors();
});
