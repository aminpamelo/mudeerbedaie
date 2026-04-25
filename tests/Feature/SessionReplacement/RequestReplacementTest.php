<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '06:30:00',
        'end_time' => '08:30:00',
    ]);
    $this->assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
        'time_slot_id' => $slot->id,
        'platform_account_id' => PlatformAccount::factory(),
        'day_of_week' => now()->addDay()->dayOfWeek,
        'is_template' => true,
    ]);
});

it('lets a host submit a one-date replacement request', function () {
    $targetDate = now()->addDay()->toDateString();

    $response = $this->actingAs($this->host)
        ->post(route('live-host.replacement-requests.store'), [
            'live_schedule_assignment_id' => $this->assignment->id,
            'scope' => 'one_date',
            'target_date' => $targetDate,
            'reason_category' => 'sick',
            'reason_note' => 'Demam tinggi.',
        ]);

    $response->assertRedirect(route('live-host.schedule'));

    $this->assertDatabaseHas('session_replacement_requests', [
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
        'scope' => 'one_date',
        'target_date' => $targetDate,
        'reason_category' => 'sick',
        'status' => 'pending',
    ]);

    $created = SessionReplacementRequest::query()->latest('id')->first();
    expect($created->expires_at->toDateTimeString())
        ->toBe(now()->parse($targetDate)->setTimeFromTimeString('06:30:00')->toDateTimeString());
});
