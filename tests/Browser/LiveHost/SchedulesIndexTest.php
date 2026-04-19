<?php

use App\Models\LiveSchedule;
use App\Models\PlatformAccount;
use App\Models\User;

it('renders schedules index page with data', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);

    LiveSchedule::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_id' => $host->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'is_active' => true,
    ]);

    $this->actingAs($pic);

    visit('/livehost/schedules')
        ->assertSee('Schedules')
        ->assertSee('Wan Amir')
        ->assertSee('TikTok Main')
        ->assertNoJavascriptErrors();
});
