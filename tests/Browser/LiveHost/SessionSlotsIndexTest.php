<?php

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;

it('renders session slots index page with data', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);
    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Alia Host']);

    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'time_slot_id' => $slot->id,
        'live_host_id' => $host->id,
        'day_of_week' => 1,
        'is_template' => true,
        'status' => 'confirmed',
    ]);

    $this->actingAs($pic);

    visit('/livehost/session-slots')
        ->assertSee('Session Slots')
        ->assertSee('TikTok Main')
        ->assertSee('09:00')
        ->assertSee('Alia Host')
        ->assertNoJavascriptErrors();
});
