<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Course;

new #[Layout('components.layouts.app')] class extends Component {
    public function with()
    {
        // Get the teacher model for the current user
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return ['courses' => collect()];
        }

        // Get courses assigned to this teacher
        $courses = Course::where('teacher_id', $teacher->id)
            ->withCount(['enrollments', 'activeEnrollments'])
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
        <!-- Courses Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($courses as $course)
                <flux:card class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <flux:heading size="sm" class="mb-2">{{ $course->name }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
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

                    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <div class="flex items-center">
                            <flux:icon icon="users" class="w-4 h-4 mr-1" />
                            {{ $course->enrollments_count }} students
                        </div>
                        @if($course->price)
                            <div class="font-medium">
                                ${{ number_format($course->price, 2) }}
                            </div>
                        @else
                            <flux:badge color="blue" size="sm">Free</flux:badge>
                        @endif
                    </div>

                    <div class="flex space-x-2">
                        <flux:button size="sm" variant="ghost" class="flex-1">
                            <flux:icon icon="eye" class="w-4 h-4 mr-1" />
                            View
                        </flux:button>
                        <flux:button size="sm" variant="ghost" class="flex-1">
                            <flux:icon icon="users" class="w-4 h-4 mr-1" />
                            Students
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-6 md:grid-cols-3 mt-8">
            <flux:card class="text-center p-6">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                    {{ $courses->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                    Total Courses
                </flux:text>
            </flux:card>

            <flux:card class="text-center p-6">
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">
                    {{ $courses->where('status', 'active')->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                    Active Courses
                </flux:text>
            </flux:card>

            <flux:card class="text-center p-6">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                    {{ $courses->sum('enrollments_count') }}
                </div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                    Total Students
                </flux:text>
            </flux:card>
        </div>
    @else
        <!-- Empty State -->
        <flux:card class="text-center py-12">
            <flux:icon icon="academic-cap" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-4">No Courses Assigned</flux:heading>
            <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                You don't have any courses assigned yet. Contact your administrator to get courses assigned to you.
            </flux:text>
        </flux:card>
    @endif
</div>