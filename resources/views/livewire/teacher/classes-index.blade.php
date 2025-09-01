<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ClassModel;

new #[Layout('components.layouts.teacher')] class extends Component {
    public function with()
    {
        // Get the teacher model for the current user
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return ['classes' => collect()];
        }

        // Get classes assigned to this teacher
        $classes = ClassModel::where('teacher_id', $teacher->id)
            ->with(['course', 'activeStudents'])
            ->withCount(['sessions', 'activeStudents'])
            ->latest()
            ->get();

        return [
            'classes' => $classes
        ];
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Classes</flux:heading>
            <flux:text class="mt-2">View and manage your individual and group classes</flux:text>
        </div>
    </div>

    @if($classes->count() > 0)
        <!-- Classes Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($classes as $class)
                <flux:card class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <flux:heading size="sm" class="mb-2">{{ $class->title }}</flux:heading>
                            <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mb-2">
                                {{ $class->course->name }}
                            </flux:text>
                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                {{ $class->description ?? 'No description available' }}
                            </flux:text>
                        </div>
                        @if($class->status === 'active')
                            <flux:badge color="green" size="sm">Active</flux:badge>
                        @elseif($class->status === 'draft')
                            <flux:badge color="gray" size="sm">Draft</flux:badge>
                        @elseif($class->status === 'completed')
                            <flux:badge color="blue" size="sm">Completed</flux:badge>
                        @elseif($class->status === 'suspended')
                            <flux:badge color="yellow" size="sm">Suspended</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Cancelled</flux:badge>
                        @endif
                    </div>

                    <div class="space-y-2 mb-4">
                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center">
                                <flux:icon icon="users" class="w-4 h-4 mr-1" />
                                {{ $class->active_students_count }} 
                                @if($class->class_type === 'individual')
                                    student
                                @else
                                    students
                                @endif
                            </div>
                            <flux:badge color="{{ $class->class_type === 'individual' ? 'purple' : 'blue' }}" size="sm">
                                {{ ucfirst($class->class_type) }}
                            </flux:badge>
                        </div>

                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center">
                                <flux:icon icon="clock" class="w-4 h-4 mr-1" />
                                {{ $class->formatted_duration }}
                            </div>
                            <div class="flex items-center">
                                <flux:icon icon="calendar" class="w-4 h-4 mr-1" />
                                {{ $class->sessions_count }} sessions
                            </div>
                        </div>

                        @if($class->location)
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <flux:icon icon="map-pin" class="w-4 h-4 mr-1" />
                                {{ $class->location }}
                            </div>
                        @endif

                        @if($class->date_time)
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <flux:icon icon="calendar-days" class="w-4 h-4 mr-1" />
                                {{ $class->formatted_date_time }}
                            </div>
                        @endif
                    </div>

                    <div class="flex space-x-2">
                        <flux:button 
                            size="sm" 
                            variant="ghost" 
                            class="flex-1"
                            href="{{ route('teacher.classes.show', $class) }}"
                        >
                            <flux:icon icon="eye" class="w-4 h-4 mr-1" />
                            View
                        </flux:button>
                        <flux:button 
                            size="sm" 
                            variant="ghost" 
                            class="flex-1"
                            href="{{ route('teacher.classes.show', $class) }}"
                        >
                            <flux:icon icon="calendar" class="w-4 h-4 mr-1" />
                            Sessions
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-6 md:grid-cols-4 mt-8">
            <flux:card class="text-center p-6">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                    {{ $classes->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                    Total Classes
                </flux:text>
            </flux:card>

            <flux:card class="text-center p-6">
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">
                    {{ $classes->where('status', 'active')->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                    Active Classes
                </flux:text>
            </flux:card>

            <flux:card class="text-center p-6">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                    {{ $classes->where('class_type', 'individual')->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                    Individual
                </flux:text>
            </flux:card>

            <flux:card class="text-center p-6">
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400 mb-2">
                    {{ $classes->where('class_type', 'group')->count() }}
                </div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                    Group Classes
                </flux:text>
            </flux:card>
        </div>
    @else
        <!-- Empty State -->
        <flux:card class="text-center py-12">
            <flux:icon icon="calendar-days" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-4">No Classes Assigned</flux:heading>
            <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                You don't have any classes assigned yet. Contact your administrator to get classes assigned to you.
            </flux:text>
        </flux:card>
    @endif
</div>