{{-- Week View with Horizontal Scrolling --}}
<div class="overflow-x-auto scrollbar-hide">
    <div class="grid gap-px bg-gray-200  rounded-lg overflow-hidden" style="grid-template-columns: repeat(7, minmax(280px, 1fr)); min-width: 1960px;">
        @foreach($days as $day)
            <div class="bg-white  {{ $day['isToday'] ? 'ring-2 ring-blue-500 ' : '' }}">
                {{-- Day Header --}}
                <div class="p-4 border-b border-gray-200">
                    <div class="text-center">
                        <div class="text-xs font-medium text-gray-500  uppercase tracking-wide">
                            {{ $day['dayName'] }}
                        </div>
                        <div class="mt-1 text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600 ' : 'text-gray-900 ' }}">
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
                                               bg-blue-100 /30 border border-blue-200  hover:bg-blue-200 :bg-blue-900/50
                                               @break
                                           @case('ongoing')
                                               bg-green-100 /30 border border-green-200  hover:bg-green-200 :bg-green-900/50 animate-pulse
                                               @break
                                           @case('completed')
                                               bg-gray-100 /30 border border-gray-200  hover:bg-gray-200 :bg-gray-900/50
                                               @break
                                           @case('cancelled')
                                               bg-red-100 /30 border border-red-200  hover:bg-red-200 :bg-red-900/50
                                               @break
                                           @default
                                               bg-gray-100 /30 border border-gray-200  hover:bg-gray-200 :bg-gray-900/50
                                       @endswitch"
                                wire:click="selectSession({{ $session->id }})"
                            >
                                {{-- Session Time --}}
                                <div class="font-medium {{ $session->status === 'ongoing' ? 'text-green-800 ' : 'text-gray-900 ' }}">
                                    {{ $item['displayTime'] }}
                                </div>
                                
                                {{-- Session Title --}}
                                <div class="text-gray-800  font-medium truncate" title="{{ $session->class->title }}">
                                    {{ $session->class->title }}
                                </div>
                                
                                {{-- Session Course --}}
                                <div class="text-gray-600  truncate" title="{{ $session->class->course->title }}">
                                    {{ $session->class->course->title }}
                                </div>
                                
                                {{-- Session Duration & Students --}}
                                <div class="flex items-center justify-between mt-1 text-gray-500">
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
                                            <span class="text-xs text-green-600">Live</span>
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
                                        <div class="bg-green-100 /30 border border-green-200  rounded px-2 py-1">
                                            <div class="text-xs font-mono font-semibold text-green-700" x-text="formatTime(elapsedTime)">
                                            </div>
                                            <div class="text-xs text-green-600">Elapsed</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            
                        @else
                            {{-- Scheduled Slot Without Session --}}
                            @php $class = $item['class']; @endphp
                            <div class="rounded-lg p-2 text-xs bg-indigo-50 /30 border border-indigo-200  transition-all duration-200 hover:bg-indigo-100 :bg-indigo-900/50">
                                {{-- Scheduled Time --}}
                                <div class="font-medium text-indigo-900">
                                    {{ $item['displayTime'] }}
                                </div>
                                
                                {{-- Class Title --}}
                                <div class="text-indigo-800  font-medium truncate" title="{{ $class->title }}">
                                    {{ $class->title }}
                                </div>
                                
                                {{-- Course Name --}}
                                <div class="text-indigo-600  truncate" title="{{ $class->course->title }}">
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
                        <div class="text-center py-8 text-gray-400">
                            <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                            <div class="text-sm">No sessions or scheduled classes</div>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Mobile Week View with Horizontal Scrolling --}}
