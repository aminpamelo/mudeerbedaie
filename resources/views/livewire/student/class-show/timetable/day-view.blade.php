{{-- Day View with Time Slots --}}
<div class="space-y-1">
    @forelse($timeSlots as $slot)
        <div class="flex items-start gap-4 p-3 border border-gray-200  rounded-lg {{ $slot['sessions']->count() > 0 ? 'bg-white ' : 'bg-gray-50 ' }}">
            {{-- Time Label --}}
            <div class="flex-shrink-0 w-20 text-center">
                <div class="text-sm font-medium text-gray-900">
                    {{ $slot['displayTime'] }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ $slot['time'] }}
                </div>
            </div>
            
            {{-- Sessions for this time slot --}}
            <div class="flex-1 min-h-[60px] flex items-center">
                @if($slot['sessions']->count() > 0)
                    <div class="w-full space-y-2">
                        @foreach($slot['sessions'] as $session)
                            <div 
                                class="group cursor-pointer rounded-lg p-3 transition-all duration-200 hover:shadow-md
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
                                @if($session->status === 'completed')
                                    wire:click="selectSession({{ $session->id }})"
                                @endif
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        {{-- Session Title --}}
                                        <div class="font-medium text-gray-900  mb-1">
                                            {{ $session->class->title }}
                                        </div>
                                        
                                        {{-- Session Details --}}
                                        <div class="text-sm text-gray-600  space-y-1">
                                            <div class="flex items-center gap-4">
                                                <span>{{ $session->formatted_duration }}</span>
                                                <span>{{ $session->session_time->format('g:i A') }}</span>
                                            </div>
                                        </div>
                                        
                                        {{-- Attendance Status - hidden for now, can be re-enabled later
                                        @if($session->attendances->count() > 0)
                                            <div class="mt-2 flex flex-wrap gap-2">
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
                                        --}}

                                        {{-- Session Notes Preview --}}
                                        @if($session->teacher_notes && $session->status === 'completed')
                                            <div class="mt-2 text-xs text-gray-500">
                                                {{ Str::limit($session->teacher_notes, 100) }}
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="flex flex-col items-end gap-2 ml-4">
                                        {{-- Session Status --}}
                                        <flux:badge
                                            class="{{ $session->student_status_badge_class }}"
                                            size="sm"
                                        >
                                            {{ $session->student_status_label }}
                                        </flux:badge>
                                        
                                        {{-- Live Indicator for Ongoing Sessions --}}
                                        @if($session->status === 'ongoing')
                                            <div class="flex items-center gap-1">
                                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                <span class="text-xs text-green-600">Live</span>
                                            </div>
                                        @endif
                                        
                                        {{-- View Details Button --}}
                                        @if($session->status === 'completed')
                                            <div class="text-xs text-gray-500">
                                                Click to view
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                
                                {{-- Timer Display for Ongoing Sessions --}}
                                @if($session->status === 'ongoing')
                                    <div class="mt-3 text-center" x-data="{ 
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
                                        <div class="bg-green-100 /30 border border-green-200  rounded-lg px-4 py-2 inline-flex items-center gap-2">
                                            <div class="text-sm font-mono font-semibold text-green-700" x-text="formatTime(elapsedTime)">
                                            </div>
                                            <div class="text-xs text-green-600">Session in progress</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- Empty Time Slot --}}
                    <div class="flex items-center justify-center h-full text-gray-400">
                        <div class="text-center">
                            <flux:icon name="clock" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                            <div class="text-sm">No sessions scheduled</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-12">
            <flux:icon name="calendar-days" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-2 text-gray-500">No time slots available</flux:heading>
            <flux:text class="text-gray-400">
                There are no time slots configured for this day.
            </flux:text>
        </div>
    @endforelse
</div>

{{-- Mobile Day View --}}
<div class="md:hidden mt-6">
    <div class="space-y-2">
        @forelse($timeSlots as $slot)
            @if($slot['sessions']->count() > 0)
                <div class="bg-white  border border-gray-200  rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="font-medium text-gray-900">
                            {{ $slot['displayTime'] }}
                        </div>
                        <flux:badge variant="outline" size="sm">
                            {{ $slot['sessions']->count() }} session{{ $slot['sessions']->count() !== 1 ? 's' : '' }}
                        </flux:badge>
                    </div>
                    
                    <div class="space-y-3">
                        @foreach($slot['sessions'] as $session)
                            <div 
                                class="rounded-lg p-3 border
                                       @switch($session->status)
                                           @case('scheduled')
                                               bg-blue-50 /20 border-blue-200 
                                               @break
                                           @case('ongoing')
                                               bg-green-50 /20 border-green-200  animate-pulse
                                               @break
                                           @case('completed')
                                               bg-gray-50 /20 border-gray-200 
                                               @break
                                           @case('cancelled')
                                               bg-red-50 /20 border-red-200 
                                               @break
                                           @default
                                               bg-gray-50 /20 border-gray-200 
                                       @endswitch"
                                @if($session->status === 'completed')
                                    wire:click="selectSession({{ $session->id }})"
                                @endif
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">
                                            {{ $session->class->title }}
                                        </div>
                                        <div class="text-sm text-gray-600  mt-1">
                                            {{ $session->session_time->format('g:i A') }} - {{ $session->formatted_duration }}
                                        </div>
                                    </div>
                                    
                                    <flux:badge
                                        class="{{ $session->student_status_badge_class }}"
                                        size="sm"
                                    >
                                        {{ $session->student_status_label }}
                                    </flux:badge>
                                </div>

                                {{-- Attendance hidden for now - can be re-enabled later
                                @if($session->attendances->count() > 0)
                                    <div class="flex flex-wrap gap-1">
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
                                --}}

                                @if($session->status === 'completed')
                                    <div class="text-center mt-2">
                                        <flux:text size="xs" class="text-gray-500">Tap to view details</flux:text>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @empty
            <div class="text-center py-8">
                <flux:icon name="calendar-days" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <flux:text class="text-gray-600">No sessions scheduled for this day</flux:text>
            </div>
        @endforelse
    </div>
</div>