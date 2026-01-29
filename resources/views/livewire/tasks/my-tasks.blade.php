<?php

declare(strict_types=1);

use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterPriority = '';
    public string $filterType = '';
    public string $sortBy = 'due_date';
    public string $sortDirection = 'asc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPriority(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function getTasks()
    {
        $query = Task::with(['department', 'creator'])
            ->where('assigned_to', auth()->id());

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhere('task_number', 'like', "%{$this->search}%");
            });
        }

        // Filters
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterType) {
            $query->where('task_type', $this->filterType);
        }

        // Sort
        if ($this->sortBy === 'priority') {
            $direction = $this->sortDirection === 'asc' ? '' : 'DESC';
            $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END $direction");
        } else {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }

        return $query->paginate(20);
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->filterPriority = '';
        $this->filterType = '';
        $this->resetPage();
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('My Tasks') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Tasks assigned to you across all departments') }}</flux:text>
            </div>
        </div>
    </x-slot>

    {{-- Filters --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search tasks...') }}"
                    icon="magnifying-glass"
                />
            </div>

            <flux:select wire:model.live="filterStatus">
                <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
                @foreach(TaskStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterPriority">
                <flux:select.option value="">{{ __('All Priority') }}</flux:select.option>
                @foreach(TaskPriority::cases() as $priority)
                <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterType">
                <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
                @foreach(TaskType::cases() as $type)
                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if($search || $filterStatus || $filterPriority || $filterType)
        <div class="mt-4">
            <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                {{ __('Clear Filters') }}
            </flux:button>
        </div>
        @endif
    </div>

    @php
        $tasks = $this->getTasks();
    @endphp

    {{-- Tasks List --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        @if($tasks->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="sort('title')">
                            <div class="flex items-center gap-1">
                                {{ __('Task') }}
                                @if($sortBy === 'title')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Department') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="sort('status')">
                            <div class="flex items-center gap-1">
                                {{ __('Status') }}
                                @if($sortBy === 'status')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="sort('priority')">
                            <div class="flex items-center gap-1">
                                {{ __('Priority') }}
                                @if($sortBy === 'priority')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Type') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="sort('due_date')">
                            <div class="flex items-center gap-1">
                                {{ __('Due Date') }}
                                @if($sortBy === 'due_date')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($tasks as $task)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                        <td class="px-4 py-4">
                            <div>
                                <a href="{{ route('tasks.show', $task) }}" class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-violet-600">
                                    {{ $task->title }}
                                </a>
                                <p class="text-sm text-zinc-500">{{ $task->task_number }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full" style="background-color: {{ $task->department->color }}"></div>
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $task->department->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <flux:badge size="sm" :color="$task->status->color()">{{ $task->status->label() }}</flux:badge>
                        </td>
                        <td class="px-4 py-4">
                            <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                        </td>
                        <td class="px-4 py-4">
                            <flux:badge size="sm" variant="outline" :color="$task->task_type->color()">{{ $task->task_type->label() }}</flux:badge>
                        </td>
                        <td class="px-4 py-4">
                            @if($task->due_date)
                            <span class="{{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-zinc-600 dark:text-zinc-400' }}">
                                {{ $task->due_date->format('d M Y') }}
                                @if($task->isOverdue())
                                <span class="block text-xs">{{ __('Overdue') }}</span>
                                @endif
                            </span>
                            @else
                            <span class="text-zinc-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right">
                            <flux:button variant="ghost" size="sm" :href="route('tasks.show', $task)">
                                {{ __('View') }}
                            </flux:button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-700">
            {{ $tasks->links() }}
        </div>
        @else
        <div class="p-8 text-center">
            <flux:icon name="clipboard-document-list" class="w-12 h-12 mx-auto text-zinc-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No Tasks Found') }}</flux:heading>
            <flux:text class="mt-2">{{ __('You have no tasks assigned to you at the moment.') }}</flux:text>
        </div>
        @endif
    </div>
</div>
