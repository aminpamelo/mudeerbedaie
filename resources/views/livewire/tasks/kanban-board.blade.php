<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public Department $department;
    public bool $isReadOnly = false;
    public bool $canManage = false;
    public bool $canCreate = false;
    public bool $canEdit = false;

    public string $search = '';
    public string $filterType = '';
    public string $filterAssignee = '';
    public string $filterPriority = '';

    // Quick create modal
    public bool $showQuickCreate = false;
    public string $quickCreateStatus = 'todo';
    public string $quickCreateTitle = '';
    public string $quickCreateType = 'adhoc';
    public string $quickCreatePriority = 'medium';

    public function mount(Department $department): void
    {
        $this->department = $department;
        $user = auth()->user();

        // Check access
        if (! $user->canViewTasks($department)) {
            abort(403, 'You do not have access to this department.');
        }

        $this->isReadOnly = $user->isAdmin();
        $this->canManage = $user->canManageTasks($department);
        $this->canCreate = $user->canCreateTasks($department);
        $this->canEdit = $user->canEditTasks($department);
    }

    public function getTasksForStatus(TaskStatus $status)
    {
        $query = Task::with(['assignee', 'creator'])
            ->where('department_id', $this->department->id)
            ->where('status', $status);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('task_number', 'like', "%{$this->search}%");
            });
        }

        // Filters
        if ($this->filterType) {
            $query->where('task_type', $this->filterType);
        }

        if ($this->filterAssignee) {
            if ($this->filterAssignee === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $this->filterAssignee);
            }
        }

        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

        return $query->orderBy('sort_order')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->get();
    }

    public function getDepartmentUsers()
    {
        return $this->department->users;
    }

    public function moveTask(int $taskId, string $newStatus, int $newOrder = 0): void
    {
        if ($this->isReadOnly || ! $this->canEdit) {
            return;
        }

        $task = Task::findOrFail($taskId);

        if ($task->department_id !== $this->department->id) {
            return;
        }

        $oldStatus = $task->status;
        $task->changeStatus(TaskStatus::from($newStatus));
        $task->update(['sort_order' => $newOrder]);

        // Log activity
        if ($oldStatus !== TaskStatus::from($newStatus)) {
            $task->logActivity(
                'status_changed',
                ['status' => $oldStatus->value],
                ['status' => $newStatus],
                "Status changed from {$oldStatus->label()} to " . TaskStatus::from($newStatus)->label()
            );
        }
    }

    public function openQuickCreate(string $status): void
    {
        if ($this->isReadOnly || ! $this->canCreate) {
            return;
        }

        $this->quickCreateStatus = $status;
        $this->quickCreateTitle = '';
        $this->quickCreateType = 'adhoc';
        $this->quickCreatePriority = 'medium';
        $this->showQuickCreate = true;
    }

    public function quickCreate(): void
    {
        if ($this->isReadOnly || ! $this->canCreate) {
            return;
        }

        $this->validate([
            'quickCreateTitle' => 'required|string|max:255',
            'quickCreateType' => 'required|in:kpi,adhoc',
            'quickCreatePriority' => 'required|in:low,medium,high,urgent',
        ]);

        $task = Task::create([
            'department_id' => $this->department->id,
            'title' => $this->quickCreateTitle,
            'task_type' => $this->quickCreateType,
            'status' => $this->quickCreateStatus,
            'priority' => $this->quickCreatePriority,
            'created_by' => auth()->id(),
        ]);

        $task->logActivity('created', null, null, 'Task created');

        $this->showQuickCreate = false;
        $this->quickCreateTitle = '';

        $this->dispatch('task-created');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterType = '';
        $this->filterAssignee = '';
        $this->filterPriority = '';
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded-full" style="background-color: {{ $department->color }}"></div>
                    <flux:heading size="xl">{{ $department->name }}</flux:heading>
                </div>
                <flux:text class="mt-2">{{ $department->description ?? __('Manage tasks for this department') }}</flux:text>
            </div>
            <div class="flex items-center gap-2">
                @if($canCreate)
                <flux:button variant="primary" :href="route('tasks.create', ['department' => $department->slug])">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    {{ __('New Task') }}
                </flux:button>
                @endif
                @if($canManage || $isReadOnly)
                <flux:button variant="ghost" :href="route('tasks.department.settings', $department->slug)">
                    <flux:icon name="cog-6-tooth" class="w-4 h-4" />
                </flux:button>
                @endif
            </div>
        </div>
    </x-slot>

    @if($isReadOnly)
    <div class="mb-6">
        <flux:callout type="info" icon="eye">
            <flux:callout.heading>{{ __('Read-Only Access') }}</flux:callout.heading>
            <flux:callout.text>{{ __('You are viewing this department in read-only mode. Only department PICs can manage tasks.') }}</flux:callout.text>
        </flux:callout>
    </div>
    @endif

    {{-- Filters --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search tasks...') }}"
                    icon="magnifying-glass"
                />
            </div>

            <flux:select wire:model.live="filterType" class="w-40">
                <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
                @foreach(TaskType::cases() as $type)
                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterPriority" class="w-40">
                <flux:select.option value="">{{ __('All Priority') }}</flux:select.option>
                @foreach(TaskPriority::cases() as $priority)
                <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterAssignee" class="w-48">
                <flux:select.option value="">{{ __('All Assignees') }}</flux:select.option>
                <flux:select.option value="unassigned">{{ __('Unassigned') }}</flux:select.option>
                @foreach($this->getDepartmentUsers() as $user)
                <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if($search || $filterType || $filterAssignee || $filterPriority)
            <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                {{ __('Clear') }}
            </flux:button>
            @endif

            <a href="{{ route('tasks.department.list', $department->slug) }}" class="text-sm text-violet-600 hover:underline">
                <flux:icon name="list-bullet" class="w-4 h-4 inline mr-1" />
                {{ __('List View') }}
            </a>
        </div>
    </div>

    {{-- Kanban Board --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"
         x-data="{
             dragging: null,
             dragOver: null
         }">
        @foreach(TaskStatus::kanbanStatuses() as $status)
        @php
            $tasks = $this->getTasksForStatus($status);
        @endphp
        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 flex flex-col min-h-[400px]"
             x-on:dragover.prevent="dragOver = '{{ $status->value }}'"
             x-on:dragleave="dragOver = null"
             x-on:drop="
                 if (dragging) {
                     $wire.moveTask(dragging, '{{ $status->value }}', 0);
                     dragging = null;
                     dragOver = null;
                 }
             "
             :class="{ 'ring-2 ring-violet-500': dragOver === '{{ $status->value }}' }">
            {{-- Column Header --}}
            <div class="p-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:icon :name="$status->icon()" class="w-4 h-4 text-{{ $status->color() }}-500" />
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $status->label() }}</span>
                    <flux:badge size="sm">{{ $tasks->count() }}</flux:badge>
                </div>
                @if($canCreate)
                <button wire:click="openQuickCreate('{{ $status->value }}')" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                    <flux:icon name="plus" class="w-5 h-5" />
                </button>
                @endif
            </div>

            {{-- Tasks --}}
            <div class="flex-1 p-2 space-y-2 overflow-y-auto">
                @forelse($tasks as $task)
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 shadow-sm hover:shadow-md transition-shadow {{ $canEdit ? 'cursor-move' : '' }}"
                     @if($canEdit)
                     draggable="true"
                     x-on:dragstart="dragging = {{ $task->id }}"
                     x-on:dragend="dragging = null"
                     @endif
                     wire:key="task-{{ $task->id }}">
                    {{-- Badges --}}
                    <div class="flex items-center justify-between mb-2">
                        <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                        <flux:badge size="sm" variant="outline" :color="$task->task_type->color()">{{ $task->task_type->label() }}</flux:badge>
                    </div>

                    {{-- Title --}}
                    <a href="{{ route('tasks.show', $task) }}" class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-violet-600 line-clamp-2 block">
                        {{ $task->title }}
                    </a>

                    {{-- Due Date --}}
                    @if($task->due_date)
                    <div class="flex items-center gap-1 mt-2 text-sm {{ $task->isOverdue() ? 'text-red-500' : 'text-zinc-500' }}">
                        <flux:icon name="calendar" class="w-4 h-4" />
                        <span>{{ $task->due_date->format('d M') }}</span>
                        @if($task->isOverdue())
                        <flux:icon name="exclamation-circle" class="w-4 h-4 text-red-500" />
                        @endif
                    </div>
                    @endif

                    {{-- Footer --}}
                    <div class="flex items-center justify-between pt-2 mt-2 border-t border-zinc-100 dark:border-zinc-700">
                        @if($task->assignee)
                        <div class="flex items-center gap-2">
                            <flux:avatar size="xs" :name="$task->assignee->name" />
                            <span class="text-xs text-zinc-500 truncate max-w-[80px]">{{ $task->assignee->name }}</span>
                        </div>
                        @else
                        <span class="text-xs text-zinc-400">{{ __('Unassigned') }}</span>
                        @endif

                        @if($task->comments_count > 0)
                        <div class="flex items-center text-zinc-400">
                            <flux:icon name="chat-bubble-left" class="w-4 h-4 mr-1" />
                            <span class="text-xs">{{ $task->comments_count }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-zinc-400">
                    <flux:icon name="inbox" class="w-8 h-8 mx-auto mb-2" />
                    <p class="text-sm">{{ __('No tasks') }}</p>
                </div>
                @endforelse
            </div>

            {{-- Quick Add Button (Bottom) --}}
            @if($canCreate)
            <div class="p-2 border-t border-zinc-200 dark:border-zinc-700">
                <button wire:click="openQuickCreate('{{ $status->value }}')"
                        class="w-full py-2 text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded transition-colors">
                    <flux:icon name="plus" class="w-4 h-4 inline mr-1" />
                    {{ __('Add Task') }}
                </button>
            </div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Quick Create Modal --}}
    <flux:modal wire:model="showQuickCreate" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Quick Add Task') }}</flux:heading>

            <form wire:submit="quickCreate" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input wire:model="quickCreateTitle" placeholder="{{ __('Enter task title...') }}" autofocus />
                    <flux:error name="quickCreateTitle" />
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Type') }}</flux:label>
                        <flux:select wire:model="quickCreateType">
                            @foreach(TaskType::cases() as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Priority') }}</flux:label>
                        <flux:select wire:model="quickCreatePriority">
                            @foreach(TaskPriority::cases() as $priority)
                            <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showQuickCreate', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Create Task') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
