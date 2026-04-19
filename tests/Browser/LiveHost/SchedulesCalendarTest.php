<?php

use App\Models\LiveSchedule;
use App\Models\User;

it('renders the schedules calendar view', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    LiveSchedule::factory()->count(5)->create(['is_active' => true]);

    $this->actingAs($pic);

    visit('/livehost/schedules?view=calendar')
        ->assertSee('Schedules')
        ->assertSee('SUN')
        ->assertSee('MON')
        ->assertSee('SAT')
        ->assertNoJavascriptErrors();
});

it('toggles between list and calendar views', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    LiveSchedule::factory()->count(3)->create();

    $this->actingAs($pic);

    visit('/livehost/schedules')
        ->assertSee('Schedules')
        ->click('Calendar')
        ->assertSee('SUN')
        ->assertSee('MON')
        ->click('List')
        ->assertNoJavascriptErrors();
});
