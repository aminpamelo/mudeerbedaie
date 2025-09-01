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
                            @forelse($timeSlot['sessions'] as $session)
                                <div class="group cursor-pointer rounded-lg p-4 mb-2 last:mb-0 transition-all duration-200 hover:shadow-md
                                           @switch($session->status)
                                               @case('scheduled')
                                                   bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30
                                                   @break
                                               @case('ongoing')
                                                   bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30 ring-2 ring-green-500 dark:ring-green-400 animate-pulse
                                                   @break
                                               @case('completed')
                                                   bg-gray-50 dark:bg-gray-900/20 border border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-900/30
                                                   @break
                                               @case('cancelled')
                                                   bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-900/30
                                                   @break
                                               @default
                                                   bg-gray-50 dark:bg-gray-900/20 border border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-900/30
                                           @endswitch"
                                    wire:click="selectSession({{ $session->id }})"
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
                                                    {{ $session->class->course->title }}
                                                </div>
                                                
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    <flux:icon name="clock" class="w-4 h-4 inline mr-1" />
                                                    {{ $session->session_time->format('g:i A') }} - {{ $session->session_time->addMinutes($session->duration_minutes)->format('g:i A') }}
                                                    ({{ $session->formatted_duration }})
                                                </div>
                                                
                                                @if($session->attendances->count() > 0)
                                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                                        <flux:icon name="users" class="w-4 h-4 inline mr-1" />
                                                        {{ $session->attendances->count() }} student{{ $session->attendances->count() !== 1 ? 's' : '' }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        {{-- Status and Actions --}}
                                        <div class="flex flex-col items-end gap-2">
                                            <flux:badge class="{{ $session->status_badge_class }}">
                                                {{ ucfirst($session->status) }}
                                            </flux:badge>
                                            
                                            {{-- Quick Actions --}}
                                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                @if($session->isScheduled())
                                                    <flux:button wire:click.stop="startSession({{ $session->id }})" size="xs" variant="primary">
                                                        Start
                                                    </flux:button>
                                                @elseif($session->isOngoing())
                                                    <flux:button wire:click.stop="completeSession({{ $session->id }})" size="xs" variant="primary">
                                                        Complete
                                                    </flux:button>
                                                @endif
                                                
                                                <flux:button wire:click.stop="selectSession({{ $session->id }})" size="xs" variant="ghost">
                                                    View
                                                </flux:button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                {{-- Empty time slot --}}
                            @endforelse
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
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                {{ $session->class->course->title }}
                            </div>
                            
                            {{-- Session Meta --}}
                            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-500">
                                <span>{{ $session->formatted_duration }}</span>
                                @if($session->attendances->count() > 0)
                                    <span>{{ $session->attendances->count() }} student{{ $session->attendances->count() !== 1 ? 's' : '' }}</span>
                                @endif
                            </div>
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
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            @if($session->isScheduled())
                                <flux:button wire:click="startSession({{ $session->id }})" size="sm" variant="primary">
                                    Start Session
                                </flux:button>
                            @elseif($session->isOngoing())
                                <flux:button wire:click="completeSession({{ $session->id }})" size="sm" variant="primary">
                                    Complete Session
                                </flux:button>
                            @endif
                        </div>
                        
                        <flux:button wire:click="selectSession({{ $session->id }})" size="sm" variant="ghost">
                            View Details
                        </flux:button>
                    </div>
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