<!-- Sessions Statistics -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <flux:card class="p-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Total</flux:text>
                <flux:heading size="lg">{{ $statistics['total_sessions'] }}</flux:heading>
                <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
            </div>
            <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <flux:icon name="calendar-days" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
            </div>
        </div>
    </flux:card>
    
    <flux:card class="p-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Completed</flux:text>
                <flux:heading size="lg">{{ $statistics['completed_sessions'] }}</flux:heading>
                <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
            </div>
            <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <flux:icon name="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
            </div>
        </div>
    </flux:card>
    
    <flux:card class="p-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Upcoming</flux:text>
                <flux:heading size="lg">{{ $statistics['upcoming_sessions'] }}</flux:heading>
                <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
            </div>
            <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                <flux:icon name="clock" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
            </div>
        </div>
    </flux:card>
    
    <flux:card class="p-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Attended</flux:text>
                <flux:heading size="lg">{{ $statistics['attended_sessions'] }}</flux:heading>
                <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
            </div>
            <div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                <flux:icon name="check-circle" class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            </div>
        </div>
    </flux:card>
</div>

<!-- Sessions List -->
@if($sessions->count() > 0)
    <flux:card>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">All Sessions</flux:heading>
            <flux:text size="sm" class="text-gray-500">
                {{ $sessions->count() }} session{{ $sessions->count() !== 1 ? 's' : '' }} total
            </flux:text>
        </div>
        
        <div class="space-y-4">
            @foreach($sessions->groupBy(function($session) { return $session->session_date->format('Y-m'); }) as $monthYear => $monthSessions)
                @php
                    $monthLabel = \Carbon\Carbon::parse($monthYear . '-01')->format('F Y');
                @endphp
                
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <flux:heading size="md" class="text-gray-700 dark:text-gray-300">{{ $monthLabel }}</flux:heading>
                        <flux:badge variant="outline" size="sm">
                            {{ $monthSessions->count() }} session{{ $monthSessions->count() !== 1 ? 's' : '' }}
                        </flux:badge>
                    </div>
                    
                    <div class="space-y-2">
                        @foreach($monthSessions->sortByDesc('session_date') as $session)
                            <div 
                                class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors
                                       {{ $session->status === 'completed' ? 'cursor-pointer' : '' }}"
                                @if($session->status === 'completed')
                                    wire:click="selectSession({{ $session->id }})"
                                @endif
                            >
                                <div class="flex-1">
                                    <div class="flex items-start gap-4">
                                        <!-- Date and Time -->
                                        <div class="flex-shrink-0">
                                            <div class="text-center">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $session->session_date->format('M j') }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $session->session_date->format('D') }}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Session Details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-3 mb-2">
                                                <flux:text class="font-medium">
                                                    {{ $session->session_time->format('g:i A') }}
                                                </flux:text>
                                                <flux:text size="sm" class="text-gray-600">
                                                    {{ $session->formatted_duration }}
                                                </flux:text>
                                            </div>
                                            
                                            @if($session->teacher_notes && $session->status === 'completed')
                                                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                                    {{ Str::limit($session->teacher_notes, 120) }}
                                                </flux:text>
                                            @endif
                                            
                                            <!-- Attendance Status -->
                                            @if($session->attendances->count() > 0)
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach($session->attendances as $attendance)
                                                        <flux:badge 
                                                            size="sm" 
                                                            class="{{ $attendance->status_badge_class }}"
                                                        >
                                                            {{ $attendance->status_label }}
                                                        </flux:badge>
                                                        
                                                        @if($attendance->teacher_remarks)
                                                            <flux:icon name="chat-bubble-left-ellipsis" class="w-4 h-4 text-gray-500" title="Has teacher notes" />
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Status and Actions -->
                                <div class="flex items-center gap-3">
                                    @if($session->status === 'ongoing')
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                            <flux:text size="sm" class="text-green-600 dark:text-green-400">Live</flux:text>
                                        </div>
                                    @endif
                                    
                                    <flux:badge 
                                        class="{{ $session->status_badge_class }}"
                                        size="sm"
                                    >
                                        {{ ucfirst($session->status) }}
                                    </flux:badge>
                                    
                                    @if($session->status === 'completed')
                                        <flux:button variant="ghost" size="sm">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                                View
                                            </div>
                                        </flux:button>
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
            <flux:icon name="calendar-days" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-2 text-gray-500">No Sessions Yet</flux:heading>
            <flux:text class="text-gray-400 mb-4">
                There are no sessions scheduled for this class yet.
            </flux:text>
            <flux:button 
                wire:click="setActiveTab('timetable')" 
                variant="primary"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="calendar" class="w-4 h-4 mr-2" />
                    View Timetable
                </div>
            </flux:button>
        </div>
    </flux:card>
@endif

<!-- Mobile Sessions List -->
<div class="md:hidden space-y-4 mt-6">
    @foreach($sessions->groupBy(function($session) { return $session->session_date->format('Y-m'); }) as $monthYear => $monthSessions)
        @php
            $monthLabel = \Carbon\Carbon::parse($monthYear . '-01')->format('F Y');
        @endphp
        
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ $monthLabel }}</flux:heading>
                    <flux:badge variant="outline" size="sm">
                        {{ $monthSessions->count() }}
                    </flux:badge>
                </div>
            </div>
            
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($monthSessions->sortByDesc('session_date') as $session)
                    <div 
                        class="p-4 {{ $session->status === 'completed' ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : '' }}"
                        @if($session->status === 'completed')
                            wire:click="selectSession({{ $session->id }})"
                        @endif
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $session->session_date->format('M j, Y') }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $session->session_time->format('g:i A') }} - {{ $session->formatted_duration }}
                                </div>
                            </div>
                            
                            <flux:badge 
                                class="{{ $session->status_badge_class }}"
                                size="sm"
                            >
                                {{ ucfirst($session->status) }}
                            </flux:badge>
                        </div>
                        
                        @if($session->attendances->count() > 0)
                            <div class="flex flex-wrap gap-2 mb-3">
                                @foreach($session->attendances as $attendance)
                                    <flux:badge 
                                        size="sm" 
                                        class="{{ $attendance->status_badge_class }}"
                                    >
                                        {{ $attendance->status_label }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        @endif
                        
                        @if($session->teacher_notes && $session->status === 'completed')
                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                {{ Str::limit($session->teacher_notes, 80) }}
                            </flux:text>
                        @endif
                        
                        @if($session->status === 'completed')
                            <div class="text-center mt-3">
                                <flux:text size="xs" class="text-gray-500">Tap to view details</flux:text>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>