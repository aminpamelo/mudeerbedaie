{{-- Month View Calendar Grid --}}
<div class="overflow-x-auto">
    {{-- Calendar Header - Days of Week --}}
    <div class="grid grid-cols-7 border-b border-gray-200">
        @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
            <div class="p-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">
                {{ $dayName }}
            </div>
        @endforeach
    </div>

    {{-- Calendar Weeks --}}
    <div class="divide-y divide-gray-200">
        @foreach($weeks as $week)
            <div class="grid grid-cols-7 divide-x divide-gray-200">
                @foreach($week as $day)
                    <div
                        class="min-h-[120px] p-2 {{ $day['isCurrentMonth'] ? 'bg-white' : 'bg-gray-50' }} {{ $day['isToday'] ? 'ring-2 ring-blue-500 ring-inset' : '' }} hover:bg-gray-50 transition-colors cursor-pointer"
                        wire:click="goToDay('{{ $day['date']->toDateString() }}')"
                    >
                        {{-- Day Number --}}
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium {{ $day['isToday'] ? 'bg-blue-600 text-white rounded-full w-7 h-7 flex items-center justify-center' : ($day['isCurrentMonth'] ? 'text-gray-900' : 'text-gray-400') }}">
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
                                    @php $color = $this->getClassColor($session->class_id); @endphp
                                    <div class="flex items-center gap-1.5 text-xs truncate {{ !$day['isCurrentMonth'] ? 'opacity-60' : '' }}">
                                        <div class="w-2 h-2 rounded-full shrink-0 {{ $color['dot'] }}
                                            @if($session->status === 'ongoing') animate-pulse @endif
                                            @if($session->status === 'completed') opacity-50 @endif
                                            @if($session->status === 'cancelled') opacity-30 @endif
                                        "></div>
                                        <span class="truncate {{ $session->status === 'completed' ? 'text-gray-400' : 'text-gray-600' }} {{ $session->status === 'cancelled' ? 'line-through text-gray-300' : '' }}">
                                            {{ $session->session_time->format('g:i') }} {{ $session->class->title }}
                                        </span>
                                    </div>
                                @endforeach
                                @if($day['sessions']->count() > 3)
                                    <div class="text-xs text-gray-400">
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
                    <div class="text-xs font-medium text-gray-500">{{ $session->session_date->format('M') }}</div>
                    <div class="text-lg font-bold {{ $color['text'] }}">{{ $session->session_date->format('d') }}</div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 truncate">{{ $session->class->title }}</div>
                    <div class="text-sm text-gray-500">{{ $session->session_time->format('g:i A') }} - {{ $session->class->teacher?->user?->name ?? 'N/A' }}</div>
                </div>
                <flux:badge size="sm" class="{{ $session->status_badge_class }}">
                    {{ ucfirst($session->status) }}
                </flux:badge>
            </div>
        @empty
            <div class="text-center py-8 text-gray-400">
                <flux:icon name="calendar" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                <div class="text-sm">No sessions this month</div>
            </div>
        @endforelse

        @if($allSessions->count() > 10)
            <div class="text-center pt-4">
                <flux:text size="sm" class="text-gray-500">
                    Showing 10 of {{ $allSessions->count() }} sessions. Click on a day to view all.
                </flux:text>
            </div>
        @endif
    </div>
</div>
