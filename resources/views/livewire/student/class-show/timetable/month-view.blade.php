{{-- Month Calendar View --}}
<div class="bg-white dark:bg-gray-800 rounded-lg">
    {{-- Month Header --}}
    <div class="grid grid-cols-7 gap-px bg-gray-200 dark:bg-gray-700 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wide rounded-t-lg overflow-hidden">
        <div class="py-2 bg-gray-50 dark:bg-gray-800">Sun</div>
        <div class="py-2 bg-gray-50 dark:bg-gray-800">Mon</div>
        <div class="py-2 bg-gray-50 dark:bg-gray-800">Tue</div>
        <div class="py-2 bg-gray-50 dark:bg-gray-800">Wed</div>
        <div class="py-2 bg-gray-50 dark:bg-gray-800">Thu</div>
        <div class="py-2 bg-gray-50 dark:bg-gray-800">Fri</div>
        <div class="py-2 bg-gray-50 dark:bg-gray-800">Sat</div>
    </div>
    
    {{-- Calendar Grid --}}
    <div class="grid grid-cols-7 gap-px bg-gray-200 dark:bg-gray-700">
        @foreach($weeks as $week)
            @foreach($week as $day)
                <div class="bg-white dark:bg-gray-800 min-h-[100px] p-2 {{ $day['isToday'] ? 'bg-blue-50 dark:bg-blue-900/20' : '' }} {{ !$day['isCurrentMonth'] ? 'opacity-50' : '' }}">
                    {{-- Day Number --}}
                    <div class="flex items-center justify-between mb-1">
                        <div class="text-sm font-medium {{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : ($day['isCurrentMonth'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-600') }}">
                            {{ $day['dayNumber'] }}
                        </div>
                        
                        @if($day['sessionCount'] > 0)
                            <flux:badge size="sm" variant="outline">
                                {{ $day['sessionCount'] }}
                            </flux:badge>
                        @endif
                    </div>
                    
                    {{-- Session Indicators --}}
                    @if($day['sessionCount'] > 0)
                        <div class="space-y-1">
                            @php
                                // Get actual sessions for this day
                                $daySessions = $sessions->filter(function($session) use ($day) {
                                    return $session->session_date->isSameDay($day['date']);
                                })->take(3); // Show maximum 3 sessions per day
                            @endphp
                            
                            @foreach($daySessions as $session)
                                <div 
                                    class="text-xs p-1 rounded truncate cursor-pointer transition-colors hover:shadow-sm
                                           @switch($session->status)
                                               @case('scheduled')
                                                   bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800
                                                   @break
                                               @case('ongoing')
                                                   bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 animate-pulse
                                                   @break
                                               @case('completed')
                                                   bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800
                                                   @break
                                               @case('cancelled')
                                                   bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800
                                                   @break
                                               @default
                                                   bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800
                                           @endswitch"
                                    title="{{ $session->session_time->format('g:i A') }} - {{ $session->class->title }}"
                                    @if($session->status === 'completed')
                                        wire:click="selectSession({{ $session->id }})"
                                    @endif
                                >
                                    <div class="flex items-center gap-1">
                                        <span>{{ $session->session_time->format('g:i A') }}</span>
                                        @if($session->status === 'ongoing')
                                            <div class="w-1 h-1 bg-green-500 rounded-full animate-pulse"></div>
                                        @endif
                                    </div>
                                    
                                    @if($session->attendances->count() > 0)
                                        @foreach($session->attendances as $attendance)
                                            <div class="text-xs mt-1">
                                                <flux:badge 
                                                    size="xs" 
                                                    class="{{ $attendance->status_badge_class }}"
                                                >
                                                    {{ $attendance->status_label }}
                                                </flux:badge>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                            
                            @if($day['sessionCount'] > 3)
                                <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
                                    +{{ $day['sessionCount'] - 3 }} more
                                </div>
                            @endif
                        </div>
                    @endif
                    
                    {{-- Today Indicator --}}
                    @if($day['isToday'])
                        <div class="absolute bottom-1 right-1">
                            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
</div>

{{-- Mobile Month View --}}
<div class="md:hidden mt-6">
    <div class="space-y-4">
        @foreach($weeks as $weekIndex => $week)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                    <flux:text class="font-medium">Week {{ $weekIndex + 1 }}</flux:text>
                </div>
                
                <div class="grid grid-cols-7 gap-1 p-3">
                    @foreach($week as $day)
                        <div class="text-center">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                {{ substr($day['date']->format('D'), 0, 1) }}
                            </div>
                            <div 
                                class="w-8 h-8 flex items-center justify-center rounded-full text-sm {{ $day['isToday'] ? 'bg-blue-500 text-white' : ($day['isCurrentMonth'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-600') }}
                                       {{ $day['sessionCount'] > 0 ? 'font-bold' : '' }}"
                            >
                                {{ $day['dayNumber'] }}
                            </div>
                            @if($day['sessionCount'] > 0)
                                <div class="w-1 h-1 bg-blue-500 rounded-full mx-auto mt-1"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>