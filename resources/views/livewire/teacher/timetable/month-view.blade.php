{{-- Month View --}}
<div class="overflow-x-auto">
    {{-- Calendar Header --}}
    <div class="grid grid-cols-7 gap-px bg-gray-200 dark:bg-gray-700 rounded-t-lg overflow-hidden min-w-full">
        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
            <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    {{ $dayName }}
                </div>
            </div>
        @endforeach
    </div>
    
    {{-- Calendar Body --}}
    <div class="bg-gray-200 dark:bg-gray-700">
        @foreach($weeks as $week)
            <div class="grid grid-cols-7 gap-px">
                @foreach($week as $day)
                    <div class="bg-white dark:bg-gray-800 min-h-[120px] relative
                                {{ $day['isCurrentMonth'] ? '' : 'bg-gray-50 dark:bg-gray-900/50' }}
                                {{ $day['isToday'] ? 'ring-2 ring-blue-500 dark:ring-blue-400 ring-inset' : '' }}">
                        
                        {{-- Date Number --}}
                        <div class="p-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium 
                                           {{ $day['isToday'] ? 'bg-blue-600 text-white dark:bg-blue-500 rounded-full w-6 h-6 flex items-center justify-center' : '' }}
                                           {{ $day['isCurrentMonth'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-600' }}">
                                    {{ $day['dayNumber'] }}
                                </span>
                                
                                {{-- Session Count Indicator --}}
                                @if($day['sessionCount'] > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ $day['sessionCount'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Session Dots --}}
                        @if($day['sessionCount'] > 0)
                            <div class="absolute bottom-2 left-2 right-2">
                                <div class="flex items-center justify-center space-x-1">
                                    @for($i = 0; $i < min($day['sessionCount'], 4); $i++)
                                        <div class="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-400"></div>
                                    @endfor
                                    @if($day['sessionCount'] > 4)
                                        <div class="text-xs text-blue-600 dark:text-blue-400 ml-1">+{{ $day['sessionCount'] - 4 }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

{{-- Mobile Month View --}}
<div class="md:hidden mt-6">
    <div class="space-y-3">
        @foreach($weeks as $weekIndex => $week)
            <flux:card>
                <div class="grid grid-cols-7 gap-2">
                    @foreach($week as $day)
                        <div class="text-center p-2 rounded-lg cursor-pointer transition-colors
                                   {{ $day['isCurrentMonth'] ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : '' }}
                                   {{ $day['isToday'] ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-900 dark:text-blue-100' : '' }}
                                   {{ !$day['isCurrentMonth'] ? 'text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-gray-100' }}"
                        >
                            <div class="text-sm font-medium">
                                {{ $day['dayNumber'] }}
                            </div>
                            
                            @if($day['sessionCount'] > 0)
                                <div class="mt-1">
                                    <div class="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-400 mx-auto"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endforeach
    </div>
    
    {{-- Mobile Sessions Summary --}}
    <flux:card class="mt-6">
        <flux:heading size="sm" class="mb-3">This Month's Sessions</flux:heading>
        
        @if($sessions->count() > 0)
            <div class="space-y-3">
                @foreach($sessions->groupBy(function($session) { return $session->session_date->format('Y-m-d'); })->take(10) as $date => $daySessions)
                    <div class="border-l-4 border-blue-500 pl-3">
                        <div class="font-medium text-gray-900 dark:text-gray-100">
                            {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $daySessions->count() }} session{{ $daySessions->count() !== 1 ? 's' : '' }}
                        </div>
                    </div>
                @endforeach
                
                @if($sessions->groupBy(function($session) { return $session->session_date->format('Y-m-d'); })->count() > 10)
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400 pt-3">
                        And {{ $sessions->groupBy(function($session) { return $session->session_date->format('Y-m-d'); })->count() - 10 }} more days...
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-6 text-gray-400 dark:text-gray-500">
                <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                <div class="text-sm">No sessions this month</div>
            </div>
        @endif
    </flux:card>
</div>