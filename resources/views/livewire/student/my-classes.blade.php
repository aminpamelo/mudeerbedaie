<?php

use App\Models\ClassModel;
use App\Models\ClassStudent;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'active';
    public string $courseFilter = '';
    public bool $showFilters = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingCourseFilter()
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status)
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->courseFilter = '';
        $this->resetPage();
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function with(): array
    {
        $student = auth()->user()->student;

        // Get all classes this student is enrolled in
        $query = ClassStudent::where('student_id', $student->id)
            ->with(['class.course', 'class.teacher.user', 'class.sessions', 'class.timetable'])
            ->when($this->search, function ($query) {
                $query->whereHas('class', function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%')
                        ->orWhereHas('course', function ($courseQuery) {
                            $courseQuery->where('name', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('teacher.user', function ($teacherQuery) {
                            $teacherQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->courseFilter, function ($query) {
                $query->whereHas('class', function ($q) {
                    $q->where('course_id', $this->courseFilter);
                });
            })
            ->orderBy('enrolled_at', 'desc');

        $classStudents = $query->paginate(12);

        // Get available courses for filter
        $courses = ClassModel::whereHas('classStudents', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->with('course')->get()->pluck('course')->unique('id')->values();

        // Get counts by status
        $statusCounts = [
            'active' => ClassStudent::where('student_id', $student->id)->where('status', 'active')->count(),
            'completed' => ClassStudent::where('student_id', $student->id)->where('status', 'completed')->count(),
            'transferred' => ClassStudent::where('student_id', $student->id)->where('status', 'transferred')->count(),
            'quit' => ClassStudent::where('student_id', $student->id)->where('status', 'quit')->count(),
        ];

        return [
            'classStudents' => $classStudents,
            'courses' => $courses,
            'statusCounts' => $statusCounts,
            'totalClasses' => array_sum($statusCounts),
            'hasActiveFilters' => $this->search || $this->statusFilter || $this->courseFilter,
        ];
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('student.classes.my_classes') }}</flux:heading>
            <flux:text class="mt-2">{{ trans_choice('student.classes.classes_enrolled', $totalClasses, ['count' => $totalClasses]) }}</flux:text>
        </div>
    </div>

    {{-- Quick Filter Chips --}}
    <div class="flex flex-wrap gap-2 mb-4">
        <button
            wire:click="setStatusFilter('active')"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                   {{ $statusFilter === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
        >
            <span class="w-2 h-2 rounded-full {{ $statusFilter === 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
            {{ __('student.classes.active') }}
            @if($statusCounts['active'] > 0)
                <span class="ml-0.5 text-xs {{ $statusFilter === 'active' ? 'text-green-600' : 'text-gray-500' }}">{{ $statusCounts['active'] }}</span>
            @endif
        </button>

        <button
            wire:click="setStatusFilter('completed')"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                   {{ $statusFilter === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
        >
            <span class="w-2 h-2 rounded-full {{ $statusFilter === 'completed' ? 'bg-blue-500' : 'bg-gray-400' }}"></span>
            {{ __('student.classes.completed') }}
            @if($statusCounts['completed'] > 0)
                <span class="ml-0.5 text-xs {{ $statusFilter === 'completed' ? 'text-blue-600' : 'text-gray-500' }}">{{ $statusCounts['completed'] }}</span>
            @endif
        </button>

        @if($statusCounts['transferred'] > 0 || $statusCounts['quit'] > 0)
            <button
                wire:click="toggleFilters"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
            >
                <flux:icon name="adjustments-horizontal" class="w-4 h-4" />
                {{ __('student.classes.more') }}
            </button>
        @endif
    </div>

    {{-- Search Bar --}}
    <div class="mb-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('student.classes.search_placeholder') }}"
            icon="magnifying-glass"
        />
    </div>

    {{-- Expanded Filters (collapsible) --}}
    @if($showFilters || $courseFilter)
        <flux:card class="mb-4">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">{{ __('student.classes.filters') }}</flux:heading>
                    @if($hasActiveFilters)
                        <flux:button wire:click="clearFilters" variant="ghost" size="sm">
                            {{ __('student.classes.clear_all') }}
                        </flux:button>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Course Filter --}}
                    <div>
                        <flux:field>
                            <flux:label>{{ __('student.classes.course') }}</flux:label>
                            <flux:select wire:model.live="courseFilter">
                                <flux:select.option value="">{{ __('student.classes.all_courses') }}</flux:select.option>
                                @foreach($courses as $course)
                                    <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>

                    {{-- Status Filter (for transferred/quit) --}}
                    <div>
                        <flux:field>
                            <flux:label>{{ __('student.classes.status') }}</flux:label>
                            <flux:select wire:model.live="statusFilter">
                                <flux:select.option value="">{{ __('student.classes.all_status') }}</flux:select.option>
                                <flux:select.option value="active">{{ __('student.classes.active') }}</flux:select.option>
                                <flux:select.option value="completed">{{ __('student.classes.completed') }}</flux:select.option>
                                @if($statusCounts['transferred'] > 0)
                                    <flux:select.option value="transferred">{{ __('student.classes.transferred') }}</flux:select.option>
                                @endif
                                @if($statusCounts['quit'] > 0)
                                    <flux:select.option value="quit">{{ __('student.classes.quit') }}</flux:select.option>
                                @endif
                            </flux:select>
                        </flux:field>
                    </div>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- Classes List --}}
    <div wire:loading.delay class="space-y-3">
        @for($i = 0; $i < 3; $i++)
            <x-student.skeleton type="class-card" />
        @endfor
    </div>

    <div wire:loading.delay.remove>
        @if($classStudents->count() > 0)
            <div class="space-y-3">
                @foreach($classStudents as $classStudent)
                    <a
                        href="{{ route('student.classes.show', $classStudent->class) }}"
                        wire:navigate
                        wire:key="class-{{ $classStudent->id }}"
                        class="block"
                    >
                        <flux:card class="hover:bg-gray-50 active:bg-gray-100 transition-all duration-150">
                            <div class="flex items-start gap-4">
                                {{-- Course Icon/Badge --}}
                                <div class="flex-shrink-0 w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                                    <flux:icon name="academic-cap" class="w-6 h-6 text-blue-600" />
                                </div>

                                {{-- Class Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <flux:heading size="sm" class="truncate">{{ $classStudent->class->title }}</flux:heading>
                                            <flux:text size="sm" class="text-gray-500 truncate">{{ $classStudent->class->course->name }}</flux:text>
                                        </div>

                                        {{-- Status Badge --}}
                                        @if($classStudent->status === 'active')
                                            <flux:badge variant="success" size="sm">{{ __('student.classes.active') }}</flux:badge>
                                        @elseif($classStudent->status === 'completed')
                                            <flux:badge variant="gray" size="sm">{{ __('student.classes.completed') }}</flux:badge>
                                        @elseif($classStudent->status === 'transferred')
                                            <flux:badge variant="warning" size="sm">{{ __('student.classes.transferred') }}</flux:badge>
                                        @else
                                            <flux:badge variant="danger" size="sm">{{ __('student.classes.quit') }}</flux:badge>
                                        @endif
                                    </div>

                                    {{-- Teacher & Sessions --}}
                                    <div class="flex items-center gap-4 mt-2">
                                        <div class="flex items-center gap-1.5 text-gray-500">
                                            <flux:icon name="user-circle" class="w-4 h-4" />
                                            <flux:text size="xs">{{ $classStudent->class->teacher->user->name }}</flux:text>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-gray-500">
                                            <flux:icon name="calendar-days" class="w-4 h-4" />
                                            <flux:text size="xs">
                                                {{ __('student.classes.sessions', ['completed' => $classStudent->class->sessions->whereIn('status', ['completed', 'no_show'])->count(), 'total' => $classStudent->class->sessions->count()]) }}
                                            </flux:text>
                                        </div>
                                    </div>
                                </div>

                                {{-- Chevron --}}
                                <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 flex-shrink-0" />
                            </div>
                        </flux:card>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($classStudents->hasPages())
                <div class="mt-6">
                    {{ $classStudents->links() }}
                </div>
            @endif
        @else
            {{-- Empty State --}}
            <flux:card class="!p-0 overflow-hidden">
                @if($hasActiveFilters)
                    <x-student.empty-state type="search-no-results">
                        <flux:button wire:click="clearFilters" variant="primary" size="sm">
                            {{ __('student.classes.clear_all') }}
                        </flux:button>
                    </x-student.empty-state>
                @else
                    <x-student.empty-state type="no-classes" />
                @endif
            </flux:card>
        @endif
    </div>
</div>
