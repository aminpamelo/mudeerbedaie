{{-- Day View --}}
<div class="space-y-4">
    {{-- Desktop Day View --}}
    <div class="hidden md:block">
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden">
            @forelse($timeSlots as $timeSlot)
                <div class="border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                    <div class="flex">
                        {{-- Time Column --}}
                        <div class="w-20 flex-shrink-0 bg-gray-50 dark:bg-gray-900 p-4 border-r border-gray-200 dark:border-gray-700">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $timeSlot['displayTime'] }}
                            </div>
                        </div>
                        
                        {{-- Content Column --}}
                        <div class="flex-1 min-h-[80px] p-4">
                            @if($timeSlot['sessions']->count() > 0)
                                @foreach($timeSlot['sessions'] as $session)
                                    @php
                                    $statusClasses = match($session->status) {
                                        'scheduled' => 'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30',
                                        'ongoing' => 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30 ring-2 ring-green-500 dark:ring-green-400 animate-pulse',
                                        'completed' => 'bg-gray-50 dark:bg-gray-900/20 border border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-900/30 cursor-pointer',
                                        'cancelled' => 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-900/30',
                                        default => 'bg-gray-50 dark:bg-gray-900/20 border border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-900/30'
                                    };
                                    @endphp
                                    <div class="group rounded-lg p-4 mb-2 last:mb-0 transition-all duration-200 hover:shadow-md {{ $statusClasses }}
                                               {{ $session->status === 'completed' ? 'cursor-pointer' : '' }}"
                                        @if($session->status === 'completed')
                                            wire:click="selectSession({{ $session->id }})"
                                        @endif
                                    >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            {{-- Session Title and Time --}}
                                            <div class="flex items-center gap-3 mb-2">
                                                <h3 class="font-semibold text-lg text-gray-900 dark:text-gray-100 truncate">
                                                    {{ $session->class->title }}
                                                </h3>
                                                
                                                @if($session->status === 'ongoing')
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex items-center gap-1">
                                                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                            <span class="text-sm text-green-600 dark:text-green-400 font-medium">Live</span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            
                                            {{-- Session Details --}}
                                            <div class="space-y-1">
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    <flux:icon name="book-open" class="w-4 h-4 inline mr-1" />
                                                    {{ $session->class->course->name }}
                                                </div>
                                                
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    <flux:icon name="user" class="w-4 h-4 inline mr-1" />
                                                    {{ $session->class->teacher->user->name }}
                                                </div>
                                                
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    <flux:icon name="clock" class="w-4 h-4 inline mr-1" />
                                                    {{ $session->session_time->format('g:i A') }} - {{ $session->session_time->addMinutes($session->duration_minutes)->format('g:i A') }}
                                                    ({{ $session->formatted_duration }})
                                                </div>
                                                
                                                {{-- Student's Attendance Status --}}
                                                @php
                                                    $myAttendance = $session->attendances->first(function($attendance) {
                                                        return $attendance->student_id === auth()->user()->student->id;
                                                    });
                                                @endphp
                                                @if($myAttendance)
                                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                                        <flux:icon name="check-circle" class="w-4 h-4 inline mr-1" />
                                                        <flux:badge 
                                                            size="xs" 
                                                            class="{{ $myAttendance->status_badge_class }}"
                                                        >
                                                            {{ $myAttendance->status_label }}
                                                        </flux:badge>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        {{-- Status and Timer --}}
                                        <div class="flex flex-col items-end gap-2">
                                            <flux:badge class="{{ $session->status_badge_class }}">
                                                {{ ucfirst($session->status) }}
                                            </flux:badge>
                                            
                                            {{-- Timer Display for Ongoing Sessions --}}
                                            @if($session->status === 'ongoing')
                                                <div class="text-center" x-data="{ 
                                                    elapsedTime: 0,
                                                    timer: null,
                                                    formatTime(seconds) {
                                                        const hours = Math.floor(seconds / 3600);
                                                        const minutes = Math.floor((seconds % 3600) / 60);
                                                        const secs = seconds % 60;
                                                        if (hours > 0) {
                                                            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                                        } else {
                                                            return `${minutes}:${secs.toString().padStart(2, '0')}`;
                                                        }
                                                    }
                                                }" x-init="
                                                    const startTime = new Date('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}').getTime();
                                                    elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                    timer = setInterval(() => {
                                                        elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                    }, 1000);
                                                " x-destroy="timer && clearInterval(timer)">
                                                    <div class="bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded px-2 py-1">
                                                        <div class="text-sm font-mono font-semibold text-green-700 dark:text-green-300" x-text="formatTime(elapsedTime)">
                                                        </div>
                                                        <div class="text-xs text-green-600 dark:text-green-400">Elapsed</div>
                                                    </div>
                                                </div>
                                            @endif
                                            
                                            {{-- View Details Button --}}
                                            @if($session->status === 'completed')
                                                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <flux:button wire:click.stop="selectSession({{ $session->id }})" size="xs" variant="ghost">
                                                        View Details
                                                    </flux:button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            @else
                                <div class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                                    No sessions at this time
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-12 text-gray-400 dark:text-gray-500">
                    <flux:icon name="calendar" class="w-12 h-12 mx-auto mb-4 opacity-50" />
                    <div class="text-lg">No time slots available</div>
                </div>
            @endforelse
        </div>
    </div>
    
    {{-- Mobile Day View --}}
    <div class="md:hidden">
        @php
            $activeSessions = collect($timeSlots)->filter(function($slot) {
                return $slot['sessions']->count() > 0;
            });
        @endphp
        @forelse($activeSessions as $timeSlot)
            @foreach($timeSlot['sessions'] as $session)
                <flux:card class="mb-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            {{-- Session Time --}}
                            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                                {{ $session->session_time->format('g:i A') }}
                            </div>
                            
                            {{-- Session Title --}}
                            <div class="font-medium text-gray-800 dark:text-gray-200 mb-1">
                                {{ $session->class->title }}
                            </div>
                            
                            {{-- Session Course --}}
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                {{ $session->class->course->name }}
                            </div>
                            
                            {{-- Teacher --}}
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                {{ $session->class->teacher->user->name }}
                            </div>
                            
                            {{-- Session Meta --}}
                            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-500 mb-2">
                                <span>{{ $session->formatted_duration }}</span>
                            </div>

                            {{-- Student's Attendance Status --}}
                            @php
                                $myAttendance = $session->attendances->first(function($attendance) {
                                    return $attendance->student_id === auth()->user()->student->id;
                                });
                            @endphp
                            @if($myAttendance)
                                <div class="mb-2">
                                    <flux:badge 
                                        size="sm" 
                                        class="{{ $myAttendance->status_badge_class }}"
                                    >
                                        {{ $myAttendance->status_label }}
                                    </flux:badge>
                                </div>
                            @endif
                        </div>
                        
                        <div class="ml-4 flex flex-col items-end gap-2">
                            <flux:badge class="{{ $session->status_badge_class }}" size="sm">
                                {{ ucfirst($session->status) }}
                            </flux:badge>
                            
                            @if($session->status === 'ongoing')
                                <div class="flex items-center gap-1">
                                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                    <span class="text-xs text-green-600 dark:text-green-400">Live</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Mobile Actions --}}
                    @if($session->status === 'completed')
                        <div class="flex items-center justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <flux:button wire:click="selectSession({{ $session->id }})" size="sm" variant="ghost">
                                View Details
                            </flux:button>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        @empty
            <flux:card class="text-center py-12">
                <flux:icon name="calendar" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                <flux:heading size="lg" class="text-gray-500 dark:text-gray-400">No Sessions Today</flux:heading>
                <flux:text class="text-gray-400 dark:text-gray-500 mt-2">
                    You don't have any sessions scheduled for this day.
                </flux:text>
            </flux:card>
        @endforelse
    </div>
</div>