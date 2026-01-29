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

    public string $view = 'focus';
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

    public function setView(string $view): void
    {
        $this->view = $view;
    }

    public function getQuickStats(): array
    {
        $userId = auth()->id();
        $baseQuery = Task::where('assigned_to', $userId);

        return [
            'today' => (clone $baseQuery)->whereIn('status', ['todo', 'in_progress', 'review'])
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('due_date')->whereDate('due_date', '<=', today());
                    });
                })->count(),
            'in_progress' => (clone $baseQuery)->where('status', 'in_progress')->count(),
            'todo' => (clone $baseQuery)->where('status', 'todo')->count(),
            'completed_week' => (clone $baseQuery)->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(7))->count(),
        ];
    }

    public function getTodayTasks()
    {
        return Task::with(['department', 'creator'])
            ->where('assigned_to', auth()->id())
            ->whereIn('status', ['todo', 'in_progress', 'review'])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('due_date')->whereDate('due_date', '<=', today());
                });
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->get();
    }

    public function getUpcomingTasks()
    {
        return Task::with(['department', 'creator'])
            ->where('assigned_to', auth()->id())
            ->whereIn('status', ['todo', 'in_progress', 'review'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>', today())
            ->whereDate('due_date', '<=', today()->addDays(7))
            ->orderBy('due_date')
            ->get();
    }

    public function getGroupedTasks(): array
    {
        $tasks = Task::with(['department', 'creator'])
            ->where('assigned_to', auth()->id())
            ->whereIn('status', ['in_progress', 'todo', 'review'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->get();

        return [
            'in_progress' => $tasks->where('status', TaskStatus::InProgress),
            'todo' => $tasks->where('status', TaskStatus::Todo),
            'review' => $tasks->where('status', TaskStatus::Review),
        ];
    }

    public function getTasks()
    {
        $query = Task::with(['department', 'creator'])
            ->where('assigned_to', auth()->id());

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhere('task_number', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterType) {
            $query->where('task_type', $this->filterType);
        }

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
            <div class="flex items-center gap-2">
                <flux:button variant="{{ $view === 'focus' ? 'primary' : 'ghost' }}" size="sm" wire:click="setView('focus')">
                    <div class="flex items-center justify-center">
                        <flux:icon name="squares-2x2" class="w-4 h-4 mr-1" />
                        {{ __('Focus') }}
                    </div>
                </flux:button>
                <flux:button variant="{{ $view === 'list' ? 'primary' : 'ghost' }}" size="sm" wire:click="setView('list')">
                    <div class="flex items-center justify-center">
                        <flux:icon name="list-bullet" class="w-4 h-4 mr-1" />
                        {{ __('List') }}
                    </div>
                </flux:button>
            </div>
        </div>
    </x-slot>

    @if($view === 'focus')
        {{-- Quick Stats --}}
        @php $stats = $this->getQuickStats(); @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <flux:icon name="fire" class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['today'] }}</p>
                        <p class="text-xs text-zinc-500">{{ __("Today's Focus") }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <flux:icon name="arrow-path" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['in_progress'] }}</p>
                        <p class="text-xs text-zinc-500">{{ __('In Progress') }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <flux:icon name="inbox-stack" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['todo'] }}</p>
                        <p class="text-xs text-zinc-500">{{ __('To Do') }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['completed_week'] }}</p>
                        <p class="text-xs text-zinc-500">{{ __('Done (7d)') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Today's Focus Section --}}
        @php $todayTasks = $this->getTodayTasks(); @endphp
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-3">
                <flux:icon name="fire" class="w-5 h-5 text-red-500" />
                <flux:heading size="lg">{{ __("Today's Focus") }}</flux:heading>
                <flux:badge size="sm" color="red">{{ $todayTasks->count() }}</flux:badge>
            </div>

            @if($todayTasks->count() > 0)
            <div class="space-y-3">
                @foreach($todayTasks as $task)
                <a href="{{ route('tasks.show', $task) }}" wire:key="today-{{ $task->id }}" class="block bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 hover:border-violet-300 dark:hover:border-violet-600 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $task->department->color }}"></div>
                            <div class="min-w-0">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-zinc-500">{{ $task->task_number }}</span>
                                    <span class="text-xs text-zinc-400">&middot;</span>
                                    <span class="text-xs text-zinc-500">{{ $task->department->name }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                            <flux:badge size="sm" :color="$task->status->color()">{{ $task->status->label() }}</flux:badge>
                            @if($task->due_date && $task->isOverdue())
                            <span class="text-xs font-medium text-red-500">{{ __('Overdue') }}</span>
                            @elseif($task->due_date)
                            <span class="text-xs text-zinc-500">{{ $task->due_date->format('d M') }}</span>
                            @endif
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            @else
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <flux:icon name="check-circle" class="w-10 h-10 mx-auto text-green-400" />
                <p class="mt-2 text-sm text-zinc-500">{{ __("You're all caught up! No tasks due today.") }}</p>
            </div>
            @endif
        </div>

        {{-- Upcoming This Week --}}
        @php $upcomingTasks = $this->getUpcomingTasks(); @endphp
        @if($upcomingTasks->count() > 0)
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-3">
                <flux:icon name="calendar" class="w-5 h-5 text-blue-500" />
                <flux:heading size="lg">{{ __('Upcoming This Week') }}</flux:heading>
                <flux:badge size="sm" color="blue">{{ $upcomingTasks->count() }}</flux:badge>
            </div>

            <div class="space-y-2">
                @foreach($upcomingTasks as $task)
                <a href="{{ route('tasks.show', $task) }}" wire:key="upcoming-{{ $task->id }}" class="block bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 hover:border-violet-300 dark:hover:border-violet-600 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $task->department->color }}"></div>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                            <span class="text-xs text-zinc-500">{{ $task->due_date->format('D, d M') }}</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Grouped by Status --}}
        @php $grouped = $this->getGroupedTasks(); @endphp
        <div>
            <div class="flex items-center gap-2 mb-3">
                <flux:icon name="squares-2x2" class="w-5 h-5 text-violet-500" />
                <flux:heading size="lg">{{ __('All Active Tasks') }}</flux:heading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- In Progress --}}
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('In Progress') }}</span>
                        <flux:badge size="sm">{{ $grouped['in_progress']->count() }}</flux:badge>
                    </div>
                    <div class="space-y-2">
                        @forelse($grouped['in_progress'] as $task)
                        <a href="{{ route('tasks.show', $task) }}" wire:key="ip-{{ $task->id }}" class="block bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-zinc-500">{{ $task->department->name }}</span>
                                <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                            </div>
                            @if($task->due_date)
                            <p class="text-xs mt-1 {{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-zinc-400' }}">
                                {{ $task->due_date->format('d M Y') }}
                                @if($task->isOverdue()) ({{ __('Overdue') }}) @endif
                            </p>
                            @endif
                        </a>
                        @empty
                        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 text-center">
                            <p class="text-xs text-zinc-400">{{ __('No tasks') }}</p>
                        </div>
                        @endforelse
                    </div>
                </div>

                {{-- To Do --}}
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-3 h-3 rounded-full bg-zinc-400"></div>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('To Do') }}</span>
                        <flux:badge size="sm">{{ $grouped['todo']->count() }}</flux:badge>
                    </div>
                    <div class="space-y-2">
                        @forelse($grouped['todo'] as $task)
                        <a href="{{ route('tasks.show', $task) }}" wire:key="td-{{ $task->id }}" class="block bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 hover:border-zinc-400 dark:hover:border-zinc-500 transition-colors">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-zinc-500">{{ $task->department->name }}</span>
                                <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                            </div>
                            @if($task->due_date)
                            <p class="text-xs mt-1 {{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-zinc-400' }}">
                                {{ $task->due_date->format('d M Y') }}
                                @if($task->isOverdue()) ({{ __('Overdue') }}) @endif
                            </p>
                            @endif
                        </a>
                        @empty
                        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 text-center">
                            <p class="text-xs text-zinc-400">{{ __('No tasks') }}</p>
                        </div>
                        @endforelse
                    </div>
                </div>

                {{-- Review --}}
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Review') }}</span>
                        <flux:badge size="sm">{{ $grouped['review']->count() }}</flux:badge>
                    </div>
                    <div class="space-y-2">
                        @forelse($grouped['review'] as $task)
                        <a href="{{ route('tasks.show', $task) }}" wire:key="rv-{{ $task->id }}" class="block bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 hover:border-amber-300 dark:hover:border-amber-600 transition-colors">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-zinc-500">{{ $task->department->name }}</span>
                                <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                            </div>
                            @if($task->due_date)
                            <p class="text-xs mt-1 {{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-zinc-400' }}">
                                {{ $task->due_date->format('d M Y') }}
                                @if($task->isOverdue()) ({{ __('Overdue') }}) @endif
                            </p>
                            @endif
                        </a>
                        @empty
                        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 text-center">
                            <p class="text-xs text-zinc-400">{{ __('No tasks') }}</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- List View --}}
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
                        <tr wire:key="list-{{ $task->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
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
    @endif
</div>
