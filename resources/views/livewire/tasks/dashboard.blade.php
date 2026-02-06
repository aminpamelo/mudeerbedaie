<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        $user = auth()->user();

        // Check access
        if (! $user->hasTaskManagementAccess()) {
            abort(403, 'You do not have access to task management.');
        }
    }

    public function getDepartments()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return Department::active()->topLevel()->ordered()->with(['children' => function ($q) {
                $q->active()->ordered();
            }])->get();
        }

        // Include direct departments + child departments of PIC parents
        $departments = $user->getAccessibleDepartments();

        // Eager load children for parent departments
        $parentIds = $departments->whereNull('parent_id')->pluck('id');
        if ($parentIds->isNotEmpty()) {
            $children = Department::whereIn('parent_id', $parentIds)->active()->ordered()->get();
            $departments->each(function ($dept) use ($children) {
                if ($dept->parent_id === null) {
                    $dept->setRelation('children', $children->where('parent_id', $dept->id)->values());
                }
            });
        }

        return $departments->whereNull('parent_id')->values();
    }

    public function getPicDepartments()
    {
        return auth()->user()->getCreatableDepartments();
    }

    public function canCreateTasks(): bool
    {
        $user = auth()->user();
        return $user->isAdmin() || $user->picDepartments()->exists();
    }

    public function getTaskStats(): array
    {
        $user = auth()->user();
        $query = Task::query();

        // Filter by accessible departments (includes child departments)
        if (! $user->isAdmin()) {
            $departmentIds = $user->getAccessibleDepartmentIds();
            $query->whereIn('department_id', $departmentIds);
        }

        $total = (clone $query)->count();
        $todo = (clone $query)->where('status', TaskStatus::TODO)->count();
        $inProgress = (clone $query)->where('status', TaskStatus::IN_PROGRESS)->count();
        $review = (clone $query)->where('status', TaskStatus::REVIEW)->count();
        $completed = (clone $query)->where('status', TaskStatus::COMPLETED)->count();
        $overdue = (clone $query)->overdue()->count();

        return [
            'total' => $total,
            'todo' => $todo,
            'in_progress' => $inProgress,
            'review' => $review,
            'completed' => $completed,
            'overdue' => $overdue,
        ];
    }

    public function getMyTasks()
    {
        return Task::with(['department'])
            ->where('assigned_to', auth()->id())
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('due_date')
            ->limit(5)
            ->get();
    }

    public function getUrgentTasks()
    {
        $user = auth()->user();
        $query = Task::with(['department', 'assignee'])
            ->where('priority', TaskPriority::URGENT)
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED]);

        if (! $user->isAdmin()) {
            $departmentIds = $user->getAccessibleDepartmentIds();
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->orderBy('due_date')->limit(5)->get();
    }

    public function getOverdueTasks()
    {
        $user = auth()->user();
        $query = Task::with(['department', 'assignee'])->overdue();

        if (! $user->isAdmin()) {
            $departmentIds = $user->getAccessibleDepartmentIds();
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->orderBy('due_date')->limit(5)->get();
    }

    public function getRecentTasks()
    {
        $user = auth()->user();
        $query = Task::with(['department', 'assignee', 'creator']);

        if (! $user->isAdmin()) {
            $departmentIds = $user->getAccessibleDepartmentIds();
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->latest()->limit(8)->get();
    }

    public function getGreeting(): string
    {
        $hour = now()->hour;

        if ($hour < 12) {
            return __('Good Morning');
        } elseif ($hour < 17) {
            return __('Good Afternoon');
        } else {
            return __('Good Evening');
        }
    }
}; ?>

<div>
    @php
        $user = auth()->user();
        $stats = $this->getTaskStats();
        $departments = $this->getDepartments();
        $picDepartments = $this->getPicDepartments();
        $myTasks = $this->getMyTasks();
        $urgentTasks = $this->getUrgentTasks();
        $overdueTasks = $this->getOverdueTasks();
        $recentTasks = $this->getRecentTasks();
        $canCreate = $this->canCreateTasks();
        $greeting = $this->getGreeting();
    @endphp

    {{-- Hero Section with Welcome & Quick Actions --}}
    <div class="mb-8 bg-gradient-to-br from-violet-600 to-indigo-700 rounded-2xl p-6 md:p-8 text-white relative overflow-hidden">
        {{-- Background Decorative Elements --}}
        <div class="absolute top-0 right-0 -mt-8 -mr-8 w-64 h-64 bg-white/5 rounded-full blur-2xl"></div>
        <div class="absolute bottom-0 left-0 -mb-12 -ml-12 w-48 h-48 bg-white/5 rounded-full blur-2xl"></div>

        <div class="relative z-10">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold">{{ $greeting }}, {{ $user->name }}!</h1>
                    <p class="mt-2 text-violet-100 text-sm md:text-base">
                        @if($user->isAdmin())
                            {{ __(':total tasks across all departments.', ['total' => $stats['total']]) }}
                        @elseif($stats['overdue'] > 0)
                            {{ __('You have :count overdue tasks that need attention.', ['count' => $stats['overdue']]) }}
                        @elseif($stats['in_progress'] > 0)
                            {{ __('You have :count tasks in progress. Keep up the good work!', ['count' => $stats['in_progress']]) }}
                        @else
                            {{ __('Ready to be productive today?') }}
                        @endif
                    </p>
                </div>

                {{-- Quick Actions for PIC --}}
                @if($canCreate)
                <div class="flex flex-wrap justify-end gap-2 shrink-0 md:ml-auto">
                    <flux:button variant="filled" class="!bg-white !text-violet-700 hover:!bg-violet-50" :href="route('tasks.create')">
                        <div class="flex items-center justify-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-1" />
                            {{ __('New Task') }}
                        </div>
                    </flux:button>
                    @if($picDepartments->count() === 1)
                    <flux:button variant="ghost" class="!text-white !border-white/30 hover:!bg-white/10" :href="route('tasks.department.board', $picDepartments->first()->slug)">
                        <div class="flex items-center justify-center">
                            <flux:icon name="view-columns" class="w-4 h-4 mr-1" />
                            {{ __('Kanban Board') }}
                        </div>
                    </flux:button>
                    @endif
                </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="mb-8 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Tasks') }}</p>
                    <p class="text-3xl font-bold text-zinc-900 dark:text-white mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                    <flux:icon name="clipboard-document-list" class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('In Progress') }}</p>
                    <p class="text-3xl font-bold text-zinc-900 dark:text-white mt-1">{{ $stats['in_progress'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <flux:icon name="play-circle" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
                    <p class="text-3xl font-bold text-zinc-900 dark:text-white mt-1">{{ $stats['completed'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-xl border {{ $stats['overdue'] > 0 ? 'border-red-300 dark:border-red-700' : 'border-zinc-200 dark:border-zinc-700' }} p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Overdue') }}</p>
                    <p class="text-3xl font-bold {{ $stats['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-white' }} mt-1">{{ $stats['overdue'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg {{ $stats['overdue'] > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-zinc-100 dark:bg-zinc-700' }} flex items-center justify-center">
                    <flux:icon name="exclamation-triangle" class="w-5 h-5 {{ $stats['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}" />
                </div>
            </div>
        </div>
    </div>

    {{-- Urgent & Overdue Section --}}
    @if($urgentTasks->count() > 0 || $overdueTasks->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Overdue Tasks --}}
        @if($overdueTasks->count() > 0)
        <div class="bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-800">
            <div class="p-4 border-b border-red-200 dark:border-red-800 flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500" />
                <flux:heading size="md" class="!text-red-700 dark:!text-red-400">{{ __('Overdue Tasks') }}</flux:heading>
                <flux:badge size="sm" color="red">{{ $overdueTasks->count() }}</flux:badge>
            </div>
            <div class="p-4 space-y-2">
                @foreach($overdueTasks as $task)
                <a href="{{ route('tasks.show', $task) }}"
                   class="flex items-center justify-between p-3 rounded-lg bg-white dark:bg-zinc-800 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                        <p class="text-sm text-zinc-500">
                            {{ $task->department?->name ?? __('Personal') }}
                            @if($task->assignee)
                                &bull; {{ $task->assignee->name }}
                            @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-red-600">{{ $task->due_date?->diffForHumans() }}</p>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Urgent Tasks --}}
        @if($urgentTasks->count() > 0)
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
            <div class="p-4 border-b border-amber-200 dark:border-amber-800 flex items-center gap-2">
                <flux:icon name="fire" class="w-5 h-5 text-amber-500" />
                <flux:heading size="md" class="!text-amber-700 dark:!text-amber-400">{{ __('Urgent Tasks') }}</flux:heading>
                <flux:badge size="sm" color="amber">{{ $urgentTasks->count() }}</flux:badge>
            </div>
            <div class="p-4 space-y-2">
                @foreach($urgentTasks as $task)
                <a href="{{ route('tasks.show', $task) }}"
                   class="flex items-center justify-between p-3 rounded-lg bg-white dark:bg-zinc-800 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                        <p class="text-sm text-zinc-500">
                            {{ $task->department?->name ?? __('Personal') }}
                            @if($task->assignee)
                                &bull; {{ $task->assignee->name }}
                            @endif
                        </p>
                    </div>
                    <flux:badge size="sm" :color="$task->status->color()">{{ $task->status->label() }}</flux:badge>
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Departments / Workspaces --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon name="squares-2x2" class="w-5 h-5 text-violet-500" />
                        <flux:heading size="md">{{ __('Workspaces') }}</flux:heading>
                    </div>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse($departments as $department)
                    @php
                        $activeTasks = $department->tasks()->whereNotIn('status', ['completed', 'cancelled'])->count();
                        $isPic = $user->isPicOfDepartment($department);
                        $hasChildren = $department->children && $department->children->count() > 0;
                    @endphp
                    <a href="{{ route('tasks.department.board', $department->slug) }}"
                       class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: {{ $department->color }}20;">
                                <div class="w-4 h-4 rounded-full" style="background-color: {{ $department->color }}"></div>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-zinc-100 group-hover:text-violet-600">{{ $department->name }}</p>
                                <p class="text-sm text-zinc-500">
                                    {{ $activeTasks }} {{ __('active tasks') }}
                                    @if($hasChildren)
                                        &bull; {{ $department->children->count() }} {{ __('sub-departments') }}
                                    @endif
                                    @if($isPic)
                                        <span class="text-violet-500">&bull; {{ __('PIC') }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400 group-hover:text-violet-500" />
                    </a>
                    {{-- Child departments --}}
                    @if($hasChildren)
                        @foreach($department->children as $child)
                        @php
                            $childActiveTasks = $child->tasks()->whereNotIn('status', ['completed', 'cancelled'])->count();
                            $isChildPic = $user->isPicOfDepartment($child);
                        @endphp
                        <a href="{{ route('tasks.department.board', $child->slug) }}"
                           class="flex items-center justify-between p-3 pl-8 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: {{ $child->color }}20;">
                                    <div class="w-3 h-3 rounded-full" style="background-color: {{ $child->color }}"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-violet-600">{{ $child->name }}</p>
                                    <p class="text-xs text-zinc-500">
                                        {{ $childActiveTasks }} {{ __('active tasks') }}
                                        @if($isChildPic)
                                            <span class="text-violet-500">&bull; {{ __('PIC') }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <flux:icon name="chevron-right" class="w-4 h-4 text-zinc-400 group-hover:text-violet-500" />
                        </a>
                        @endforeach
                    @endif
                    @empty
                    <div class="p-8 text-center">
                        <flux:icon name="folder" class="w-12 h-12 mx-auto text-zinc-300" />
                        <p class="mt-2 text-zinc-500">{{ __('No workspaces available.') }}</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- My Tasks --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon name="user" class="w-5 h-5 text-blue-500" />
                        <flux:heading size="md">{{ __('My Tasks') }}</flux:heading>
                        @if($myTasks->count() > 0)
                        <flux:badge size="sm">{{ $myTasks->count() }}</flux:badge>
                        @endif
                    </div>
                    <a href="{{ route('tasks.my-tasks') }}" class="text-sm text-violet-600 hover:underline">{{ __('View all') }}</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse($myTasks as $task)
                    <a href="{{ route('tasks.show', $task) }}"
                       class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="w-2 h-2 rounded-full" style="background-color: {{ $task->department?->color ?? '#8b5cf6' }}"></div>
                                    <span class="text-sm text-zinc-500">{{ $task->department?->name ?? __('Personal') }}</span>
                                </div>
                            </div>
                            <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                        </div>
                        @if($task->due_date)
                        <div class="flex items-center gap-1 mt-2 text-sm {{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-zinc-500' }}">
                            <flux:icon name="calendar" class="w-4 h-4" />
                            <span>{{ $task->due_date->format('d M Y') }}</span>
                            @if($task->isOverdue())
                            <flux:icon name="exclamation-circle" class="w-4 h-4" />
                            @endif
                        </div>
                        @endif
                    </a>
                    @empty
                    <div class="p-8 text-center">
                        <flux:icon name="check-circle" class="w-12 h-12 mx-auto text-green-300" />
                        <p class="mt-2 text-zinc-500">{{ __('No tasks assigned to you.') }}</p>
                        <p class="text-sm text-zinc-400">{{ __("You're all caught up!") }}</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center gap-2">
                    <flux:icon name="clock" class="w-5 h-5 text-zinc-500" />
                    <flux:heading size="md">{{ __('Recent Activity') }}</flux:heading>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700 max-h-[400px] overflow-y-auto">
                    @forelse($recentTasks as $task)
                    <a href="{{ route('tasks.show', $task) }}"
                       class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center flex-shrink-0">
                                <flux:icon :name="$task->status->icon()" class="w-4 h-4 text-{{ $task->status->color() }}-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $task->title }}</p>
                                <p class="text-sm text-zinc-500 mt-0.5">
                                    {{ $task->department?->name ?? __('Personal') }}
                                    &bull; {{ $task->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <flux:badge size="sm" :color="$task->status->color()">{{ $task->status->label() }}</flux:badge>
                        </div>
                    </a>
                    @empty
                    <div class="p-8 text-center">
                        <flux:icon name="inbox" class="w-12 h-12 mx-auto text-zinc-300" />
                        <p class="mt-2 text-zinc-500">{{ __('No recent activity.') }}</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
