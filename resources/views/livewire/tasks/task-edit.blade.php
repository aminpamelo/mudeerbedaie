<?php

declare(strict_types=1);

use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Livewire\Volt\Component;

new class extends Component {
    public Task $task;

    public string $title = '';
    public string $description = '';
    public string $task_type = 'adhoc';
    public string $status = 'todo';
    public string $priority = 'medium';
    public ?int $assigned_to = null;
    public ?string $due_date = null;
    public ?string $due_time = null;
    public ?float $estimated_hours = null;
    public ?float $actual_hours = null;

    public function mount(Task $task): void
    {
        $this->task = $task->load('department');

        $user = auth()->user();

        // Check permission
        if (! $task->canBeEditedBy($user)) {
            abort(403, 'You cannot edit this task.');
        }

        // Fill form with existing data
        $this->title = $task->title;
        $this->description = $task->description ?? '';
        $this->task_type = $task->task_type->value;
        $this->status = $task->status->value;
        $this->priority = $task->priority->value;
        $this->assigned_to = $task->assigned_to;
        $this->due_date = $task->due_date?->format('Y-m-d');
        $this->due_time = $task->due_time;
        $this->estimated_hours = $task->estimated_hours;
        $this->actual_hours = $task->actual_hours;
    }

    public function getDepartmentUsers()
    {
        return $this->task->department->users;
    }

    public function update(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'required|in:kpi,adhoc',
            'status' => 'required|in:todo,in_progress,review,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
        ]);

        // Track changes for activity log
        $changes = [];

        if ($this->task->title !== $this->title) {
            $changes[] = ['title_changed', ['title' => $this->task->title], ['title' => $this->title], 'Title changed'];
        }

        if ($this->task->status->value !== $this->status) {
            $oldStatus = $this->task->status;
            $newStatus = TaskStatus::from($this->status);
            $changes[] = ['status_changed', ['status' => $oldStatus->value], ['status' => $this->status], "Status changed from {$oldStatus->label()} to {$newStatus->label()}"];
        }

        if ($this->task->priority->value !== $this->priority) {
            $oldPriority = $this->task->priority;
            $newPriority = TaskPriority::from($this->priority);
            $changes[] = ['priority_changed', ['priority' => $oldPriority->value], ['priority' => $this->priority], "Priority changed from {$oldPriority->label()} to {$newPriority->label()}"];
        }

        if ($this->task->assigned_to !== $this->assigned_to) {
            if ($this->assigned_to) {
                $assignee = \App\Models\User::find($this->assigned_to);
                $changes[] = ['assigned', ['assigned_to' => $this->task->assigned_to], ['assigned_to' => $this->assigned_to], "Assigned to {$assignee->name}"];
            } else {
                $changes[] = ['unassigned', ['assigned_to' => $this->task->assigned_to], ['assigned_to' => null], 'Task unassigned'];
            }
        }

        $dueDate = $this->due_date ? \Carbon\Carbon::parse($this->due_date)->format('Y-m-d') : null;
        $taskDueDate = $this->task->due_date?->format('Y-m-d');
        if ($taskDueDate !== $dueDate) {
            $changes[] = ['due_date_changed', ['due_date' => $taskDueDate], ['due_date' => $dueDate], 'Due date changed'];
        }

        // Update task
        $updateData = [
            'title' => $this->title,
            'description' => $this->description,
            'task_type' => $this->task_type,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => $this->assigned_to,
            'due_date' => $this->due_date,
            'due_time' => $this->due_time,
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
        ];

        // Handle status-specific timestamps
        $newStatus = TaskStatus::from($this->status);
        if ($newStatus === TaskStatus::IN_PROGRESS && ! $this->task->started_at) {
            $updateData['started_at'] = now();
        }
        if ($newStatus === TaskStatus::COMPLETED && ! $this->task->completed_at) {
            $updateData['completed_at'] = now();
        }
        if ($newStatus === TaskStatus::CANCELLED && ! $this->task->cancelled_at) {
            $updateData['cancelled_at'] = now();
        }

        $this->task->update($updateData);

        // Log all changes
        foreach ($changes as $change) {
            $this->task->logActivity($change[0], $change[1], $change[2], $change[3]);
        }

        $this->redirect(route('tasks.show', $this->task), navigate: true);
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Edit Task') }}</flux:heading>
                <flux:text class="mt-2">{{ $task->task_number }} &bull; {{ $task->department->name }}</flux:text>
            </div>
            <flux:button variant="ghost" :href="route('tasks.show', $task)">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        <form wire:submit="update" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="p-6 space-y-6">
                {{-- Title --}}
                <flux:field>
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input wire:model="title" placeholder="{{ __('Enter task title...') }}" />
                    <flux:error name="title" />
                </flux:field>

                {{-- Description --}}
                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" rows="4" placeholder="{{ __('Enter task description...') }}" />
                    <flux:error name="description" />
                </flux:field>

                {{-- Type, Status, Priority --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Type') }}</flux:label>
                        <flux:select wire:model="task_type">
                            @foreach(TaskType::cases() as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="task_type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Status') }}</flux:label>
                        <flux:select wire:model="status">
                            @foreach(TaskStatus::cases() as $status)
                            <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Priority') }}</flux:label>
                        <flux:select wire:model="priority">
                            @foreach(TaskPriority::cases() as $priority)
                            <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="priority" />
                    </flux:field>
                </div>

                {{-- Assignee --}}
                <flux:field>
                    <flux:label>{{ __('Assign To') }}</flux:label>
                    <flux:select wire:model="assigned_to">
                        <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                        @foreach($this->getDepartmentUsers() as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="assigned_to" />
                </flux:field>

                {{-- Due Date & Time --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Due Date') }}</flux:label>
                        <flux:input type="date" wire:model="due_date" />
                        <flux:error name="due_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Due Time') }}</flux:label>
                        <flux:input type="time" wire:model="due_time" />
                        <flux:error name="due_time" />
                    </flux:field>
                </div>

                {{-- Hours --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Estimated Hours') }}</flux:label>
                        <flux:input type="number" step="0.5" min="0" wire:model="estimated_hours" placeholder="0" />
                        <flux:error name="estimated_hours" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Actual Hours') }}</flux:label>
                        <flux:input type="number" step="0.5" min="0" wire:model="actual_hours" placeholder="0" />
                        <flux:error name="actual_hours" />
                    </flux:field>
                </div>
            </div>

            {{-- Actions --}}
            <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700 rounded-b-lg flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('tasks.show', $task)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Update Task') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
