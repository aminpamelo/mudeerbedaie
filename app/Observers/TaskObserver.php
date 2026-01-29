<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Task;
use App\Notifications\TaskAssignedNotification;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Notify assignee if task is assigned on creation
        if ($task->assignee && $task->assignee->id !== $task->created_by) {
            $task->assignee->notify(new TaskAssignedNotification($task, $task->creator));
        }
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Notify new assignee if assignment changed
        if ($task->wasChanged('assigned_to') && $task->assignee) {
            // Only notify if the assignee is not the one making the change
            if ($task->assignee->id !== auth()->id()) {
                $task->assignee->notify(new TaskAssignedNotification($task, auth()->user()));
            }
        }
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        //
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        //
    }

    /**
     * Handle the Task "force deleted" event.
     */
    public function forceDeleted(Task $task): void
    {
        //
    }
}
