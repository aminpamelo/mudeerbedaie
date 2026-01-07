<!-- Sessions Statistics -->
<div class="grid grid-cols-3 gap-3 sm:gap-4 mb-6">
    <flux:card class="text-center">
        <div class="flex flex-col items-center">
            <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center mb-2">
                <flux:icon name="calendar-days" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <flux:heading size="lg" class="text-blue-600 dark:text-blue-400">{{ $statistics['total_sessions'] }}</flux:heading>
            <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ __('student.stats.total_sessions') }}</flux:text>
        </div>
    </flux:card>

    <flux:card class="text-center">
        <div class="flex flex-col items-center">
            <div class="w-10 h-10 rounded-full bg-green-50 dark:bg-green-900/30 flex items-center justify-center mb-2">
                <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
            </div>
            <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ $statistics['completed_sessions'] }}</flux:heading>
            <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ __('student.stats.completed') }}</flux:text>
        </div>
    </flux:card>

    <flux:card class="text-center">
        <div class="flex flex-col items-center">
            <div class="w-10 h-10 rounded-full bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center mb-2">
                <flux:icon name="clock" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
            </div>
            <flux:heading size="lg" class="text-purple-600 dark:text-purple-400">{{ $statistics['upcoming_sessions'] }}</flux:heading>
            <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ __('student.stats.upcoming') }}</flux:text>
        </div>
    </flux:card>
</div>

<!-- Sessions List -->
@if($sessions->count() > 0)
    <flux:card>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg" class="text-gray-900 dark:text-white">{{ __('student.classes.sessions_tab') }}</flux:heading>
            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                {{ trans_choice('student.dashboard.session', $sessions->count(), ['count' => $sessions->count()]) }}
            </flux:text>
        </div>

        <div class="space-y-6">
            @foreach($sessions->groupBy(function($session) { return $session->session_date->format('Y-m'); }) as $monthYear => $monthSessions)
                @php
                    $monthLabel = \Carbon\Carbon::parse($monthYear . '-01')->format('F Y');
                @endphp

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <flux:heading size="md" class="text-gray-700 dark:text-gray-300">{{ $monthLabel }}</flux:heading>
                        <flux:badge color="zinc" size="sm">
                            {{ $monthSessions->count() }} session{{ $monthSessions->count() !== 1 ? 's' : '' }}
                        </flux:badge>
                    </div>

                    <div class="space-y-2">
                        @foreach($monthSessions->sortByDesc('session_date') as $session)
                            <div
                                class="flex flex-col sm:flex-row sm:items-center justify-between p-3 sm:p-4 border border-gray-200 dark:border-zinc-700 rounded-lg transition-colors
                                       {{ in_array($session->status, ['completed', 'no_show']) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800' : '' }}"
                                @if(in_array($session->status, ['completed', 'no_show']))
                                    wire:click="selectSession({{ $session->id }})"
                                @endif
                            >
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start gap-3 sm:gap-4">
                                        <!-- Date Badge -->
                                        <div class="flex-shrink-0 text-center bg-gray-100 dark:bg-zinc-800 rounded-lg px-3 py-2">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $session->session_date->format('M j') }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $session->session_date->format('D') }}
                                            </div>
                                        </div>

                                        <!-- Session Details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                                <flux:text class="font-medium text-gray-900 dark:text-white">
                                                    {{ $session->session_time->format('g:i A') }}
                                                </flux:text>
                                                <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                                    {{ $session->formatted_duration }}
                                                </flux:text>
                                                @if($session->status === 'ongoing')
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                        <flux:text size="sm" class="text-green-600 dark:text-green-400 font-medium">{{ __('student.status.live') }}</flux:text>
                                                    </div>
                                                @endif
                                            </div>

                                            @if($session->teacher_notes && in_array($session->status, ['completed', 'no_show']))
                                                <flux:text size="sm" class="text-gray-600 dark:text-gray-400 line-clamp-1">
                                                    {{ Str::limit($session->teacher_notes, 80) }}
                                                </flux:text>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Status and Actions -->
                                <div class="flex items-center justify-between sm:justify-end gap-3 mt-3 sm:mt-0 pt-3 sm:pt-0 border-t sm:border-t-0 border-gray-100 dark:border-zinc-700">
                                    <flux:badge
                                        class="{{ $session->student_status_badge_class }}"
                                        size="sm"
                                    >
                                        {{ $session->student_status_label }}
                                    </flux:badge>

                                    @if(in_array($session->status, ['completed', 'no_show']))
                                        <flux:icon name="chevron-right" class="w-4 h-4 text-gray-400 dark:text-gray-500 hidden sm:block" />
                                        <flux:text size="xs" class="text-gray-400 dark:text-gray-500 sm:hidden">Tap to view</flux:text>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>
@else
    <!-- Empty State -->
    <flux:card>
        <div class="text-center py-12">
            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-zinc-700 flex items-center justify-center mx-auto mb-4">
                <flux:icon name="calendar-days" class="w-8 h-8 text-gray-400 dark:text-gray-500" />
            </div>
            <flux:heading size="lg" class="mb-2 text-gray-900 dark:text-white">{{ __('student.empty.no_sessions') }}</flux:heading>
            <flux:text class="text-gray-500 dark:text-gray-400 mb-4">
                {{ __('student.empty.no_sessions_desc') }}
            </flux:text>
            <flux:button
                wire:click="setActiveTab('timetable')"
                variant="primary"
                size="sm"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="calendar" class="w-4 h-4 mr-2" />
                    {{ __('student.classes.timetable') }}
                </div>
            </flux:button>
        </div>
    </flux:card>
@endif
