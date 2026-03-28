<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSeriesAdmin(): User
{
    return User::factory()->admin()->create();
}

test('unauthenticated user cannot access meeting series', function () {
    $this->getJson('/api/hr/meetings/series')->assertUnauthorized();
});

test('can list meeting series', function () {
    $admin = createSeriesAdmin();

    MeetingSeries::factory()->count(3)->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->getJson('/api/hr/meetings/series');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can create meeting series', function () {
    $admin = createSeriesAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/meetings/series', [
        'name' => 'Weekly Standups',
        'description' => 'Regular weekly team standups',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Weekly Standups');
    expect(MeetingSeries::where('name', 'Weekly Standups')->exists())->toBeTrue();
});

test('create series requires name', function () {
    $admin = createSeriesAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/meetings/series', [
        'description' => 'Missing name field',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('can view series with meetings', function () {
    $admin = createSeriesAdmin();
    $employee = Employee::factory()->create(['user_id' => $admin->id]);
    $series = MeetingSeries::factory()->create(['created_by' => $admin->id]);

    Meeting::factory()->count(2)->create([
        'meeting_series_id' => $series->id,
        'organizer_id' => $employee->id,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->getJson("/api/hr/meetings/series/{$series->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.name', $series->name);
    expect($response->json('data.meetings'))->toHaveCount(2);
});

test('series list includes meeting counts', function () {
    $admin = createSeriesAdmin();
    $employee = Employee::factory()->create(['user_id' => $admin->id]);
    $series = MeetingSeries::factory()->create(['created_by' => $admin->id]);

    Meeting::factory()->count(3)->create([
        'meeting_series_id' => $series->id,
        'organizer_id' => $employee->id,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/meetings/series');

    $response->assertSuccessful();
    expect($response->json('data.0.meetings_count'))->toBe(3);
});
