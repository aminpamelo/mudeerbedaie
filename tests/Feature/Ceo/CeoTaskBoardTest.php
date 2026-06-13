<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function ceoUser(): User
{
    return User::factory()->create(['role' => 'ceo']);
}

describe('board payload', function () {
    it('ships an editable board with lookups', function () {
        $employee = Employee::factory()->create();
        Task::factory()->create(['assigned_to' => $employee->id, 'status' => 'pending']);

        $this->actingAs(ceoUser())
            ->get('/ceo/tasks')
            ->assertInertia(fn (Assert $page) => $page
                ->component('TaskMonitoring', false)
                ->has('board.data')
                ->has('board.statusFilters')
                ->has('board.statusOptions', 4)
                ->has('board.priorityOptions', 4)
                ->where('board.filters.status', 'open')
                ->has('employees')
                ->has('categories')
            );
    });

    it('defaults to open tasks and can show all', function () {
        $employee = Employee::factory()->create();
        Task::factory()->create(['assigned_to' => $employee->id, 'status' => 'pending']);
        Task::factory()->completed()->create(['assigned_to' => $employee->id]);

        $this->actingAs(ceoUser())
            ->get('/ceo/tasks')
            ->assertInertia(fn (Assert $page) => $page->has('board.data', 1)); // only the open one

        $this->actingAs(ceoUser())
            ->get('/ceo/tasks?status=all')
            ->assertInertia(fn (Assert $page) => $page->has('board.data', 2));
    });

    it('filters by priority and search', function () {
        $employee = Employee::factory()->create();
        Task::factory()->create(['assigned_to' => $employee->id, 'status' => 'pending', 'priority' => 'urgent', 'title' => 'Fix the server']);
        Task::factory()->create(['assigned_to' => $employee->id, 'status' => 'pending', 'priority' => 'low', 'title' => 'Water the plants']);

        $this->actingAs(ceoUser())
            ->get('/ceo/tasks?priority=urgent')
            ->assertInertia(fn (Assert $page) => $page->has('board.data', 1)->where('board.data.0.priority', 'urgent'));

        $this->actingAs(ceoUser())
            ->get('/ceo/tasks?search=server')
            ->assertInertia(fn (Assert $page) => $page->has('board.data', 1)->where('board.data.0.title', 'Fix the server'));
    });
});

describe('mutations', function () {
    it('lets the CEO create a standalone task', function () {
        $assignee = Employee::factory()->create();

        $this->actingAs(ceoUser())
            ->post('/ceo/tasks', [
                'title' => 'Board the new hires',
                'assigned_to' => $assignee->id,
                'priority' => 'high',
                'deadline' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect();

        $task = Task::where('title', 'Board the new hires')->first();
        expect($task)->not->toBeNull();
        expect($task->taskable_type)->toBeNull();
        expect($task->assigned_to)->toBe($assignee->id);
        expect($task->status)->toBe('pending');
    });

    it('validates task creation', function () {
        $this->actingAs(ceoUser())
            ->post('/ceo/tasks', ['priority' => 'high'])
            ->assertSessionHasErrors(['title', 'assigned_to', 'deadline']);
    });

    it('lets the CEO reassign and reprioritise a task', function () {
        $from = Employee::factory()->create();
        $to = Employee::factory()->create();
        $task = Task::factory()->create(['assigned_to' => $from->id, 'priority' => 'low', 'status' => 'pending']);

        $this->actingAs(ceoUser())
            ->patch("/ceo/tasks/{$task->id}", ['assigned_to' => $to->id, 'priority' => 'urgent'])
            ->assertRedirect();

        $task->refresh();
        expect($task->assigned_to)->toBe($to->id);
        expect($task->priority)->toBe('urgent');
    });

    it('sets completed_at when the CEO marks a task complete', function () {
        $task = Task::factory()->create(['status' => 'in_progress', 'completed_at' => null]);

        $this->actingAs(ceoUser())
            ->patch("/ceo/tasks/{$task->id}", ['status' => 'completed'])
            ->assertRedirect();

        $task->refresh();
        expect($task->status)->toBe('completed');
        expect($task->completed_at)->not->toBeNull();
    });

    it('clears completed_at when a completed task is reopened', function () {
        $task = Task::factory()->completed()->create();
        expect($task->completed_at)->not->toBeNull();

        $this->actingAs(ceoUser())
            ->patch("/ceo/tasks/{$task->id}", ['status' => 'in_progress'])
            ->assertRedirect();

        $task->refresh();
        expect($task->status)->toBe('in_progress');
        expect($task->completed_at)->toBeNull();
    });

    it('can assign a category', function () {
        $task = Task::factory()->create();
        $category = TaskCategory::factory()->create();

        $this->actingAs(ceoUser())
            ->patch("/ceo/tasks/{$task->id}", ['category_id' => $category->id])
            ->assertRedirect();

        expect($task->refresh()->category_id)->toBe($category->id);
    });

    it('reflects edits immediately in the cached aggregates', function () {
        $emp = Employee::factory()->create();
        Task::factory()->count(3)->create([
            'assigned_to' => $emp->id,
            'status' => 'pending',
            'deadline' => now()->addDays(2)->toDateString(),
        ]);

        $ceo = ceoUser();

        // Prime the 60s aggregate cache: open = 3.
        $this->actingAs($ceo)->get('/ceo/tasks')
            ->assertInertia(fn (Assert $page) => $page->where('tasks.kpis.0.value', '3'));

        $this->actingAs($ceo)
            ->patch('/ceo/tasks/'.Task::first()->id, ['status' => 'completed'])
            ->assertRedirect();

        // Without cache-busting this would still read the cached '3'.
        $this->actingAs($ceo)->get('/ceo/tasks')
            ->assertInertia(fn (Assert $page) => $page->where('tasks.kpis.0.value', '2'));
    });

    it('lets the CEO delete a task', function () {
        $task = Task::factory()->create();

        $this->actingAs(ceoUser())
            ->delete("/ceo/tasks/{$task->id}")
            ->assertRedirect();

        expect(Task::find($task->id))->toBeNull();
        expect(Task::withTrashed()->find($task->id))->not->toBeNull();
    });
});

describe('access control', function () {
    it('forbids non-executive roles from mutating tasks', function () {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $task = Task::factory()->create();

        $this->actingAs($teacher)->post('/ceo/tasks', [
            'title' => 'Nope',
            'assigned_to' => $task->assigned_to,
            'priority' => 'low',
            'deadline' => now()->addDay()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($teacher)->patch("/ceo/tasks/{$task->id}", ['status' => 'completed'])->assertForbidden();
        $this->actingAs($teacher)->delete("/ceo/tasks/{$task->id}")->assertForbidden();
    });

    it('allows admins too', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $task = Task::factory()->create();

        $this->actingAs($admin)->patch("/ceo/tasks/{$task->id}", ['priority' => 'high'])->assertRedirect();
        expect($task->refresh()->priority)->toBe('high');
    });
});
