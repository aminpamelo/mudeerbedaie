<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Course;
use Illuminate\Support\Str;

new #[Layout('components.layouts.teacher')] class extends Component {
    public function with()
    {
        // Get the teacher model for the current user
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return ['courses' => collect()];
        }

        // Get courses where this teacher has classes
        $courses = Course::whereHas('classes', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->withCount([
                'enrollments', 
                'activeEnrollments',
                'classes as classes_count' => function ($query) use ($teacher) {
                    $query->where('teacher_id', $teacher->id);
                }
            ])
            ->latest()
            ->get();

        return [
            'courses' => $courses
        ];
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Courses</flux:heading>
            <flux:text class="mt-2">View and manage your teaching courses</flux:text>
        </div>
    </div>

    @if($courses->count() > 0)
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 mb-8">
            <flux:card class="text-center p-4 md:p-6">
                <div class="text-xl md:text-2xl font-bold text-blue-600  mb-2">
                    {{ $courses->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600">
                    Total Courses
                </flux:text>
            </flux:card>

            <flux:card class="text-center p-4 md:p-6">
                <div class="text-xl md:text-2xl font-bold text-emerald-600  mb-2">
                    {{ $courses->where('status', 'active')->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600">
                    Active Courses
                </flux:text>
            </flux:card>

            <flux:card class="text-center p-4 md:p-6 col-span-2 md:col-span-1">
                <div class="text-xl md:text-2xl font-bold text-purple-600  mb-2">
                    {{ $courses->sum('enrollments_count') }}
                </div>
                <flux:text size="sm" class="text-gray-600">
                    Total Students
                </flux:text>
            </flux:card>
        </div>
        <!-- Courses Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($courses as $course)
                <flux:card class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <flux:heading size="sm" class="mb-2">{{ $course->name }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600  mb-3 line-clamp-2">
                                {{ $course->description ?? 'No description available' }}
                            </flux:text>
                        </div>
                        @if($course->status === 'active')
                            <flux:badge color="green" size="sm">Active</flux:badge>
                        @elseif($course->status === 'draft')
                            <flux:badge color="gray" size="sm">Draft</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Inactive</flux:badge>
                        @endif
                    </div>

                    <div class="flex items-center justify-between text-sm text-gray-600  mb-4">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <flux:icon icon="academic-cap" class="w-4 h-4 mr-1" />
                                {{ $course->classes_count }} {{ Str::plural('class', $course->classes_count) }}
                            </div>
                            <div class="flex items-center">
                                <flux:icon icon="users" class="w-4 h-4 mr-1" />
                                {{ $course->enrollments_count }} students
                            </div>
                        </div>
                        @if($course->feeSettings && $course->feeSettings->fee_amount)
                            <div class="font-medium">
                                {{ $course->formatted_fee }}
                            </div>
                        @else
                            <flux:badge color="blue" size="sm">Free</flux:badge>
                        @endif
                    </div>

                    <div class="flex space-x-2">
                        <flux:button size="sm" variant="ghost" class="flex-1" wire:navigate href="{{ route('teacher.courses.show', $course) }}">
                            <div class="flex items-center justify-center">
                                <flux:icon icon="eye" class="w-4 h-4 mr-1" />
                                View
                            </div>
                        </flux:button>
                        <flux:button size="sm" variant="ghost" class="flex-1" wire:navigate href="{{ route('teacher.courses.show', $course) }}">
                            <div class="flex items-center justify-center">
                                <flux:icon icon="users" class="w-4 h-4 mr-1" />
                                Students
                            </div>
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <flux:card class="text-center py-12">
            <flux:icon icon="academic-cap" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-4">No Courses Assigned</flux:heading>
            <flux:text class="text-gray-600  mb-6 max-w-md mx-auto">
                You don't have any courses assigned yet. Contact your administrator to get courses assigned to you.
            </flux:text>
        </flux:card>
    @endif
</div>