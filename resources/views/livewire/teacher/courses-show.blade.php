<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Course;
use App\Models\ClassModel;
use Illuminate\Support\Str;

new #[Layout('components.layouts.teacher')] class extends Component {
    public Course $course;

    public function mount(Course $course)
    {
        $this->course = $course;
        
        // Verify the teacher has access to this course (has classes in this course)
        $teacher = auth()->user()->teacher;
        if (!$teacher) {
            abort(403, 'Teacher access required');
        }

        $hasAccess = $course->classes()
            ->where('teacher_id', $teacher->id)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'You do not have access to this course');
        }
    }

    public function with()
    {
        $teacher = auth()->user()->teacher;
        
        // Get classes for this course taught by this teacher
        $classes = ClassModel::where('course_id', $this->course->id)
            ->where('teacher_id', $teacher->id)
            ->withCount(['activeStudents', 'sessions', 'classStudents'])
            ->with(['timetable', 'sessions' => function($query) {
                $query->orderBy('session_date', 'desc')->limit(5);
            }])
            ->latest()
            ->get();

        // Get all students from all classes for this course
        $allStudents = collect();
        foreach ($classes as $class) {
            $classStudents = $class->classStudents()
                ->with(['student.user'])
                ->where('status', 'active')
                ->get()
                ->map(function ($classStudent) use ($class) {
                    return [
                        'student' => $classStudent->student,
                        'class' => $class,
                        'enrolled_at' => $classStudent->enrolled_at,
                        'status' => $classStudent->status,
                    ];
                });
            
            $allStudents = $allStudents->concat($classStudents);
        }

        // Remove duplicates based on student ID
        $uniqueStudents = $allStudents->unique(function ($item) {
            return $item['student']->id;
        });

        return [
            'classes' => $classes,
            'students' => $uniqueStudents,
            'totalClasses' => $classes->count(),
            'totalStudents' => $uniqueStudents->count(),
            'totalSessions' => $classes->sum('sessions_count'),
            'activeClasses' => $classes->where('status', 'active')->count(),
        ];
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <flux:button variant="ghost" size="sm" wire:navigate href="{{ route('teacher.courses.index') }}">
                <div class="flex items-center justify-center">
                    <flux:icon icon="chevron-left" class="w-4 h-4 mr-1" />
                    Back to Courses
                </div>
            </flux:button>
            <div class="border-l border-gray-300  h-6"></div>
            <div>
                <flux:heading size="xl">{{ $course->name }}</flux:heading>
                <flux:text class="mt-1">{{ $course->description ?: 'Course details and class management' }}</flux:text>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            @if($course->status === 'active')
                <flux:badge color="green">Active</flux:badge>
            @elseif($course->status === 'draft')
                <flux:badge color="gray">Draft</flux:badge>
            @else
                <flux:badge color="red">Inactive</flux:badge>
            @endif
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-4 mb-8">
        <flux:card class="text-center p-4">
            <div class="text-2xl font-bold text-blue-600  mb-2">
                {{ $totalClasses }}
            </div>
            <flux:text size="sm" class="text-gray-600">
                Total Classes
            </flux:text>
        </flux:card>

        <flux:card class="text-center p-4">
            <div class="text-2xl font-bold text-emerald-600  mb-2">
                {{ $activeClasses }}
            </div>
            <flux:text size="sm" class="text-gray-600">
                Active Classes
            </flux:text>
        </flux:card>

        <flux:card class="text-center p-4">
            <div class="text-2xl font-bold text-purple-600  mb-2">
                {{ $totalStudents }}
            </div>
            <flux:text size="sm" class="text-gray-600">
                Total Students
            </flux:text>
        </flux:card>

        <flux:card class="text-center p-4">
            <div class="text-2xl font-bold text-orange-600  mb-2">
                {{ $totalSessions }}
            </div>
            <flux:text size="sm" class="text-gray-600">
                Total Sessions
            </flux:text>
        </flux:card>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        <!-- Classes Section -->
        <div class="lg:col-span-2">
            <flux:card class="p-6">
                <div class="mb-6">
                    <flux:heading size="lg">Your Classes</flux:heading>
                </div>

                @if($classes->count() > 0)
                    <div class="space-y-4">
                        @foreach($classes as $class)
                            <div class="border border-gray-200  rounded-lg p-4 hover:bg-gray-50 :bg-gray-800 transition-colors">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <flux:heading size="sm">{{ $class->title }}</flux:heading>
                                            @if($class->status === 'active')
                                                <flux:badge color="green" size="sm">Active</flux:badge>
                                            @elseif($class->status === 'draft')
                                                <flux:badge color="gray" size="sm">Draft</flux:badge>
                                            @elseif($class->status === 'completed')
                                                <flux:badge color="emerald" size="sm">Completed</flux:badge>
                                            @else
                                                <flux:badge color="red" size="sm">{{ Str::title($class->status) }}</flux:badge>
                                            @endif
                                        </div>
                                        @if($class->description)
                                            <flux:text size="sm" class="text-gray-600  mb-2">
                                                {{ $class->description }}
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4 text-sm text-gray-600  mb-4">
                                    <div class="flex items-center">
                                        <flux:icon icon="users" class="w-4 h-4 mr-1" />
                                        {{ $class->active_students_count }} students
                                    </div>
                                    <div class="flex items-center">
                                        <flux:icon icon="calendar" class="w-4 h-4 mr-1" />
                                        {{ $class->sessions_count }} sessions
                                    </div>
                                    <div class="flex items-center">
                                        <flux:icon icon="clock" class="w-4 h-4 mr-1" />
                                        {{ $class->formatted_duration }}
                                    </div>
                                </div>

                                <div class="flex space-x-2">
                                    <flux:button size="sm" variant="ghost" wire:navigate href="{{ route('teacher.classes.show', $class) }}">
                                        <div class="flex items-center justify-center">
                                            <flux:icon icon="eye" class="w-4 h-4 mr-1" />
                                            View Details
                                        </div>
                                    </flux:button>
                                    @if($class->hasTimetable())
                                        <flux:button size="sm" variant="ghost" wire:navigate href="{{ route('teacher.timetable') }}?class_id={{ $class->id }}">
                                            <div class="flex items-center justify-center">
                                                <flux:icon icon="calendar" class="w-4 h-4 mr-1" />
                                                Schedule
                                            </div>
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon icon="academic-cap" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                        <flux:heading size="md" class="mb-2">No Classes Yet</flux:heading>
                        <flux:text class="text-gray-600">
                            You don't have any classes for this course yet.
                        </flux:text>
                    </div>
                @endif
            </flux:card>
        </div>

        <!-- Students Section -->
        <div>
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-6">Course Students</flux:heading>

                @if($students->count() > 0)
                    <div class="space-y-3">
                        @foreach($students->take(10) as $studentData)
                            <div class="flex items-center space-x-3 p-3 border border-gray-200  rounded-lg">
                                <div class="flex-1 min-w-0">
                                    <flux:text size="sm" class="font-medium">
                                        {{ $studentData['student']->user->name }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-500">
                                        {{ $studentData['class']->title }}
                                    </flux:text>
                                </div>
                                <div class="text-right">
                                    <flux:badge color="green" size="sm">{{ Str::title($studentData['status']) }}</flux:badge>
                                </div>
                            </div>
                        @endforeach

                        @if($students->count() > 10)
                            <div class="text-center pt-4">
                                <flux:text size="sm" class="text-gray-500">
                                    And {{ $students->count() - 10 }} more students...
                                </flux:text>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon icon="users" class="w-12 h-12 text-gray-400 mx-auto mb-3" />
                        <flux:text class="text-gray-600">
                            No students enrolled yet
                        </flux:text>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>