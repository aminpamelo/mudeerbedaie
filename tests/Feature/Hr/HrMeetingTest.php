<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMeetingAdmin(): array
{
    $user = User::factory()->admin()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$user, $employee];
}

test('unauthenticated user cannot access meetings', function () {
    $this->getJson('/api/hr/meetings')->assertUnauthorized();
    $this->postJson('/api/hr/meetings')->assertUnauthorized();
});

test('can list meetings', function () {
    [$user, $employee] = createMeetingAdmin();

    Meeting::factory()->count(3)->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/meetings');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(3);
});

test('can create a meeting', function () {
    [$user, $employee] = createMeetingAdmin();

    $response = $this->actingAs($user)->postJson('/api/hr/meetings', [
        'title' => 'Weekly Standup',
        'description' => 'Weekly team sync',
        'meeting_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'location' => 'Meeting Room A',
        'status' => 'scheduled',
    ]);

    $response->assertCreated();
    expect(Meeting::where('title', 'Weekly Standup')->exists())->toBeTrue();
});

test('can create meeting with attendees and agenda', function () {
    [$user, $employee] = createMeetingAdmin();
    $attendee1 = Employee::factory()->create();
    $attendee2 = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hr/meetings', [
        'title' => 'Project Review',
        'meeting_date' => now()->addDay()->toDateString(),
        'start_time' => '14:00',
        'attendee_ids' => [$attendee1->id, $attendee2->id],
        'agenda_items' => [
            ['title' => 'Project Status', 'description' => 'Review progress'],
            ['title' => 'Budget Review'],
        ],
    ]);

    $response->assertCreated();
    $meeting = Meeting::where('title', 'Project Review')->first();
    expect($meeting)->not->toBeNull();
    // Organizer + 2 attendees = 3
    expect($meeting->attendees()->count())->toBe(3);
    expect($meeting->agendaItems()->count())->toBe(2);
});

test('can view meeting detail', function () {
    [$user, $employee] = createMeetingAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->getJson("/api/hr/meetings/{$meeting->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.title', $meeting->title);
});

test('can update a meeting', function () {
    [$user, $employee] = createMeetingAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->putJson("/api/hr/meetings/{$meeting->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertSuccessful();
    expect($meeting->fresh()->title)->toBe('Updated Title');
});

test('can delete a meeting', function () {
    [$user, $employee] = createMeetingAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->deleteJson("/api/hr/meetings/{$meeting->id}");

    $response->assertSuccessful();
    expect(Meeting::find($meeting->id))->toBeNull();
    expect(Meeting::withTrashed()->find($meeting->id))->not->toBeNull();
});

test('can update meeting status', function () {
    [$user, $employee] = createMeetingAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
        'status' => 'scheduled',
    ]);

    $response = $this->actingAs($user)->patchJson("/api/hr/meetings/{$meeting->id}/status", [
        'status' => 'in_progress',
    ]);

    $response->assertSuccessful();
    expect($meeting->fresh()->status)->toBe('in_progress');
});

test('create meeting requires title', function () {
    [$user, $employee] = createMeetingAdmin();

    $response = $this->actingAs($user)->postJson('/api/hr/meetings', [
        'meeting_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

test('create meeting requires meeting_date', function () {
    [$user, $employee] = createMeetingAdmin();

    $response = $this->actingAs($user)->postJson('/api/hr/meetings', [
        'title' => 'Test Meeting',
        'start_time' => '10:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['meeting_date']);
});

test('can list meetings with search filter', function () {
    [$user, $employee] = createMeetingAdmin();

    Meeting::factory()->create([
        'title' => 'Sprint Planning',
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    Meeting::factory()->create([
        'title' => 'Budget Review',
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/meetings?search=Sprint');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Sprint Planning');
});

test('can filter meetings by status', function () {
    [$user, $employee] = createMeetingAdmin();

    Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
        'status' => 'scheduled',
    ]);
    Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/meetings?status=scheduled');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('can create meeting with series', function () {
    [$user, $employee] = createMeetingAdmin();
    $series = MeetingSeries::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/hr/meetings', [
        'title' => 'Weekly Standup #5',
        'meeting_date' => now()->addDay()->toDateString(),
        'start_time' => '09:00',
        'meeting_series_id' => $series->id,
    ]);

    $response->assertCreated();
    expect(Meeting::where('meeting_series_id', $series->id)->exists())->toBeTrue();
});
