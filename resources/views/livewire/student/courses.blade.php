<?php
use App\Models\Course;
use App\Models\Enrollment;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $teacherFilter = '';

    public $statusFilter = '';

    public $feeRangeFilter = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTeacherFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingFeeRangeFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->teacherFilter = '';
        $this->statusFilter = '';
        $this->feeRangeFilter = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $student = auth()->user()->student;

        // If user doesn't have a student record, show all courses as not enrolled
        if (! $student) {
            $query = Course::with(['teacher.user', 'feeSettings', 'enrollments', 'activeEnrollments'])
                ->where('status', 'active')
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('description', 'like', '%'.$this->search.'%')
                            ->orWhereHas('teacher.user', function ($teacherQuery) {
                                $teacherQuery->where('name', 'like', '%'.$this->search.'%');
                            });
                    });
                })
                ->when($this->teacherFilter, function ($query) {
                    $query->where('teacher_id', $this->teacherFilter);
                })
                ->when($this->feeRangeFilter, function ($query) {
                    switch ($this->feeRangeFilter) {
                        case 'free':
                            $query->whereHas('feeSettings', function ($feeQuery) {
                                $feeQuery->where('fee_amount', 0);
                            });
                            break;
                        case '1-50':
                            $query->whereHas('feeSettings', function ($feeQuery) {
                                $feeQuery->whereBetween('fee_amount', [1, 50]);
                            });
                            break;
                        case '51-100':
                            $query->whereHas('feeSettings', function ($feeQuery) {
                                $feeQuery->whereBetween('fee_amount', [51, 100]);
                            });
                            break;
                        case '101+':
                            $query->whereHas('feeSettings', function ($feeQuery) {
                                $feeQuery->where('fee_amount', '>', 100);
                            });
                            break;
                    }
                })
                ->orderBy('name');

            $teachers = Course::with('teacher.user')
                ->where('status', 'active')
                ->get()
                ->pluck('teacher')
                ->filter() // Remove null teachers
                ->filter(fn ($teacher) => $teacher->user !== null) // Remove teachers without users
                ->unique('id')
                ->values();

            return [
                'courses' => $query->paginate(12),
                'studentEnrollments' => collect(),
                'teachers' => $teachers,
                'totalActiveCourses' => Course::where('status', 'active')->count(),
                'totalEnrolledCourses' => 0,
            ];
        }

        // Get all courses with eager loading
        $query = Course::with(['teacher.user', 'feeSettings', 'enrollments', 'activeEnrollments'])
            ->where('status', 'active')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('teacher.user', function ($teacherQuery) {
                            $teacherQuery->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->teacherFilter, function ($query) {
                $query->where('teacher_id', $this->teacherFilter);
            })
            ->when($this->statusFilter === 'enrolled', function ($query) use ($student) {
                $query->whereHas('enrollments', function ($enrollmentQuery) use ($student) {
                    $enrollmentQuery->where('student_id', $student->id)
                        ->whereIn('status', ['enrolled', 'active']);
                });
            })
            ->when($this->statusFilter === 'not_enrolled', function ($query) use ($student) {
                $query->whereDoesntHave('enrollments', function ($enrollmentQuery) use ($student) {
                    $enrollmentQuery->where('student_id', $student->id)
                        ->whereIn('status', ['enrolled', 'active']);
                });
            })
            ->when($this->feeRangeFilter, function ($query) {
                switch ($this->feeRangeFilter) {
                    case 'free':
                        $query->whereHas('feeSettings', function ($feeQuery) {
                            $feeQuery->where('fee_amount', 0);
                        });
                        break;
                    case '1-50':
                        $query->whereHas('feeSettings', function ($feeQuery) {
                            $feeQuery->whereBetween('fee_amount', [1, 50]);
                        });
                        break;
                    case '51-100':
                        $query->whereHas('feeSettings', function ($feeQuery) {
                            $feeQuery->whereBetween('fee_amount', [51, 100]);
                        });
                        break;
                    case '101+':
                        $query->whereHas('feeSettings', function ($feeQuery) {
                            $feeQuery->where('fee_amount', '>', 100);
                        });
                        break;
                }
            })
            ->orderBy('name');

        // Get student's enrollments for status checking
        $studentEnrollments = collect();
        if ($student) {
            $studentEnrollments = Enrollment::where('student_id', $student->id)
                ->whereIn('status', ['enrolled', 'active'])
                ->pluck('course_id');
        }

        // Get available teachers for filter
        $teachers = Course::with('teacher.user')
            ->where('status', 'active')
            ->get()
            ->pluck('teacher')
            ->filter() // Remove null teachers
            ->filter(fn ($teacher) => $teacher->user !== null) // Remove teachers without users
            ->unique('id')
            ->values();

        return [
            'courses' => $query->paginate(12),
            'studentEnrollments' => $studentEnrollments,
            'teachers' => $teachers,
            'totalActiveCourses' => Course::where('status', 'active')->count(),
            'totalEnrolledCourses' => $student ? $student->activeEnrollments()->count() : 0,
        ];
    }

    public function enroll($courseId)
    {
        // This would redirect to enrollment/payment process
        // For now, we'll just show a message
        session()->flash('message', 'Enrollment feature will be implemented soon!');
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('student.courses.available_courses') }}</flux:heading>
            <flux:text class="mt-2 text-gray-600 dark:text-gray-400">{{ __('student.courses.discover_enroll') }}</flux:text>
        </div>
        <flux:button href="{{ route('student.subscriptions') }}" variant="outline" class="self-start sm:self-auto">
            <div class="flex items-center justify-center">
                <flux:icon name="bookmark" class="w-4 h-4 mr-1" />
                {{ __('student.courses.my_enrollments') }}
            </div>
        </flux:button>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <flux:card class="text-center sm:text-left">
            <div class="flex flex-col sm:flex-row items-center sm:justify-between gap-2">
                <div>
                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ __('student.courses.available') }}</flux:text>
                    <flux:heading size="lg" class="text-blue-600 dark:text-blue-400">{{ number_format($totalActiveCourses) }}</flux:heading>
                </div>
                <div class="hidden sm:block w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                    <flux:icon name="academic-cap" class="w-5 h-5 text-blue-500 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="text-center sm:text-left">
            <div class="flex flex-col sm:flex-row items-center sm:justify-between gap-2">
                <div>
                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ __('student.courses.enrolled') }}</flux:text>
                    <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ number_format($totalEnrolledCourses) }}</flux:heading>
                </div>
                <div class="hidden sm:block w-10 h-10 rounded-full bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
                    <flux:icon name="check-circle" class="w-5 h-5 text-green-500 dark:text-green-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Search and Filters -->
    <flux:card class="mb-6">
        <div class="space-y-4">
            <!-- Search (full width on mobile) -->
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('student.classes.search_placeholder') }}"
                icon="magnifying-glass"
            />

            <!-- Filters -->
            <div x-data="{ showFilters: window.innerWidth >= 1024 }" x-init="window.addEventListener('resize', () => showFilters = window.innerWidth >= 1024 || showFilters)">
                <!-- Mobile Filter Toggle -->
                <button
                    @click="showFilters = !showFilters"
                    class="lg:hidden w-full flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-zinc-800 text-sm text-gray-600 dark:text-gray-300"
                >
                    <span class="flex items-center gap-2">
                        <flux:icon name="funnel" class="w-4 h-4" />
                        {{ __('student.classes.filters') }}
                        @if($teacherFilter || $statusFilter || $feeRangeFilter)
                            <flux:badge size="sm" color="blue">{{ collect([$teacherFilter, $statusFilter, $feeRangeFilter])->filter()->count() }}</flux:badge>
                        @endif
                    </span>
                    <flux:icon name="chevron-down" class="w-4 h-4 transition-transform duration-200" x-bind:class="showFilters ? 'rotate-180' : ''" />
                </button>

                <!-- Filter Selects -->
                <div
                    x-show="showFilters"
                    x-collapse
                    class="mt-3 lg:mt-0"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Teacher Filter -->
                        <flux:select wire:model.live="teacherFilter" size="sm">
                            <flux:select.option value="">{{ __('student.courses.all_teachers') }}</flux:select.option>
                            @foreach($teachers as $teacher)
                                <flux:select.option value="{{ $teacher->id }}">{{ $teacher->user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <!-- Status Filter -->
                        <flux:select wire:model.live="statusFilter" size="sm">
                            <flux:select.option value="">{{ __('student.courses.all_courses') }}</flux:select.option>
                            <flux:select.option value="enrolled">{{ __('student.courses.my_enrolled') }}</flux:select.option>
                            <flux:select.option value="not_enrolled">{{ __('student.courses.not_enrolled') }}</flux:select.option>
                        </flux:select>

                        <!-- Fee Range Filter -->
                        <flux:select wire:model.live="feeRangeFilter" size="sm">
                            <flux:select.option value="">{{ __('student.courses.any_price') }}</flux:select.option>
                            <flux:select.option value="free">{{ __('student.courses.free') }}</flux:select.option>
                            <flux:select.option value="1-50">RM 1 - 50</flux:select.option>
                            <flux:select.option value="51-100">RM 51 - 100</flux:select.option>
                            <flux:select.option value="101+">RM 101+</flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </div>
        </div>

        @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                <flux:button wire:click="clearFilters" variant="ghost" size="sm">
                    <div class="flex items-center justify-center">
                        <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                        {{ __('student.courses.clear_filters') }}
                    </div>
                </flux:button>
            </div>
        @endif
    </flux:card>

    <!-- Courses Grid -->
    @if($courses->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
            @foreach($courses as $course)
                <flux:card class="flex flex-col group hover:shadow-md transition-shadow duration-200">
                    <div class="flex-1">
                        <!-- Course Header -->
                        <div class="flex items-start justify-between mb-3">
                            <flux:heading size="md" class="flex-1 pr-2 text-gray-900 dark:text-white">{{ $course->name }}</flux:heading>
                            @if($studentEnrollments->contains($course->id))
                                <flux:badge color="green" size="sm">{{ __('student.courses.enrolled') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('student.courses.available') }}</flux:badge>
                            @endif
                        </div>

                        <!-- Teacher -->
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-6 h-6 rounded-full bg-gray-100 dark:bg-zinc-700 flex items-center justify-center">
                                <flux:icon name="user" class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
                            </div>
                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                {{ $course->teacher?->user?->name ?? __('student.courses.unknown_teacher') }}
                            </flux:text>
                        </div>

                        <!-- Description -->
                        @if($course->description)
                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                                {{ $course->description }}
                            </flux:text>
                        @endif

                        <!-- Course Stats -->
                        <div class="flex items-center gap-4 mb-4">
                            <div class="flex items-center gap-1.5">
                                <flux:icon name="users" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                    {{ __('student.courses.students', ['count' => number_format($course->active_enrollment_count)]) }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Price Badge -->
                        <div class="flex items-center gap-2">
                            <div class="inline-flex items-center px-3 py-1.5 rounded-full bg-green-50 dark:bg-green-900/30">
                                <flux:text size="sm" class="font-semibold text-green-700 dark:text-green-400">
                                    {{ $course->formatted_fee }}
                                    @if($course->feeSettings && $course->feeSettings->billing_interval)
                                        <span class="font-normal text-green-600 dark:text-green-500">
                                            {{ __('student.courses.per_month') }}
                                        </span>
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-4 mt-4 border-t border-gray-100 dark:border-zinc-700">
                        @if($studentEnrollments->contains($course->id))
                            <flux:button
                                href="{{ route('student.subscriptions') }}"
                                variant="ghost"
                                class="w-full"
                                size="sm"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="check-circle" class="w-4 h-4 mr-1.5 text-green-500" />
                                    {{ __('student.courses.view_enrollment') }}
                                </div>
                            </flux:button>
                        @else
                            <flux:button
                                wire:click="enroll({{ $course->id }})"
                                variant="primary"
                                class="w-full"
                                size="sm"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="plus" class="w-4 h-4 mr-1.5" />
                                    {{ __('student.courses.enroll_now') }}
                                </div>
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($courses->hasPages())
            <div class="mt-6">
                {{ $courses->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <flux:card>
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-zinc-700 flex items-center justify-center mx-auto mb-4">
                    <flux:icon name="academic-cap" class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                </div>
                <flux:heading size="lg" class="mb-2 text-gray-900 dark:text-white">
                    @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
                        {{ __('student.courses.no_courses_match') }}
                    @else
                        {{ __('student.courses.no_courses_available') }}
                    @endif
                </flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-4">
                    @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
                        {{ __('student.courses.try_adjusting') }}
                    @else
                        {{ __('student.courses.check_back') }}
                    @endif
                </flux:text>
                @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
                    <flux:button wire:click="clearFilters" variant="primary" size="sm">
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                            {{ __('student.courses.clear_filters') }}
                        </div>
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @endif

    <!-- Flash Message -->
    @if(session('message'))
        <div class="fixed bottom-24 lg:bottom-4 right-4 z-50" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition>
            <div class="bg-blue-600 dark:bg-blue-500 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2">
                <flux:icon name="information-circle" class="w-5 h-5" />
                <span class="text-sm font-medium">{{ session('message') }}</span>
                <button @click="show = false" class="ml-2 hover:bg-blue-700 dark:hover:bg-blue-600 rounded p-1 transition-colors">
                    <flux:icon name="x-mark" class="w-4 h-4" />
                </button>
            </div>
        </div>
    @endif
</div>