<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTaskAdmin(): array
{
    $user = User::factory()->admin()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$user, $employee];
}

test('can create task for a meeting', function () {
    [$user, $employee] = createTaskAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);
    $assignee = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson("/api/hr/meetings/{$meeting->id}/tasks", [
        'title' => 'Prepare slides',
        'description' => 'Create presentation slides for the meeting',
        'assigned_to' => $assignee->id,
        'priority' => 'high',
        'deadline' => now()->addWeek()->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Prepare slides');
    expect(Task::where('title', 'Prepare slides')->exists())->toBeTrue();
});

test('can list tasks', function () {
    [$user, $employee] = createTaskAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    Task::factory()->count(3)->create([
        'taskable_type' => Meeting::class,
        'taskable_id' => $meeting->id,
        'assigned_by' => $employee->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(3);
});

test('can view task detail', function () {
    [$user, $employee] = createTaskAdmin();
    $task = Task::factory()->create(['assigned_by' => $employee->id]);

    $response = $this->actingAs($user)->getJson("/api/hr/tasks/{$task->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.title', $task->title);
});

test('can update task', function () {
    [$user, $employee] = createTaskAdmin();
    $task = Task::factory()->create(['assigned_by' => $employee->id]);

    $response = $this->actingAs($user)->putJson("/api/hr/tasks/{$task->id}", [
        'title' => 'Updated Task Title',
    ]);

    $response->assertSuccessful();
    expect($task->fresh()->title)->toBe('Updated Task Title');
});

test('can change task status to completed and sets completed_at', function () {
    [$user, $employee] = createTaskAdmin();
    $task = Task::factory()->create([
        'assigned_by' => $employee->id,
        'status' => 'in_progress',
    ]);

    $response = $this->actingAs($user)->patchJson("/api/hr/tasks/{$task->id}/status", [
        'status' => 'completed',
    ]);

    $response->assertSuccessful();
    $freshTask = $task->fresh();
    expect($freshTask->status)->toBe('completed');
    expect($freshTask->completed_at)->not->toBeNull();
});

test('can add subtask', function () {
    [$user, $employee] = createTaskAdmin();
    $task = Task::factory()->create(['assigned_by' => $employee->id]);
    $assignee = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson("/api/hr/tasks/{$task->id}/subtasks", [
        'title' => 'Subtask: Gather data',
        'assigned_to' => $assignee->id,
        'priority' => 'medium',
        'deadline' => now()->addDays(3)->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Subtask: Gather data');
    expect(Task::where('parent_id', $task->id)->count())->toBe(1);
});

test('can add comment to task', function () {
    [$user, $employee] = createTaskAdmin();
    $task = Task::factory()->create(['assigned_by' => $employee->id]);

    $response = $this->actingAs($user)->postJson("/api/hr/tasks/{$task->id}/comments", [
        'content' => 'This task is progressing well.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.content', 'This task is progressing well.');
    expect($task->comments()->count())->toBe(1);
});

test('can delete task with soft delete', function () {
    [$user, $employee] = createTaskAdmin();
    $task = Task::factory()->create(['assigned_by' => $employee->id]);

    $response = $this->actingAs($user)->deleteJson("/api/hr/tasks/{$task->id}");

    $response->assertSuccessful();
    expect(Task::find($task->id))->toBeNull();
    expect(Task::withTrashed()->find($task->id))->not->toBeNull();
});

test('can filter tasks by status', function () {
    [$user, $employee] = createTaskAdmin();

    Task::factory()->create([
        'assigned_by' => $employee->id,
        'status' => 'pending',
    ]);
    Task::factory()->completed()->create([
        'assigned_by' => $employee->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks?status=pending');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('can filter tasks by priority', function () {
    [$user, $employee] = createTaskAdmin();

    Task::factory()->create([
        'assigned_by' => $employee->id,
        'priority' => 'urgent',
    ]);
    Task::factory()->create([
        'assigned_by' => $employee->id,
        'priority' => 'low',
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks?priority=urgent');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('create task requires title and assigned_to', function () {
    [$user, $employee] = createTaskAdmin();
    $meeting = Meeting::factory()->create([
        'organizer_id' => $employee->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/hr/meetings/{$meeting->id}/tasks", [
        'priority' => 'high',
        'deadline' => now()->addWeek()->toDateString(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'assigned_to']);
});

test('unauthenticated user cannot access tasks', function () {
    $this->getJson('/api/hr/tasks')->assertUnauthorized();
});

test('can create a standalone task with multiple assignees', function () {
    [$user] = createTaskAdmin();
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks', [
        'title' => 'Shared task',
        'assignee_ids' => [$a->id, $b->id],
        'priority' => 'high',
        'deadline' => now()->addWeek()->toDateString(),
    ]);

    $response->assertCreated();
    $task = Task::where('title', 'Shared task')->firstOrFail();

    // First selected becomes the canonical assignee; full set lives in the pivot.
    expect($task->assigned_to)->toEqual($a->id);
    expect($task->assignees->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test('list payload includes the assignees array', function () {
    [$user, $employee] = createTaskAdmin();
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();
    $task = Task::factory()->create(['assigned_by' => $employee->id, 'assigned_to' => $a->id]);
    $task->assignees()->sync([$a->id, $b->id]);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks');

    $response->assertSuccessful();
    expect($response->json('data.0.assignees'))->toHaveCount(2);
});

test('can update a task to be co-owned by several employees', function () {
    [$user, $employee] = createTaskAdmin();
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();
    $c = Employee::factory()->create();
    $task = Task::factory()->create(['assigned_by' => $employee->id, 'assigned_to' => $a->id]);
    $task->assignees()->sync([$a->id]);

    $response = $this->actingAs($user)->putJson("/api/hr/tasks/{$task->id}", [
        'assignee_ids' => [$b->id, $c->id],
    ]);

    $response->assertSuccessful();
    $fresh = $task->fresh();
    expect($fresh->assigned_to)->toEqual($b->id);
    expect($fresh->assignees->pluck('id')->sort()->values()->all())
        ->toBe(collect([$b->id, $c->id])->sort()->values()->all());
});

test('filtering by assigned_to matches any co-owner, not just the primary', function () {
    [$user, $employee] = createTaskAdmin();
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();

    $shared = Task::factory()->create(['assigned_by' => $employee->id, 'assigned_to' => $a->id]);
    $shared->assignees()->sync([$a->id, $b->id]);

    $solo = Task::factory()->create(['assigned_by' => $employee->id, 'assigned_to' => $a->id]);
    $solo->assignees()->sync([$a->id]);

    // b co-owns only the shared task.
    $response = $this->actingAs($user)->getJson("/api/hr/tasks?assigned_to={$b->id}");

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toEqual($shared->id);
});
