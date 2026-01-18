{{-- Day View - Hourly Timeline --}}
<div class="overflow-x-auto">
    <div class="min-w-[600px]">
        {{-- Day Header --}}
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ $this->currentDate->format('l, F j, Y') }}</flux:heading>
                    @if($this->currentDate->isToday())
                        <flux:badge color="blue" size="sm" class="mt-1">Today</flux:badge>
                    @endif
                </div>
                <div class="text-right">
                    @php
                        $totalSessions = collect($timeSlots)->sum(fn($slot) => $slot['sessions']->count());
                    @endphp
                    <flux:text class="text-gray-600">{{ $totalSessions }} session{{ $totalSessions !== 1 ? 's' : '' }} scheduled</flux:text>
                </div>
            </div>
        </div>

        {{-- Hourly Timeline --}}
        <div class="divide-y divide-gray-100">
            @foreach($timeSlots as $slot)
                <div class="flex min-h-[80px] {{ $slot['sessions']->count() > 0 ? 'bg-white' : 'bg-gray-50/50' }}">
                    {{-- Time Column --}}
                    <div class="w-20 shrink-0 p-3 border-r border-gray-200 text-right">
                        <span class="text-sm font-medium text-gray-500">{{ $slot['displayTime'] }}</span>
                    </div>

                    {{-- Sessions Container --}}
                    <div class="flex-1 p-2">
                        @if($slot['sessions']->count() > 0)
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                @foreach($slot['sessions'] as $session)
                                    @php $color = $this->getClassColor($session->class_id); @endphp
                                    <div
                                        class="rounded-lg p-3 cursor-pointer transition-all duration-200 hover:shadow-md {{ $color['bg'] }} {{ $color['border'] }} border
                                               @if($session->status === 'ongoing') animate-pulse @endif
                                               @if($session->status === 'completed') opacity-75 @endif
                                               @if($session->status === 'cancelled') line-through opacity-50 @endif"
                                        wire:click="selectSession({{ $session->id }})"
                                    >
                                        {{-- Session Header --}}
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium {{ $color['text'] }} text-sm">
                                                    {{ $session->session_time->format('g:i A') }}
                                                </div>
                                                <div class="font-semibold text-gray-900 truncate" title="{{ $session->class->title }}">
                                                    {{ $session->class->title }}
                                                </div>
                                            </div>
                                            @if($session->status === 'ongoing')
                                                <div class="flex items-center gap-1 shrink-0 ml-2">
                                                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                    <span class="text-xs text-green-600 font-medium">Live</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Session Details --}}
                                        <div class="space-y-1 text-sm">
                                            {{-- Course --}}
                                            <div class="flex items-center gap-2 text-gray-600">
                                                <flux:icon name="academic-cap" class="w-4 h-4 shrink-0" />
                                                <span class="truncate">{{ $session->class->course?->name ?? 'No Course' }}</span>
                                            </div>

                                            {{-- Teacher --}}
                                            @if($session->class->teacher?->user)
                                                <div class="flex items-center gap-2 text-gray-600">
                                                    <flux:icon name="user" class="w-4 h-4 shrink-0" />
                                                    <span class="truncate">{{ $session->class->teacher->user->name }}</span>
                                                </div>
                                            @endif

                                            {{-- Duration --}}
                                            <div class="flex items-center gap-2 text-gray-500">
                                                <flux:icon name="clock" class="w-4 h-4 shrink-0" />
                                                <span>{{ $session->formatted_duration }}</span>
                                            </div>

                                            {{-- Attendance --}}
                                            @if($session->attendances->count() > 0)
                                                <div class="flex items-center gap-2 text-gray-500">
                                                    <flux:icon name="users" class="w-4 h-4 shrink-0" />
                                                    <span>{{ $session->attendances->count() }} students</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Footer --}}
                                        <div class="flex items-center justify-between mt-3 pt-2 border-t border-gray-200/50">
                                            <flux:badge size="sm" class="{{ $session->status_badge_class }}">
                                                {{ ucfirst($session->status) }}
                                            </flux:badge>

                                            {{-- Categories --}}
                                            @if($session->class->categories->count() > 0)
                                                <div class="flex items-center gap-1">
                                                    @foreach($session->class->categories->take(2) as $category)
                                                        <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">
                                                            {{ $category->name }}
                                                        </span>
                                                    @endforeach
                                                    @if($session->class->categories->count() > 2)
                                                        <span class="text-xs text-gray-400">+{{ $session->class->categories->count() - 2 }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Empty Time Slot --}}
                            <div class="h-full flex items-center justify-center text-gray-300 text-sm">
                                {{-- Empty slot placeholder --}}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- No Sessions Message --}}
        @php
            $hasSessions = collect($timeSlots)->sum(fn($slot) => $slot['sessions']->count()) > 0;
        @endphp
        @if(!$hasSessions)
            <div class="text-center py-12 text-gray-400">
                <flux:icon name="calendar" class="w-16 h-16 mx-auto mb-4 opacity-50" />
                <flux:heading size="md" class="text-gray-500">No Sessions Scheduled</flux:heading>
                <flux:text class="mt-2">There are no sessions scheduled for {{ $this->currentDate->format('F j, Y') }}</flux:text>
            </div>
        @endif
    </div>
</div>
