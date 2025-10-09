{{-- List View --}}
<div class="space-y-4">
    @forelse($sessions as $session)
        <flux:card 
            class="cursor-pointer transition-all duration-200 hover:shadow-md hover:bg-gray-50 :bg-gray-800 group"
            wire:click="selectSession({{ $session->id }})"
        >
            <div class="flex items-start justify-between">
                {{-- Session Details --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start gap-4">
                        {{-- Date/Time Column --}}
                        <div class="flex-shrink-0 text-center min-w-[80px]">
                            <div class="text-lg font-bold text-gray-900">
                                {{ $session->session_date->format('d') }}
                            </div>
                            <div class="text-xs text-gray-500  uppercase tracking-wide">
                                {{ $session->session_date->format('M') }}
                            </div>
                            <div class="text-xs text-gray-400">
                                {{ $session->session_date->format('Y') }}
                            </div>
                            <div class="mt-2 text-sm font-medium text-blue-600">
                                {{ $session->session_time->format('g:i A') }}
                            </div>
                        </div>
                        
                        {{-- Session Info --}}
                        <div class="flex-1 min-w-0">
                            {{-- Title and Status --}}
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-900  truncate group-hover:text-blue-600 :text-blue-400 transition-colors">
                                        {{ $session->class->title }}
                                    </h3>
                                    <p class="text-sm text-gray-600  truncate">
                                        {{ $session->class->course->title }}
                                    </p>
                                </div>
                                
                                <div class="ml-4 flex items-center gap-2">
                                    <flux:badge class="{{ $session->status_badge_class }}">
                                        {{ ucfirst($session->status) }}
                                    </flux:badge>
                                    
                                    @if($session->status === 'ongoing')
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center gap-1">
                                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                <span class="text-xs text-green-600  font-medium">Live</span>
                                            </div>
                                            
                                            {{-- Timer Display --}}
                                            <div class="bg-green-100 /30 border border-green-200  rounded-full px-3 py-1">
                                                <div class="text-xs font-mono font-semibold text-green-700">
                                                    {{ $session->formatted_elapsed_time }}
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Session Meta --}}
                            <div class="flex items-center gap-6 text-sm text-gray-500">
                                <div class="flex items-center gap-1">
                                    <flux:icon name="clock" class="w-4 h-4" />
                                    <span>{{ $session->formatted_duration }}</span>
                                </div>
                                
                                @if($session->attendances->count() > 0)
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="users" class="w-4 h-4" />
                                        <span>{{ $session->attendances->count() }} student{{ $session->attendances->count() !== 1 ? 's' : '' }}</span>
                                    </div>
                                @endif
                                
                                @if($session->class->class_type)
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="tag" class="w-4 h-4" />
                                        <span>{{ ucfirst($session->class->class_type) }}</span>
                                    </div>
                                @endif
                                
                                {{-- Time Until Session --}}
                                @if($session->isScheduled() && $session->session_date->isFuture())
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="calendar" class="w-4 h-4" />
                                        <span>{{ $session->session_date->diffForHumans() }}</span>
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Teacher Notes Preview --}}
                            @if($session->teacher_notes)
                                <div class="mt-2">
                                    <div class="text-xs text-gray-400  uppercase tracking-wide">Notes</div>
                                    <div class="text-sm text-gray-600  truncate">
                                        {{ Str::limit($session->teacher_notes, 100) }}
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Student Attendance Preview --}}
                            @if($session->attendances->count() > 0)
                                <div class="mt-3">
                                    <div class="flex items-center gap-2">
                                        <div class="text-xs text-gray-400  uppercase tracking-wide">Students</div>
                                        <div class="flex -space-x-2">
                                            @foreach($session->attendances->take(3) as $attendance)
                                                <div class="relative">
                                                    <div class="w-6 h-6 rounded-full bg-gray-300  flex items-center justify-center text-xs font-medium text-gray-700  ring-2 ring-white">
                                                        {{ substr($attendance->student->user->name, 0, 1) }}
                                                    </div>
                                                    <div class="absolute -bottom-1 -right-1 w-3 h-3 rounded-full border border-white  
                                                               {{ $attendance->isPresent() ? 'bg-green-500' : ($attendance->isAbsent() ? 'bg-red-500' : 'bg-gray-400') }}">
                                                    </div>
                                                </div>
                                            @endforeach
                                            @if($session->attendances->count() > 3)
                                                <div class="w-6 h-6 rounded-full bg-gray-100  flex items-center justify-center text-xs font-medium text-gray-600  ring-2 ring-white">
                                                    +{{ $session->attendances->count() - 3 }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                {{-- Action Area --}}
                <div class="flex-shrink-0 ml-4 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="flex flex-col gap-2">
                        @if($session->isScheduled())
                            <flux:button wire:click.stop="requestStartSession({{ $session->id }})" size="xs" variant="primary">
                                <flux:icon name="play" class="w-3 h-3 mr-1" />
                                Start
                            </flux:button>
                        @elseif($session->isOngoing())
                            <flux:button wire:click.stop="completeSession({{ $session->id }})" size="xs" variant="primary">
                                <flux:icon name="check" class="w-3 h-3 mr-1" />
                                Complete
                            </flux:button>
                        @endif
                        
                        <flux:button wire:click.stop="selectSession({{ $session->id }})" size="xs" variant="ghost">
                            <flux:icon name="eye" class="w-3 h-3 mr-1" />
                            View
                        </flux:button>
                    </div>
                </div>
                
                {{-- Chevron Icon --}}
                <div class="flex-shrink-0 ml-2">
                    <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 :text-gray-300 transition-colors" />
                </div>
            </div>
        </flux:card>
    @empty
        {{-- Empty State --}}
        <flux:card class="text-center py-16">
            <div class="max-w-sm mx-auto">
                <flux:icon name="calendar" class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                
                <flux:heading size="lg" class="text-gray-500  mb-2">
                    No Upcoming Sessions
                </flux:heading>
                
                <flux:text class="text-gray-400  mb-6">
                    You don't have any sessions scheduled. Your upcoming sessions will appear here once they're created.
                </flux:text>
                
                <div class="flex items-center justify-center gap-3">
                    <flux:button variant="primary" size="sm">
                        <flux:icon name="plus" class="w-4 h-4 mr-2" />
                        Schedule Session
                    </flux:button>
                    
                    <flux:button variant="ghost" size="sm" wire:click="$set('currentView', 'week')">
                        View Calendar
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endforelse
    
    {{-- Load More (if there are many sessions) --}}
    @if($sessions->count() >= 50)
        <div class="text-center py-6">
            <flux:text class="text-gray-500">
                Showing first 50 sessions. Use filters to narrow down results.
            </flux:text>
        </div>
    @endif
</div>

{{-- Mobile Optimizations --}}
<style>
    @media (max-width: 768px) {
        .group:hover .opacity-0 {
            opacity: 1;
        }
    }
</style>