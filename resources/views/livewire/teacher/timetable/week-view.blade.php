{{-- Week View --}}
<div class="overflow-x-auto">
    <div class="grid grid-cols-7 gap-px bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden min-w-full">
        @foreach($days as $day)
            <div class="bg-white dark:bg-gray-800 {{ $day['isToday'] ? 'ring-2 ring-blue-500 dark:ring-blue-400' : '' }}">
                {{-- Day Header --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="text-center">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            {{ $day['dayName'] }}
                        </div>
                        <div class="mt-1 text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ $day['dayNumber'] }}
                        </div>
                    </div>
                </div>
                
                {{-- Day Sessions and Scheduled Slots --}}
                <div class="p-2 min-h-[200px] space-y-1">
                    @php
                        // Combine sessions and scheduled slots, sorted by time
                        $combinedItems = collect();
                        
                        // Add existing sessions
                        foreach($day['sessions'] as $session) {
                            $combinedItems->push([
                                'type' => 'session',
                                'time' => $session->session_time->format('H:i'),
                                'displayTime' => $session->session_time->format('g:i A'),
                                'session' => $session,
                                'class' => $session->class,
                            ]);
                        }
                        
                        // Add scheduled slots without existing sessions
                        foreach($day['scheduledSlots'] as $slot) {
                            // Only add if there's no existing session for this time/class combo
                            if (!$slot['session']) {
                                $combinedItems->push([
                                    'type' => 'scheduled',
                                    'time' => $slot['time'],
                                    'displayTime' => \Carbon\Carbon::parse($slot['time'])->format('g:i A'),
                                    'session' => null,
                                    'class' => $slot['class'],
                                ]);
                            }
                        }
                        
                        $sortedItems = $combinedItems->sortBy('time');
                    @endphp
                    
                    @forelse($sortedItems as $item)
                        @if($item['type'] === 'session')
                            {{-- Existing Session --}}
                            @php $session = $item['session']; @endphp
                            <div 
                                class="group cursor-pointer rounded-lg p-2 text-xs transition-all duration-200 hover:shadow-md
                                       @switch($session->status)
                                           @case('scheduled')
                                               bg-blue-100 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 hover:bg-blue-200 dark:hover:bg-blue-900/50
                                               @break
                                           @case('ongoing')
                                               bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 hover:bg-green-200 dark:hover:bg-green-900/50 animate-pulse
                                               @break
                                           @case('completed')
                                               bg-gray-100 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-800 hover:bg-gray-200 dark:hover:bg-gray-900/50
                                               @break
                                           @case('cancelled')
                                               bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 hover:bg-red-200 dark:hover:bg-red-900/50
                                               @break
                                           @default
                                               bg-gray-100 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-800 hover:bg-gray-200 dark:hover:bg-gray-900/50
                                       @endswitch"
                                wire:click="selectSession({{ $session->id }})"
                            >
                                {{-- Session Time --}}
                                <div class="font-medium {{ $session->status === 'ongoing' ? 'text-green-800 dark:text-green-200' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ $item['displayTime'] }}
                                </div>
                                
                                {{-- Session Title --}}
                                <div class="text-gray-800 dark:text-gray-200 font-medium truncate" title="{{ $session->class->title }}">
                                    {{ $session->class->title }}
                                </div>
                                
                                {{-- Session Course --}}
                                <div class="text-gray-600 dark:text-gray-400 truncate" title="{{ $session->class->course->title }}">
                                    {{ $session->class->course->title }}
                                </div>
                                
                                {{-- Session Duration & Students --}}
                                <div class="flex items-center justify-between mt-1 text-gray-500 dark:text-gray-500">
                                    <span>{{ $session->formatted_duration }}</span>
                                    @if($session->attendances->count() > 0)
                                        <span class="text-xs">{{ $session->attendances->count() }} students</span>
                                    @endif
                                </div>
                                
                                {{-- Status Indicator and Actions --}}
                                <div class="flex items-center justify-between mt-1">
                                    <flux:badge 
                                        size="sm" 
                                        class="{{ $session->status_badge_class }}"
                                    >
                                        {{ ucfirst($session->status) }}
                                    </flux:badge>
                                    
                                    @if($session->status === 'ongoing')
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                            <span class="text-xs text-green-600 dark:text-green-400">Live</span>
                                        </div>
                                    @endif
                                </div>
                                
                                {{-- Timer Display for Ongoing Sessions --}}
                                @if($session->status === 'ongoing')
                                    <div class="mt-2 text-center" x-data="{ 
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
                                            <div class="text-xs font-mono font-semibold text-green-700 dark:text-green-300" x-text="formatTime(elapsedTime)">
                                            </div>
                                            <div class="text-xs text-green-600 dark:text-green-400">Elapsed</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            
                        @else
                            {{-- Scheduled Slot Without Session --}}
                            @php $class = $item['class']; @endphp
                            <div class="rounded-lg p-2 text-xs bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 transition-all duration-200 hover:bg-indigo-100 dark:hover:bg-indigo-900/50">
                                {{-- Scheduled Time --}}
                                <div class="font-medium text-indigo-900 dark:text-indigo-100">
                                    {{ $item['displayTime'] }}
                                </div>
                                
                                {{-- Class Title --}}
                                <div class="text-indigo-800 dark:text-indigo-200 font-medium truncate" title="{{ $class->title }}">
                                    {{ $class->title }}
                                </div>
                                
                                {{-- Course Name --}}
                                <div class="text-indigo-600 dark:text-indigo-400 truncate" title="{{ $class->course->title }}">
                                    {{ $class->course->title }}
                                </div>
                                
                                {{-- Scheduled Badge --}}
                                <div class="flex items-center justify-between mt-1">
                                    <flux:badge size="sm" color="indigo">Scheduled</flux:badge>
                                </div>
                                
                                {{-- Start Session Button --}}
                                <div class="mt-2">
                                    <flux:button 
                                        variant="primary" 
                                        size="xs" 
                                        class="w-full"
                                        wire:click.stop="startSessionFromTimetable({{ $class->id }}, '{{ $day['date']->toDateString() }}', '{{ $item['time'] }}')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <flux:icon name="play" variant="micro" />
                                            <span>Start Session</span>
                                        </div>
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                            <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                            <div class="text-sm">No sessions or scheduled classes</div>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Mobile Week View --}}
<div class="md:hidden mt-6">
    <div class="space-y-4">
        @foreach($days as $day)
            <flux:card class="{{ $day['isToday'] ? 'ring-2 ring-blue-500 dark:ring-blue-400' : '' }}">
                {{-- Mobile Day Header --}}
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ $day['dayName'] }}, {{ $day['date']->format('M d') }}
                        </div>
                        @if($day['isToday'])
                            <flux:badge color="blue" size="sm">Today</flux:badge>
                        @endif
                    </div>
                    @if($day['sessions']->count() > 0)
                        <flux:badge variant="outline">{{ $day['sessions']->count() }} session{{ $day['sessions']->count() !== 1 ? 's' : '' }}</flux:badge>
                    @endif
                </div>
                
                {{-- Mobile Sessions and Scheduled Slots List --}}
                @php
                    // Same logic as desktop view for mobile
                    $combinedItems = collect();
                    
                    // Add existing sessions
                    foreach($day['sessions'] as $session) {
                        $combinedItems->push([
                            'type' => 'session',
                            'time' => $session->session_time->format('H:i'),
                            'displayTime' => $session->session_time->format('g:i A'),
                            'session' => $session,
                            'class' => $session->class,
                        ]);
                    }
                    
                    // Add scheduled slots without existing sessions
                    foreach($day['scheduledSlots'] as $slot) {
                        if (!$slot['session']) {
                            $combinedItems->push([
                                'type' => 'scheduled',
                                'time' => $slot['time'],
                                'displayTime' => \Carbon\Carbon::parse($slot['time'])->format('g:i A'),
                                'session' => null,
                                'class' => $slot['class'],
                            ]);
                        }
                    }
                    
                    $sortedItems = $combinedItems->sortBy('time');
                @endphp
                
                @forelse($sortedItems as $item)
                    @if($item['type'] === 'session')
                        {{-- Existing Session --}}
                        @php $session = $item['session']; @endphp
                        <div 
                            class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 mb-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            wire:click="selectSession({{ $session->id }})"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $item['displayTime'] }}
                                        </span>
                                        <flux:badge class="{{ $session->status_badge_class }}" size="sm">
                                            {{ ucfirst($session->status) }}
                                        </flux:badge>
                                    </div>
                                    <div class="font-medium text-gray-800 dark:text-gray-200 truncate">
                                        {{ $session->class->title }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                        {{ $session->class->course->title }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        {{ $session->formatted_duration }}
                                        @if($session->attendances->count() > 0)
                                            • {{ $session->attendances->count() }} student{{ $session->attendances->count() !== 1 ? 's' : '' }}
                                        @endif
                                    </div>
                                    
                                    {{-- Timer Display for Mobile --}}
                                    @if($session->status === 'ongoing')
                                        <div class="mt-2" x-data="{ 
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
                                            <div class="bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-full px-3 py-1 inline-flex items-center gap-2">
                                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                <div class="text-xs font-mono font-semibold text-green-700 dark:text-green-300" x-text="formatTime(elapsedTime)">
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="ml-3 flex-shrink-0">
                                    <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400" />
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Scheduled Slot Without Session --}}
                        @php $class = $item['class']; @endphp
                        <div class="border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg p-3 mb-3">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-medium text-indigo-900 dark:text-indigo-100">
                                            {{ $item['displayTime'] }}
                                        </span>
                                        <flux:badge color="indigo" size="sm">Scheduled</flux:badge>
                                    </div>
                                    <div class="font-medium text-indigo-800 dark:text-indigo-200 truncate">
                                        {{ $class->title }}
                                    </div>
                                    <div class="text-sm text-indigo-600 dark:text-indigo-400 truncate">
                                        {{ $class->course->title }}
                                    </div>
                                    <div class="text-xs text-indigo-500 dark:text-indigo-500 mt-1">
                                        {{ $class->duration_minutes ?? 60 }} min
                                    </div>
                                </div>
                                
                                <div class="ml-3 flex-shrink-0">
                                    <flux:button 
                                        variant="primary" 
                                        size="xs" 
                                        wire:click="startSessionFromTimetable({{ $class->id }}, '{{ $day['date']->toDateString() }}', '{{ $item['time'] }}')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <flux:icon name="play" variant="micro" />
                                            <span>Start</span>
                                        </div>
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="text-center py-6 text-gray-400 dark:text-gray-500">
                        <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                        <div class="text-sm">No sessions or scheduled classes</div>
                    </div>
                @endforelse
            </flux:card>
        @endforeach
    </div>
</div>