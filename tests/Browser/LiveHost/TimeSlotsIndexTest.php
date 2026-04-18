<?php

use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;

it('renders time slots index page with data', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);

    LiveTimeSlot::factory()->create([
        'platform_account_id' => $account->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'is_active' => true,
    ]);

    $this->actingAs($pic);

    visit('/livehost/time-slots')
        ->assertSee('Time Slots')
        ->assertSee('TikTok Main')
        ->assertSee('09:00')
        ->assertNoJavascriptErrors();
});
