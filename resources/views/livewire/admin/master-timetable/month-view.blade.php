{{-- Month View Calendar Grid --}}
<div class="overflow-x-auto">
    {{-- Calendar Header - Days of Week --}}
    <div class="grid grid-cols-7 border-b border-gray-200 dark:border-zinc-700">
        @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
            <div class="p-3 text-center text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wide">
                {{ $dayName }}
            </div>
        @endforeach
    </div>

    {{-- Calendar Weeks --}}
    <div class="divide-y divide-gray-200 dark:divide-zinc-700">
        @foreach($weeks as $week)
            <div class="grid grid-cols-7 divide-x divide-gray-200 dark:divide-zinc-700">
                @foreach($week as $day)
                    <div
                        class="min-h-[120px] p-2 {{ $day['isCurrentMonth'] ? 'bg-white dark:bg-zinc-800' : 'bg-gray-50 dark:bg-zinc-900/50' }} {{ $day['isToday'] ? 'ring-2 ring-blue-500 ring-inset' : '' }} hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors cursor-pointer"
                        wire:click="goToDay('{{ $day['date']->toDateString() }}')"
                    >
                        {{-- Day Number --}}
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium {{ $day['isToday'] ? 'bg-blue-600 text-white rounded-full w-7 h-7 flex items-center justify-center' : ($day['isCurrentMonth'] ? 'text-gray-900 dark:text-zinc-100' : 'text-gray-400 dark:text-zinc-500') }}">
                                {{ $day['dayNumber'] }}
                            </span>
                            @if($day['sessionCount'] > 0)
                                <flux:badge size="sm" color="blue" variant="outline">
                                    {{ $day['sessionCount'] }}
                                </flux:badge>
                            @endif
                        </div>

                        {{-- Session Dots / Preview --}}
                        @if($day['sessions']->count() > 0)
                            <div class="space-y-1">
                                @foreach($day['sessions']->take(3) as $session)
                                    @php
                                        $color = $this->getClassColor($session->class_id);
                                        $expectedTime = $session->session_date->copy()->setTimeFromTimeString($session->session_time->format('H:i:s'));
                                        $isPastTime = now()->gt($expectedTime);
                                        $isNotStarted = $session->started_at === null;
                                    @endphp
                                    <div class="flex items-center gap-1.5 text-xs truncate {{ !$day['isCurrentMonth'] ? 'opacity-60' : '' }}">
                                        {{-- Status Indicator Dot --}}
                                        @if($session->status === 'scheduled' && $isPastTime && $isNotStarted)
                                            <div class="w-2 h-2 rounded-full shrink-0 bg-red-500" title="Not started"></div>
                                        @elseif($session->started_at)
                                            <div class="w-2 h-2 rounded-full shrink-0 bg-green-500 {{ $session->status === 'ongoing' ? 'animate-pulse' : '' }}" title="Started"></div>
                                        @else
                                            <div class="w-2 h-2 rounded-full shrink-0 {{ $color['dot'] }}
                                                @if($session->status === 'ongoing') animate-pulse @endif
                                                @if($session->status === 'completed') opacity-50 @endif
                                                @if($session->status === 'cancelled') opacity-30 @endif
                                            "></div>
                                        @endif
                                        <span class="truncate {{ $session->status === 'completed' ? 'text-gray-400 dark:text-zinc-500' : 'text-gray-600 dark:text-zinc-300' }} {{ $session->status === 'cancelled' ? 'line-through text-gray-300 dark:text-zinc-600' : '' }}">
                                            {{ $session->session_time->format('g:i') }} {{ $session->class->title }}
                                            @if($this->isClassEnded($session))
                                                <span class="text-red-500 dark:text-red-400">(Ended)</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                                @if($day['sessions']->count() > 3)
                                    <div class="text-xs text-gray-400 dark:text-zinc-500">
                                        +{{ $day['sessions']->count() - 3 }} more
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

{{-- Mobile Month View - Simplified --}}
<div class="md:hidden mt-6">
    <flux:heading size="sm" class="mb-4">Sessions This Month</flux:heading>
    <div class="space-y-2">
        @php
            $allSessions = collect($weeks)->flatten(1)->pluck('sessions')->flatten()->sortBy('session_date')->sortBy('session_time');
        @endphp

        @forelse($allSessions->take(10) as $session)
            @php $color = $this->getClassColor($session->class_id); @endphp
            <div
                class="flex items-center gap-3 p-3 rounded-lg {{ $color['bg'] }} {{ $color['border'] }} border cursor-pointer hover:shadow-md transition-all"
                wire:click="selectSession({{ $session->id }})"
            >
                <div class="shrink-0 text-center">
                    <div class="text-xs font-medium text-gray-500 dark:text-zinc-400">{{ $session->session_date->format('M') }}</div>
                    <div class="text-lg font-bold {{ $color['text'] }}">{{ $session->session_date->format('d') }}</div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 dark:text-zinc-100 truncate">{{ $session->class->title }}</div>
                    <div class="text-sm text-gray-500 dark:text-zinc-400">{{ $session->session_time->format('g:i A') }} - {{ $session->class->teacher?->user?->name ?? 'N/A' }}</div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <flux:badge size="sm" class="{{ $session->status_badge_class }}">
                        {{ ucfirst($session->status) }}
                    </flux:badge>
                    @if($this->isClassEnded($session))
                        <flux:badge size="sm" color="red">Ended</flux:badge>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-400 dark:text-zinc-500">
                <flux:icon name="calendar" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                <div class="text-sm">No sessions this month</div>
            </div>
        @endforelse

        @if($allSessions->count() > 10)
            <div class="text-center pt-4">
                <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">
                    Showing 10 of {{ $allSessions->count() }} sessions. Click on a day to view all.
                </flux:text>
            </div>
        @endif
    </div>
</div>
