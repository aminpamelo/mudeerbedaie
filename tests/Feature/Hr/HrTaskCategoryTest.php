<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function taskAdmin(): array
{
    $user = User::factory()->admin()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$user, $employee];
}

// ---------- Standalone tasks ----------

test('can create a standalone task without a meeting', function () {
    [$user, $employee] = taskAdmin();
    $assignee = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks', [
        'title' => 'Standalone task',
        'assigned_to' => $assignee->id,
        'priority' => 'medium',
        'deadline' => now()->addDays(2)->toDateString(),
    ]);

    $response->assertCreated()->assertJsonPath('data.title', 'Standalone task');

    $task = Task::where('title', 'Standalone task')->first();
    expect($task)->not->toBeNull();
    expect($task->taskable_type)->toBeNull();
    expect($task->taskable_id)->toBeNull();
    expect($task->assigned_by)->toBe($employee->id);
    expect($task->status)->toBe('pending');
});

test('a standalone task can be assigned a category', function () {
    [$user] = taskAdmin();
    $assignee = Employee::factory()->create();
    $category = TaskCategory::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks', [
        'title' => 'Categorized task',
        'assigned_to' => $assignee->id,
        'category_id' => $category->id,
        'priority' => 'high',
        'deadline' => now()->addDays(5)->toDateString(),
    ]);

    $response->assertCreated()->assertJsonPath('data.category_id', $category->id);
});

test('an admin without a linked employee can still create a standalone task', function () {
    $user = User::factory()->admin()->create();
    $assignee = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks', [
        'title' => 'Task by employee-less admin',
        'assigned_to' => $assignee->id,
        'priority' => 'medium',
        'deadline' => now()->addDays(2)->toDateString(),
    ]);

    $response->assertCreated();

    $task = Task::where('title', 'Task by employee-less admin')->first();
    expect($task)->not->toBeNull();
    expect($task->assigned_by)->toBeNull();
    expect($task->assigned_to)->toBe($assignee->id);
});

test('standalone task requires title, assignee and deadline', function () {
    [$user] = taskAdmin();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks', [
        'priority' => 'medium',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'assigned_to', 'deadline']);
});

test('standalone task rejects a non-existent category', function () {
    [$user] = taskAdmin();
    $assignee = Employee::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks', [
        'title' => 'Bad category task',
        'assigned_to' => $assignee->id,
        'category_id' => 99999,
        'priority' => 'medium',
        'deadline' => now()->addDay()->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
});

// ---------- Category CRUD ----------

test('can create a task category', function () {
    [$user] = taskAdmin();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks/categories', [
        'name' => 'Engineering',
        'color' => '#3366ff',
        'description' => 'Engineering follow-ups',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Engineering');
    expect(TaskCategory::where('name', 'Engineering')->exists())->toBeTrue();
});

test('task category requires a valid hex color', function () {
    [$user] = taskAdmin();

    $response = $this->actingAs($user)->postJson('/api/hr/tasks/categories', [
        'name' => 'Bad Color',
        'color' => 'red',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['color']);
});

test('can update a task category', function () {
    [$user] = taskAdmin();
    $category = TaskCategory::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($user)->putJson("/api/hr/tasks/categories/{$category->id}", [
        'name' => 'New Name',
        'color' => '#112233',
    ]);

    $response->assertSuccessful();
    expect($category->fresh()->name)->toBe('New Name');
});

test('deleting a category leaves its tasks intact but uncategorized', function () {
    [$user, $employee] = taskAdmin();
    $category = TaskCategory::factory()->create();
    $task = Task::factory()->create([
        'assigned_by' => $employee->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/hr/tasks/categories/{$category->id}")
        ->assertSuccessful();

    expect(TaskCategory::find($category->id))->toBeNull();
    expect($task->fresh())->not->toBeNull();
    expect($task->fresh()->category_id)->toBeNull();
});

test('the categories index route is not shadowed by the task show route', function () {
    [$user] = taskAdmin();
    TaskCategory::query()->delete();
    TaskCategory::factory()->count(2)->create();

    $response = $this->actingAs($user)->getJson('/api/hr/tasks/categories');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
});

test('category index can include task counts', function () {
    [$user, $employee] = taskAdmin();
    TaskCategory::query()->delete();
    $category = TaskCategory::factory()->create();
    Task::factory()->count(3)->create([
        'assigned_by' => $employee->id,
        'category_id' => $category->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks/categories?with_counts=1');

    $response->assertSuccessful()->assertJsonPath('data.0.tasks_count', 3);
});

// ---------- Filtering ----------

test('can filter tasks by category', function () {
    [$user, $employee] = taskAdmin();
    $category = TaskCategory::factory()->create();
    Task::factory()->create(['assigned_by' => $employee->id, 'category_id' => $category->id]);
    Task::factory()->create(['assigned_by' => $employee->id, 'category_id' => null]);

    $response = $this->actingAs($user)->getJson("/api/hr/tasks?category_id={$category->id}");

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('can filter uncategorized tasks', function () {
    [$user, $employee] = taskAdmin();
    $category = TaskCategory::factory()->create();
    Task::factory()->create(['assigned_by' => $employee->id, 'category_id' => $category->id]);
    Task::factory()->create(['assigned_by' => $employee->id, 'category_id' => null]);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks?category_id=none');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('can search tasks by title', function () {
    [$user, $employee] = taskAdmin();
    Task::factory()->create(['assigned_by' => $employee->id, 'title' => 'Quarterly budget review']);
    Task::factory()->create(['assigned_by' => $employee->id, 'title' => 'Unrelated chore']);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks?search=budget');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

test('can filter for standalone tasks only', function () {
    [$user, $employee] = taskAdmin();
    Task::factory()->create(['assigned_by' => $employee->id]); // meeting-backed
    Task::factory()->create([
        'assigned_by' => $employee->id,
        'taskable_type' => null,
        'taskable_id' => null,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/tasks?taskable_type=standalone');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
});

// ---------- Auth ----------

test('a non-admin employee cannot create a task category', function () {
    $employeeUser = User::factory()->create(['role' => 'employee']);

    $response = $this->actingAs($employeeUser)->postJson('/api/hr/tasks/categories', [
        'name' => 'Sneaky',
        'color' => '#000000',
    ]);

    $response->assertForbidden();
    expect(TaskCategory::where('name', 'Sneaky')->exists())->toBeFalse();
});

test('a non-admin employee cannot delete a task category', function () {
    $employeeUser = User::factory()->create(['role' => 'employee']);
    $category = TaskCategory::factory()->create();

    $this->actingAs($employeeUser)
        ->deleteJson("/api/hr/tasks/categories/{$category->id}")
        ->assertForbidden();

    expect(TaskCategory::find($category->id))->not->toBeNull();
});

test('unauthenticated user cannot manage task categories', function () {
    $this->getJson('/api/hr/tasks/categories')->assertUnauthorized();
    $this->postJson('/api/hr/tasks/categories', ['name' => 'X', 'color' => '#000000'])->assertUnauthorized();
});

test('unauthenticated user cannot create a standalone task', function () {
    $this->postJson('/api/hr/tasks', [
        'title' => 'Nope',
        'assigned_to' => 1,
        'priority' => 'low',
        'deadline' => now()->addDay()->toDateString(),
    ])->assertUnauthorized();
});