<div class="md:hidden mt-6" x-data="{
    scrollContainer: null,
    currentDayIndex: 0,
    
    init() {
        this.scrollContainer = this.$refs.scrollContainer;
        // Find today's index
        const todayIndex = {{ collect($days)->search(function($day) { return $day['isToday']; }) ?: 0 }};
        this.currentDayIndex = todayIndex;
        this.$nextTick(() => {
            this.scrollToDay(todayIndex);
        });
        
        // Listen for scroll events to update current day indicator
        this.scrollContainer.addEventListener('scroll', this.updateCurrentDay.bind(this));
    },
    
    scrollToDay(dayIndex) {
        if (this.scrollContainer) {
            const dayWidth = this.scrollContainer.scrollWidth / {{ count($days) }};
            const scrollPosition = dayIndex * dayWidth;
            this.scrollContainer.scrollTo({
                left: scrollPosition,
                behavior: 'smooth'
            });
            this.currentDayIndex = dayIndex;
        }
    },
    
    updateCurrentDay() {
        if (this.scrollContainer) {
            const dayWidth = this.scrollContainer.scrollWidth / {{ count($days) }};
            const scrollLeft = this.scrollContainer.scrollLeft;
            const newDayIndex = Math.round(scrollLeft / dayWidth);
            this.currentDayIndex = Math.max(0, Math.min(newDayIndex, {{ count($days) - 1 }}));
        }
    },
    
    previousDay() {
        if (this.currentDayIndex > 0) {
            this.scrollToDay(this.currentDayIndex - 1);
        }
    },
    
    nextDay() {
        if (this.currentDayIndex < {{ count($days) - 1 }}) {
            this.scrollToDay(this.currentDayIndex + 1);
        }
    }
}">
    {{-- Day Navigation --}}
    <div class="flex items-center justify-between mb-4 px-2">
        <flux:button 
            variant="ghost" 
            size="sm" 
            x-on:click="previousDay()" 
            x-bind:disabled="currentDayIndex === 0"
            x-bind:class="currentDayIndex === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 :bg-gray-800'"
        >
            <flux:icon name="chevron-left" class="w-5 h-5" />
        </flux:button>
        
        <div class="text-center">
            <div class="text-sm font-medium text-gray-900" x-text="
                (() => {
                    const days = {{ collect($days)->map(function($day) { return $day['dayName'] . ', ' . $day['date']->format('M d'); })->toJson() }};
                    return days[currentDayIndex] || 'Current Day';
                })()">
            </div>
            <div class="text-xs text-gray-500">
                Day <span x-text="currentDayIndex + 1"></span> of {{ count($days) }}
            </div>
        </div>
        
        <flux:button 
            variant="ghost" 
            size="sm" 
            x-on:click="nextDay()" 
            x-bind:disabled="currentDayIndex === {{ count($days) - 1 }}"
            x-bind:class="currentDayIndex === {{ count($days) - 1 }} ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 :bg-gray-800'"
        >
            <flux:icon name="chevron-right" class="w-5 h-5" />
        </flux:button>
    </div>
    
    {{-- Day Dots Indicator --}}
    <div class="flex justify-center items-center gap-2 mb-4">
        @foreach($days as $index => $day)
            <button 
                class="w-2 h-2 rounded-full transition-all duration-200"
                x-bind:class="currentDayIndex === {{ $index }} ? 'bg-blue-500 w-6' : 'bg-gray-300 '"
                x-on:click="scrollToDay({{ $index }})"
                title="{{ $day['dayName'] }}, {{ $day['date']->format('M d') }}"
            ></button>
        @endforeach
    </div>
    
    {{-- Horizontal Scrolling Container --}}
    <div 
        x-ref="scrollContainer"
        class="overflow-x-auto scrollbar-hide scroll-smooth"
        style="scroll-snap-type: x mandatory;"
    >
        <div class="flex gap-4 pb-4" style="width: {{ count($days) * 100 }}%;">
            @foreach($days as $day)
                <div 
                    class="flex-shrink-0 bg-white  border border-gray-200  rounded-lg {{ $day['isToday'] ? 'ring-2 ring-blue-500 ' : '' }}"
                    style="width: calc(100% / {{ count($days) }} - 0.75rem); scroll-snap-align: start;"
                >
                    {{-- Mobile Day Header --}}
                    <div class="p-4 border-b border-gray-200">
                        <div class="text-center">
                            <div class="text-xs font-medium text-gray-500  uppercase tracking-wide">
                                {{ $day['dayName'] }}
                            </div>
                            <div class="mt-1 text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600 ' : 'text-gray-900 ' }}">
                                {{ $day['dayNumber'] }}
                            </div>
                            @if($day['isToday'])
                                <flux:badge color="blue" size="sm" class="mt-1">Today</flux:badge>
                            @endif
                        </div>
                        @if($day['sessions']->count() > 0)
                            <div class="text-center mt-2">
                                <flux:badge variant="outline" size="sm">{{ $day['sessions']->count() }} session{{ $day['sessions']->count() !== 1 ? 's' : '' }}</flux:badge>
                            </div>
                        @endif
                    </div>
                    
                    {{-- Mobile Sessions and Scheduled Slots --}}
                    <div class="p-3 min-h-[300px] space-y-2">
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
                                    class="group cursor-pointer rounded-lg p-3 text-sm transition-all duration-200 hover:shadow-md
                                           @switch($session->status)
                                               @case('scheduled')
                                                   bg-blue-100 /30 border border-blue-200  hover:bg-blue-200 :bg-blue-900/50
                                                   @break
                                               @case('ongoing')
                                                   bg-green-100 /30 border border-green-200  hover:bg-green-200 :bg-green-900/50 animate-pulse
                                                   @break
                                               @case('completed')
                                                   bg-gray-100 /30 border border-gray-200  hover:bg-gray-200 :bg-gray-900/50
                                                   @break
                                               @case('cancelled')
                                                   bg-red-100 /30 border border-red-200  hover:bg-red-200 :bg-red-900/50
                                                   @break
                                               @default
                                                   bg-gray-100 /30 border border-gray-200  hover:bg-gray-200 :bg-gray-900/50
                                           @endswitch"
                                    wire:click="selectSession({{ $session->id }})"
                                >
                                    <div class="font-medium {{ $session->status === 'ongoing' ? 'text-green-800 ' : 'text-gray-900 ' }} mb-1">
                                        {{ $item['displayTime'] }}
                                    </div>
                                    
                                    <div class="text-gray-800  font-medium truncate mb-1" title="{{ $session->class->title }}">
                                        {{ $session->class->title }}
                                    </div>
                                    
                                    <div class="text-gray-600  text-xs truncate mb-2" title="{{ $session->class->course->title }}">
                                        {{ $session->class->course->title }}
                                    </div>
                                    
                                    <div class="flex items-center justify-between text-xs text-gray-500  mb-2">
                                        <span>{{ $session->formatted_duration }}</span>
                                        @if($session->attendances->count() > 0)
                                            <span>{{ $session->attendances->count() }} students</span>
                                        @endif
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <flux:badge 
                                            size="sm" 
                                            class="{{ $session->status_badge_class }}"
                                        >
                                            {{ ucfirst($session->status) }}
                                        </flux:badge>
                                        
                                        @if($session->status === 'ongoing')
                                            <div class="flex items-center gap-1">
                                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                <span class="text-xs text-green-600">Live</span>
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
                                            <div class="bg-green-100 /30 border border-green-200  rounded-full px-2 py-1 inline-flex items-center gap-1">
                                                <div class="text-xs font-mono font-semibold text-green-700" x-text="formatTime(elapsedTime)">
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                
                            @else
                                {{-- Scheduled Slot Without Session --}}
                                @php $class = $item['class']; @endphp
                                <div class="rounded-lg p-3 text-sm bg-indigo-50 /30 border border-indigo-200  transition-all duration-200 hover:bg-indigo-100 :bg-indigo-900/50">
                                    <div class="font-medium text-indigo-900  mb-1">
                                        {{ $item['displayTime'] }}
                                    </div>
                                    
                                    <div class="text-indigo-800  font-medium truncate mb-1" title="{{ $class->title }}">
                                        {{ $class->title }}
                                    </div>
                                    
                                    <div class="text-indigo-600  text-xs truncate mb-2" title="{{ $class->course->title }}">
                                        {{ $class->course->title }}
                                    </div>
                                    
                                    <div class="flex items-center justify-between mb-2">
                                        <flux:badge size="sm" color="indigo">Scheduled</flux:badge>
                                    </div>
                                    
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
                            @endif
                        @empty
                            <div class="text-center py-8 text-gray-400">
                                <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <div class="text-sm">No sessions scheduled</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Custom CSS for smooth scrolling --}}
<style>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>