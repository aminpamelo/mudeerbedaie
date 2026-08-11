<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
});

test('calendar page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get('/workspace/calendar')
        ->assertSuccessful();
});

test('calendar events endpoint returns tasks within date range', function () {
    $task = Task::factory()->create([
        'deadline' => '2026-08-15',
        'start_date' => '2026-08-10',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/workspace/calendar/events?start=2026-08-01&end=2026-08-31')
        ->assertSuccessful();

    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($task->id);
});

test('calendar events endpoint excludes tasks outside date range', function () {
    Task::factory()->create([
        'deadline' => '2026-09-15',
        'start_date' => '2026-09-10',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/workspace/calendar/events?start=2026-08-01&end=2026-08-31')
        ->assertSuccessful();

    $data = $response->json('data');

    expect($data)->toHaveCount(0);
});

test('calendar events endpoint validates start and end dates', function () {
    $this->actingAs($this->user)
        ->getJson('/workspace/calendar/events')
        ->assertUnprocessable();

    $this->actingAs($this->user)
        ->getJson('/workspace/calendar/events?start=2026-08-31&end=2026-08-01')
        ->assertUnprocessable();
});

test('calendar is not accessible to unauthenticated users', function () {
    $this->get('/workspace/calendar')
        ->assertRedirect();
});
