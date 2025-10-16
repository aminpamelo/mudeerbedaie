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
            <flux:heading size="xl">Available Courses</flux:heading>
            <flux:text class="mt-2">Discover and enroll in courses that interest you</flux:text>
        </div>
        <flux:button href="{{ route('student.subscriptions') }}" variant="outline" class="self-start sm:self-auto">
            My Enrollments
        </flux:button>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Available Courses</flux:text>
                    <flux:heading size="lg" class="text-blue-600">{{ number_format($totalActiveCourses) }}</flux:heading>
                </div>
                <flux:icon name="academic-cap" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">My Enrolled Courses</flux:text>
                    <flux:heading size="lg" class="text-green-600">{{ number_format($totalEnrolledCourses) }}</flux:heading>
                </div>
                <flux:icon name="check-circle" class="w-8 h-8 text-green-500" />
            </div>
        </flux:card>
    </div>

    <!-- Search and Filters -->
    <flux:card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Search -->
            <div class="lg:col-span-2">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search courses or teachers..."
                    icon="magnifying-glass"
                />
            </div>

            <!-- Teacher Filter -->
            <flux:select wire:model.live="teacherFilter">
                <flux:select.option value="">All Teachers</flux:select.option>
                @foreach($teachers as $teacher)
                    <flux:select.option value="{{ $teacher->id }}">{{ $teacher->user->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <!-- Status Filter -->
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">All Courses</flux:select.option>
                <flux:select.option value="enrolled">My Enrolled</flux:select.option>
                <flux:select.option value="not_enrolled">Not Enrolled</flux:select.option>
            </flux:select>

            <!-- Fee Range Filter -->
            <flux:select wire:model.live="feeRangeFilter">
                <flux:select.option value="">Any Price</flux:select.option>
                <flux:select.option value="free">Free</flux:select.option>
                <flux:select.option value="1-50">RM 1 - 50</flux:select.option>
                <flux:select.option value="51-100">RM 51 - 100</flux:select.option>
                <flux:select.option value="101+">RM 101+</flux:select.option>
            </flux:select>
        </div>

        @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <flux:button wire:click="clearFilters" variant="outline" size="sm">
                    Clear All Filters
                </flux:button>
            </div>
        @endif
    </flux:card>

    <!-- Courses Grid -->
    @if($courses->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($courses as $course)
                <flux:card class="flex flex-col">
                    <div class="flex-1">
                        <!-- Course Header -->
                        <div class="flex items-start justify-between mb-3">
                            <flux:heading size="md" class="flex-1 pr-2">{{ $course->name }}</flux:heading>
                            @if($studentEnrollments->contains($course->id))
                                <flux:badge variant="success" size="sm">Enrolled</flux:badge>
                            @else
                                <flux:badge variant="gray" size="sm">Available</flux:badge>
                            @endif
                        </div>

                        <!-- Teacher -->
                        <div class="flex items-center gap-2 mb-3">
                            <flux:icon name="user-circle" class="w-4 h-4 text-gray-500" />
                            <flux:text size="sm" class="text-gray-600">
                                {{ $course->teacher->user->name }}
                            </flux:text>
                        </div>

                        <!-- Description -->
                        @if($course->description)
                            <flux:text size="sm" class="text-gray-600 mb-4 line-clamp-3">
                                {{ $course->description }}
                            </flux:text>
                        @endif

                        <!-- Course Stats -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <flux:text size="sm" class="text-gray-500">Students</flux:text>
                                <flux:text size="sm" class="font-semibold">
                                    {{ number_format($course->active_enrollment_count) }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text size="sm" class="text-gray-500">Fee</flux:text>
                                <flux:text size="sm" class="font-semibold text-green-600">
                                    {{ $course->formatted_fee }}
                                    @if($course->feeSettings && $course->feeSettings->billing_interval)
                                        <span class="text-gray-500">
                                            /{{ $course->feeSettings->billing_interval === 'month' ? 'month' : $course->feeSettings->billing_interval }}
                                        </span>
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-4 border-t border-gray-100">
                        @if($studentEnrollments->contains($course->id))
                            <flux:button 
                                href="{{ route('student.subscriptions') }}" 
                                variant="outline" 
                                class="w-full"
                                size="sm"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                                    View Enrollment
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
                                    <flux:icon name="plus" class="w-4 h-4 mr-2" />
                                    Enroll Now
                                </div>
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($courses->hasPages())
            <div class="mt-8">
                {{ $courses->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <flux:card>
            <div class="text-center py-12">
                <flux:icon name="academic-cap" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <flux:heading size="lg" class="mb-2">
                    @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
                        No courses match your filters
                    @else
                        No courses available
                    @endif
                </flux:heading>
                <flux:text class="text-gray-600 mb-4">
                    @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
                        Try adjusting your search criteria or filters to find more courses.
                    @else
                        Check back later for new course offerings.
                    @endif
                </flux:text>
                @if($search || $teacherFilter || $statusFilter || $feeRangeFilter)
                    <flux:button wire:click="clearFilters" variant="primary">
                        Clear Filters
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @endif

    <!-- Flash Message -->
    @if(session('message'))
        <div class="fixed bottom-4 right-4 z-50">
            <div class="bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg">
                {{ session('message') }}
            </div>
        </div>
    @endif
</div>