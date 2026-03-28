<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAttendeeAdmin(): array
{
    $user = User::factory()->admin()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$user, $employee];
}

test('can add attendees to meeting', function () {
    [$user, $employee] = createAttendeeAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    $attendee1 = Employee::factory()->create();
    $attendee2 = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson("/api/hr/meetings/{$meeting->id}/attendees", [
        'employee_ids' => [$attendee1->id, $attendee2->id],
    ]);

    $response->assertCreated();
    expect($meeting->attendees()->count())->toBe(2);
});

test('adding duplicate attendees does not create duplicates', function () {
    [$user, $employee] = createAttendeeAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    $attendee = Employee::factory()->create();

    MeetingAttendee::create([
        'meeting_id' => $meeting->id,
        'employee_id' => $attendee->id,
        'role' => 'attendee',
        'attendance_status' => 'invited',
    ]);

    $response = $this->actingAs($user)->postJson("/api/hr/meetings/{$meeting->id}/attendees", [
        'employee_ids' => [$attendee->id],
    ]);

    $response->assertCreated();
    expect($meeting->attendees()->where('employee_id', $attendee->id)->count())->toBe(1);
});

test('can update attendance status', function () {
    [$user, $employee] = createAttendeeAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    $attendee = Employee::factory()->create();

    MeetingAttendee::create([
        'meeting_id' => $meeting->id,
        'employee_id' => $attendee->id,
        'role' => 'attendee',
        'attendance_status' => 'invited',
    ]);

    $response = $this->actingAs($user)->patchJson("/api/hr/meetings/{$meeting->id}/attendees/{$attendee->id}", [
        'attendance_status' => 'attended',
    ]);

    $response->assertSuccessful();
    expect(MeetingAttendee::where('meeting_id', $meeting->id)
        ->where('employee_id', $attendee->id)
        ->first()
        ->attendance_status)->toBe('attended');
});

test('can remove attendee from meeting', function () {
    [$user, $employee] = createAttendeeAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    $attendee = Employee::factory()->create();

    MeetingAttendee::create([
        'meeting_id' => $meeting->id,
        'employee_id' => $attendee->id,
        'role' => 'attendee',
        'attendance_status' => 'invited',
    ]);

    $response = $this->actingAs($user)->deleteJson("/api/hr/meetings/{$meeting->id}/attendees/{$attendee->id}");

    $response->assertSuccessful();
    expect($meeting->attendees()->where('employee_id', $attendee->id)->count())->toBe(0);
});

test('removing non-existent attendee returns 404', function () {
    [$user, $employee] = createAttendeeAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    $nonAttendee = Employee::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/api/hr/meetings/{$meeting->id}/attendees/{$nonAttendee->id}");

    $response->assertNotFound();
});

test('add attendees requires employee_ids array', function () {
    [$user, $employee] = createAttendeeAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/hr/meetings/{$meeting->id}/attendees", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids']);
});

test('update attendance status validates allowed values', function () {
    [$user, $employee] = createAttendeeAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    $attendee = Employee::factory()->create();

    MeetingAttendee::create([
        'meeting_id' => $meeting->id,
        'employee_id' => $attendee->id,
        'role' => 'attendee',
        'attendance_status' => 'invited',
    ]);

    $response = $this->actingAs($user)->patchJson("/api/hr/meetings/{$meeting->id}/attendees/{$attendee->id}", [
        'attendance_status' => 'invalid_status',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_status']);
});
