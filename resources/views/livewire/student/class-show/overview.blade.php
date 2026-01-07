<!-- Class Information -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Details -->
    <div class="lg:col-span-2">
        <flux:card>
            <flux:heading size="lg" class="mb-4 text-gray-900 dark:text-white">{{ __('student.timetable.session_details') }}</flux:heading>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.classes.course') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ $class->course->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.teacher') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ $class->teacher->user->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">Class Type</flux:text>
                        <flux:badge color="{{ $class->class_type === 'individual' ? 'blue' : 'green' }}">
                            {{ ucfirst($class->class_type) }}
                        </flux:badge>
                    </div>

                    @if($class->max_capacity)
                        <div>
                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">Class Size</flux:text>
                            <flux:text class="font-medium text-gray-900 dark:text-white">
                                {{ $class->activeStudents()->count() }} / {{ $class->max_capacity }} students
                            </flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.duration') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ $class->formatted_duration }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.date') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ $classStudent->enrolled_at->format('M j, Y') }}</flux:text>
                    </div>
                </div>

                @if($class->description)
                    <div class="pt-4 border-t border-gray-200 dark:border-zinc-700">
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400 block mb-2">{{ __('student.common.description') ?? 'Description' }}</flux:text>
                        <flux:text class="text-gray-700 dark:text-gray-300">{{ $class->description }}</flux:text>
                    </div>
                @endif

                @if($class->location || $class->meeting_url)
                    <div class="pt-4 border-t border-gray-200 dark:border-zinc-700">
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400 block mb-3">Class Location</flux:text>
                        <div class="space-y-2">
                            @if($class->location)
                                <div class="flex items-center gap-2">
                                    <flux:icon name="map-pin" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                    <flux:text class="text-gray-900 dark:text-white">{{ $class->location }}</flux:text>
                                </div>
                            @endif

                            @if($class->meeting_url)
                                <div class="flex items-center gap-2">
                                    <flux:icon name="video-camera" class="w-4 h-4 text-blue-500 dark:text-blue-400" />
                                    <a href="{{ $class->meeting_url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline">
                                        Join Online Meeting
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- Statistics -->
    <div class="space-y-6">
        <!-- Session Statistics -->
        <flux:card>
            <flux:heading size="lg" class="mb-4 text-gray-900 dark:text-white">{{ __('student.stats.total_sessions') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 dark:bg-blue-800/50 rounded-full">
                            <flux:icon name="calendar-days" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ __('student.stats.total_sessions') }}</flux:text>
                    </div>
                    <flux:text class="font-bold text-blue-700 dark:text-blue-400">{{ $statistics['total_sessions'] }}</flux:text>
                </div>

                <div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/30 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 dark:bg-green-800/50 rounded-full">
                            <flux:icon name="check-circle" class="w-4 h-4 text-green-600 dark:text-green-400" />
                        </div>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ __('student.stats.completed') }}</flux:text>
                    </div>
                    <flux:text class="font-bold text-green-700 dark:text-green-400">{{ $statistics['completed_sessions'] }}</flux:text>
                </div>

                <div class="flex items-center justify-between p-3 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-purple-100 dark:bg-purple-800/50 rounded-full">
                            <flux:icon name="clock" class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                        </div>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ __('student.stats.upcoming') }}</flux:text>
                    </div>
                    <flux:text class="font-bold text-purple-700 dark:text-purple-400">{{ $statistics['upcoming_sessions'] }}</flux:text>
                </div>
            </div>
        </flux:card>

        <!-- Quick Actions -->
        <flux:card>
            <flux:heading size="lg" class="mb-4 text-gray-900 dark:text-white">{{ __('student.quick_actions.my_classes') }}</flux:heading>

            <div class="space-y-3">
                <flux:button
                    wire:click="setActiveTab('timetable')"
                    variant="outline"
                    class="w-full justify-start"
                >
                    <div class="flex items-center">
                        <flux:icon name="calendar" class="w-4 h-4 mr-2" />
                        {{ __('student.classes.timetable') }}
                    </div>
                </flux:button>

                <flux:button
                    wire:click="setActiveTab('sessions')"
                    variant="outline"
                    class="w-full justify-start"
                >
                    <div class="flex items-center">
                        <flux:icon name="clipboard-document-list" class="w-4 h-4 mr-2" />
                        {{ __('student.classes.sessions_tab') }}
                    </div>
                </flux:button>

                @if($class->meeting_url)
                    <a href="{{ $class->meeting_url }}" target="_blank" class="block">
                        <flux:button variant="primary" class="w-full justify-start">
                            <div class="flex items-center">
                                <flux:icon name="video-camera" class="w-4 h-4 mr-2" />
                                Join Online Class
                            </div>
                        </flux:button>
                    </a>
                @endif
            </div>
        </flux:card>
    </div>
</div>

<!-- Recent Sessions -->
@if($sessions->take(5)->count() > 0)
    <flux:card class="mt-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg" class="text-gray-900 dark:text-white">{{ __('student.classes.sessions_tab') }}</flux:heading>
            <flux:button
                wire:click="setActiveTab('sessions')"
                variant="ghost"
                size="sm"
            >
                {{ __('student.common.view') }}
            </flux:button>
        </div>

        <div class="space-y-3">
            @foreach($sessions->sortByDesc('session_date')->take(5) as $session)
                <div
                    class="flex items-center justify-between p-3 border border-gray-200 dark:border-zinc-700 rounded-lg
                        {{ in_array($session->status, ['completed', 'no_show']) ? 'hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer' : '' }}"
                    @if(in_array($session->status, ['completed', 'no_show']))
                        wire:click="selectSession({{ $session->id }})"
                    @endif
                >
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col">
                                <flux:text class="font-medium text-gray-900 dark:text-white">
                                    {{ $session->session_date->format('M j, Y') }}
                                </flux:text>
                                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                    {{ $session->session_time->format('g:i A') }} - {{ $session->formatted_duration }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <flux:badge class="{{ $session->student_status_badge_class }}" size="sm">
                            {{ $session->student_status_label }}
                        </flux:badge>

                        @if(in_array($session->status, ['completed', 'no_show']))
                            <flux:icon name="chevron-right" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>
@endif
