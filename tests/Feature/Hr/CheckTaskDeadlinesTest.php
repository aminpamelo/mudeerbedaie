<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Notifications\Hr\TaskDeadlineApproachingNotification;
use App\Notifications\Hr\TaskOverdueForAssignerNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-21 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sends a 3-day reminder to the assignee', function () {
    $assignee = Employee::factory()->create();
    $task = Task::factory()->create([
        'assigned_to' => $assignee->id,
        'deadline' => Carbon::today()->addDays(3),
        'status' => 'pending',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertSentTo(
        $assignee->user,
        TaskDeadlineApproachingNotification::class,
        fn ($notification) => $notification->task->is($task)
            && $notification->stage === TaskDeadlineApproachingNotification::STAGE_THREE_DAYS
    );
});

it('sends a due-today reminder to the assignee', function () {
    $assignee = Employee::factory()->create();
    Task::factory()->create([
        'assigned_to' => $assignee->id,
        'deadline' => Carbon::today(),
        'status' => 'in_progress',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertSentTo(
        $assignee->user,
        TaskDeadlineApproachingNotification::class,
        fn ($notification) => $notification->stage === TaskDeadlineApproachingNotification::STAGE_DUE_TODAY
    );
});

it('skips reminders for completed tasks', function () {
    $assignee = Employee::factory()->create();
    Task::factory()->completed()->create([
        'assigned_to' => $assignee->id,
        'deadline' => Carbon::today(),
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertNothingSentTo($assignee->user);
});

it('skips reminders for cancelled tasks', function () {
    $assignee = Employee::factory()->create();
    Task::factory()->create([
        'assigned_to' => $assignee->id,
        'deadline' => Carbon::today(),
        'status' => 'cancelled',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertNothingSentTo($assignee->user);
});

it('does not resend the same stage reminder when run again', function () {
    $assignee = Employee::factory()->create();
    Task::factory()->create([
        'assigned_to' => $assignee->id,
        'deadline' => Carbon::today()->addDay(),
        'status' => 'pending',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();
    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertSentToTimes($assignee->user, TaskDeadlineApproachingNotification::class, 1);
});

it('also notifies the assigner when the task becomes overdue', function () {
    $assignee = Employee::factory()->create();
    $assigner = Employee::factory()->create();

    $task = Task::factory()->create([
        'assigned_to' => $assignee->id,
        'assigned_by' => $assigner->id,
        'deadline' => Carbon::today()->subDay(),
        'status' => 'pending',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertSentTo(
        $assignee->user,
        TaskDeadlineApproachingNotification::class,
        fn ($notification) => $notification->stage === TaskDeadlineApproachingNotification::STAGE_OVERDUE_1
    );

    Notification::assertSentTo(
        $assigner->user,
        TaskOverdueForAssignerNotification::class,
        fn ($notification) => $notification->task->is($task) && $notification->daysOverdue === 1
    );
});

it('does not notify the assigner when assignee and assigner are the same person', function () {
    $employee = Employee::factory()->create();

    Task::factory()->create([
        'assigned_to' => $employee->id,
        'assigned_by' => $employee->id,
        'deadline' => Carbon::today()->subDay(),
        'status' => 'pending',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertNotSentTo($employee->user, TaskOverdueForAssignerNotification::class);
});

it('fires a weekly overdue reminder past 7 days', function () {
    $assignee = Employee::factory()->create();
    Task::factory()->create([
        'assigned_to' => $assignee->id,
        'deadline' => Carbon::today()->subDays(14),
        'status' => 'pending',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertSentTo(
        $assignee->user,
        TaskDeadlineApproachingNotification::class,
        fn ($notification) => $notification->stage === TaskDeadlineApproachingNotification::STAGE_OVERDUE_WEEKLY
    );
});

it('does not fire on a non-milestone overdue day', function () {
    $assignee = Employee::factory()->create();
    Task::factory()->create([
        'assigned_to' => $assignee->id,
        'deadline' => Carbon::today()->subDays(5),
        'status' => 'pending',
    ]);

    $this->artisan('hr:check-task-deadlines')->assertSuccessful();

    Notification::assertNotSentTo($assignee->user, TaskDeadlineApproachingNotification::class);
});
