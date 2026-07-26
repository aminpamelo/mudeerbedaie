<?php

declare(strict_types=1);

use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

it('rebuilds scheduled_start_at from assignment date + slot start_time', function () {
    Carbon::setTestNow('2026-07-26 15:00:00');

    $platform = PlatformAccount::factory()->create();
    $account = LiveAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);
    $slot = LiveTimeSlot::factory()->create(['start_time' => '06:30:00', 'end_time' => '08:30:00']);
    $assignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $platform->id, 'live_account_id' => $account->id,
        'live_host_id' => $host->id, 'time_slot_id' => $slot->id,
        'is_template' => false, 'schedule_date' => '2026-07-01', 'day_of_week' => Carbon::parse('2026-07-01')->dayOfWeek,
    ]);
    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->firstOrFail();

    // Simulate the corruption: scheduled_start_at bumped to "now".
    $session->forceFill(['scheduled_start_at' => '2026-07-26 15:00:00'])->saveQuietly();

    $this->artisan('livehost:restore-scheduled-times --apply')->assertSuccessful();

    expect($session->refresh()->scheduled_start_at->format('Y-m-d H:i:s'))->toBe('2026-07-01 06:30:00');
});

it('dry-run changes nothing', function () {
    Carbon::setTestNow('2026-07-26 15:00:00');
    $slot = LiveTimeSlot::factory()->create(['start_time' => '06:30:00', 'end_time' => '08:30:00']);
    $assignment = LiveScheduleAssignment::factory()->create([
        'time_slot_id' => $slot->id, 'is_template' => false, 'schedule_date' => '2026-07-01',
        'day_of_week' => Carbon::parse('2026-07-01')->dayOfWeek,
    ]);
    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->firstOrFail();
    $session->forceFill(['scheduled_start_at' => '2026-07-26 15:00:00'])->saveQuietly();

    $this->artisan('livehost:restore-scheduled-times')->assertSuccessful();

    expect($session->refresh()->scheduled_start_at->format('Y-m-d H:i:s'))->toBe('2026-07-26 15:00:00');
});
