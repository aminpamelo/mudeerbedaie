<?php
use App\Models\ClassModel;
use App\Models\ClassStudent;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $courseFilter = '';

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

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->courseFilter = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $student = auth()->user()->student;
        
        // Get all classes this student is enrolled in
        $query = ClassStudent::where('student_id', $student->id)
            ->with(['class.course', 'class.teacher.user', 'class.sessions', 'class.timetable'])
            ->when($this->search, function($query) {
                $query->whereHas('class', function($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%')
                      ->orWhereHas('course', function($courseQuery) {
                          $courseQuery->where('name', 'like', '%' . $this->search . '%');
                      })
                      ->orWhereHas('teacher.user', function($teacherQuery) {
                          $teacherQuery->where('name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->courseFilter, function($query) {
                $query->whereHas('class', function($q) {
                    $q->where('course_id', $this->courseFilter);
                });
            })
            ->orderBy('enrolled_at', 'desc');

        $classStudents = $query->paginate(12);

        // Get available courses for filter
        $courses = ClassModel::whereHas('classStudents', function($q) use ($student) {
            $q->where('student_id', $student->id);
        })->with('course')->get()->pluck('course')->unique('id')->values();

        // Get status options
        $statusOptions = [
            'active' => 'Active',
            'transferred' => 'Transferred',
            'quit' => 'Quit',
            'completed' => 'Completed'
        ];

        return [
            'classStudents' => $classStudents,
            'courses' => $courses,
            'statusOptions' => $statusOptions,
            'totalActiveClasses' => ClassStudent::where('student_id', $student->id)->where('status', 'active')->count(),
            'totalClasses' => ClassStudent::where('student_id', $student->id)->count(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">My Classes</flux:heading>
            <flux:text class="mt-2">View and manage your enrolled classes</flux:text>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Active Classes</flux:text>
                    <flux:heading size="lg" class="text-green-600">{{ number_format($totalActiveClasses) }}</flux:heading>
                </div>
                <flux:icon name="academic-cap" class="w-8 h-8 text-green-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600">Total Classes</flux:text>
                    <flux:heading size="lg" class="text-blue-600">{{ number_format($totalClasses) }}</flux:heading>
                </div>
                <flux:icon name="calendar-days" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>
    </div>

    <!-- Search and Filters -->
    <flux:card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search classes, courses, or teachers..."
                    icon="magnifying-glass"
                />
            </div>

            <!-- Course Filter -->
            <flux:select wire:model.live="courseFilter">
                <flux:select.option value="">All Courses</flux:select.option>
                @foreach($courses as $course)
                    <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <!-- Status Filter -->
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">All Status</flux:select.option>
                @foreach($statusOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <!-- Clear Filters -->
            <flux:button 
                wire:click="clearFilters" 
                variant="outline"
            >
                Clear Filters
            </flux:button>
        </div>
    </flux:card>

    <!-- Classes Grid -->
    @if($classStudents->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($classStudents as $classStudent)
                <flux:card class="flex flex-col">
                    <div class="flex-1">
                        <!-- Class Header -->
                        <div class="flex items-start justify-between mb-3">
                            <flux:heading size="md" class="flex-1 pr-2">{{ $classStudent->class->title }}</flux:heading>
                            @if($classStudent->status === 'active')
                                <flux:badge variant="success" size="sm">Active</flux:badge>
                            @elseif($classStudent->status === 'completed')
                                <flux:badge variant="gray" size="sm">Completed</flux:badge>
                            @elseif($classStudent->status === 'transferred')
                                <flux:badge variant="warning" size="sm">Transferred</flux:badge>
                            @else
                                <flux:badge variant="danger" size="sm">{{ ucfirst($classStudent->status) }}</flux:badge>
                            @endif
                        </div>

                        <!-- Course and Teacher -->
                        <div class="mb-3">
                            <div class="flex items-center gap-2 mb-1">
                                <flux:icon name="academic-cap" class="w-4 h-4 text-gray-500" />
                                <flux:text size="sm" class="text-gray-600">
                                    {{ $classStudent->class->course->name }}
                                </flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon name="user-circle" class="w-4 h-4 text-gray-500" />
                                <flux:text size="sm" class="text-gray-600">
                                    {{ $classStudent->class->teacher->user->name }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Description -->
                        @if($classStudent->class->description)
                            <flux:text size="sm" class="text-gray-600 mb-4 line-clamp-2">
                                {{ $classStudent->class->description }}
                            </flux:text>
                        @endif

                        <!-- Class Stats -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <flux:text size="sm" class="text-gray-500">Total Sessions</flux:text>
                                <flux:text size="sm" class="font-semibold">
                                    {{ number_format($classStudent->class->sessions->count()) }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text size="sm" class="text-gray-500">Completed</flux:text>
                                <flux:text size="sm" class="font-semibold text-green-600">
                                    {{ number_format($classStudent->class->sessions->where('status', 'completed')->count()) }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Enrollment Date -->
                        <div class="text-xs text-gray-500">
                            Enrolled: {{ $classStudent->enrolled_at->format('M j, Y') }}
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-4 border-t border-gray-100 mt-4">
                        <flux:button 
                            href="{{ route('student.classes.show', $classStudent->class) }}" 
                            variant="primary" 
                            class="w-full"
                            size="sm"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="eye" class="w-4 h-4 mr-2" />
                                View Details
                            </div>
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($classStudents->hasPages())
            <div class="mt-8">
                {{ $classStudents->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <flux:card>
            <div class="text-center py-12">
                <flux:icon name="academic-cap" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <flux:heading size="lg" class="mb-2">
                    @if($search || $statusFilter || $courseFilter)
                        No classes match your filters
                    @else
                        No classes found
                    @endif
                </flux:heading>
                <flux:text class="text-gray-600 mb-4">
                    @if($search || $statusFilter || $courseFilter)
                        Try adjusting your search criteria or filters to find more classes.
                    @else
                        You are not currently enrolled in any classes.
                    @endif
                </flux:text>
                @if($search || $statusFilter || $courseFilter)
                    <flux:button wire:click="clearFilters" variant="primary">
                        Clear Filters
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @endif
</div>