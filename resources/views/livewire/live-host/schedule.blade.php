<?php

use App\Models\LiveSchedule;
use Livewire\Volt\Component;

new class extends Component {
    public int $weekOffset = 0;

    public function previousWeek(): void
    {
        $this->weekOffset--;
    }

    public function nextWeek(): void
    {
        $this->weekOffset++;
    }

    public function jumpToToday(): void
    {
        $this->weekOffset = 0;
    }

    public function getCurrentWeekStartProperty()
    {
        return now()->addWeeks($this->weekOffset)->startOfWeek();
    }

    public function getCurrentWeekEndProperty()
    {
        return now()->addWeeks($this->weekOffset)->endOfWeek();
    }

    public function getIsCurrentWeekProperty(): bool
    {
        return $this->weekOffset === 0;
    }

    public function getSchedulesByDayProperty()
    {
        $schedules = auth()->user()
            ->platformAccounts()
            ->with(['liveSchedules' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('start_time');
            }, 'platform'])
            ->get()
            ->flatMap(function ($account) {
                return $account->liveSchedules->map(function ($schedule) use ($account) {
                    $schedule->platformAccount = $account;
                    return $schedule;
                });
            })
            ->groupBy('day_of_week');

        // Ensure all days are present even if empty
        $days = collect(range(0, 6))->mapWithKeys(function ($day) use ($schedules) {
            return [$day => $schedules->get($day, collect())];
        });

        return $days;
    }

    public function getTotalSchedulesProperty()
    {
        return auth()->user()
            ->platformAccounts()
            ->withCount('liveSchedules')
            ->get()
            ->sum('live_schedules_count');
    }

    public function getActiveSchedulesProperty()
    {
        return LiveSchedule::whereHas('platformAccount', function ($query) {
            $query->where('user_id', auth()->id());
        })->where('is_active', true)->count();
    }

    public function getRecurringSchedulesProperty()
    {
        return LiveSchedule::whereHas('platformAccount', function ($query) {
            $query->where('user_id', auth()->id());
        })->where('is_recurring', true)->count();
    }

    public function getSessionsThisWeekProperty()
    {
        return auth()->user()->liveSessions()
            ->whereBetween('scheduled_start_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])
            ->count();
    }

    public function getDayName($dayNumber)
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$dayNumber];
    }

    public function getPlatformColor($platformName)
    {
        return match(strtolower($platformName)) {
            'tiktok shop' => 'gray',
            'facebook shop' => 'blue',
            'shopee' => 'orange',
            default => 'purple'
        };
    }
}; ?>

<div class="pb-20 lg:pb-6">
    <!-- Header -->
    <div class="mb-4">
        <flux:heading size="xl">My Schedule</flux:heading>
        <flux:text class="mt-1 text-sm">Your weekly live streaming schedule</flux:text>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-blue-50 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.calendar class="w-4 h-4 text-blue-600" />
                <div>
                    <div class="text-lg font-bold text-blue-900">{{ $this->totalSchedules }}</div>
                    <div class="text-xs text-blue-600">Total</div>
                </div>
            </div>
        </div>
        <div class="bg-green-50 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="w-4 h-4 text-green-600" />
                <div>
                    <div class="text-lg font-bold text-green-900">{{ $this->activeSchedules }}</div>
                    <div class="text-xs text-green-600">Active</div>
                </div>
            </div>
        </div>
        <div class="bg-purple-50 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.arrow-path class="w-4 h-4 text-purple-600" />
                <div>
                    <div class="text-lg font-bold text-purple-900">{{ $this->recurringSchedules }}</div>
                    <div class="text-xs text-purple-600">Recurring</div>
                </div>
            </div>
        </div>
        <div class="bg-indigo-50 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.video-camera class="w-4 h-4 text-indigo-600" />
                <div>
                    <div class="text-lg font-bold text-indigo-900">{{ $this->sessionsThisWeek }}</div>
                    <div class="text-xs text-indigo-600">This Week</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Week Navigation -->
    <flux:card class="mb-4">
        <div class="p-4">
            <div class="flex items-center justify-between gap-3">
                <flux:button variant="ghost" size="sm" wire:click="previousWeek" class="flex-shrink-0">
                    <flux:icon.chevron-left class="w-5 h-5" />
                </flux:button>

                <div class="flex-1 text-center">
                    <div class="text-sm font-semibold text-gray-900">
                        {{ $this->currentWeekStart->format('M d') }} - {{ $this->currentWeekEnd->format('M d, Y') }}
                    </div>
                    @if ($this->isCurrentWeek)
                        <div class="text-xs text-blue-600 mt-0.5">Current Week</div>
                    @endif
                </div>

                <flux:button variant="ghost" size="sm" wire:click="nextWeek" class="flex-shrink-0">
                    <flux:icon.chevron-right class="w-5 h-5" />
                </flux:button>
            </div>

            @if (!$this->isCurrentWeek)
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <flux:button variant="primary" size="sm" wire:click="jumpToToday" class="w-full">
                        <div class="flex items-center justify-center gap-2">
                            <flux:icon.calendar class="w-4 h-4" />
                            <span>Jump to Today</span>
                        </div>
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Schedule Content -->
    <div class="space-y-3">
        @foreach ($this->schedulesByDay as $dayNumber => $schedules)
            @php
                $isToday = $this->isCurrentWeek && $dayNumber === now()->dayOfWeek;
                $dayName = $this->getDayName($dayNumber);
                $dayDate = $this->currentWeekStart->copy()->addDays($dayNumber);
            @endphp

            <flux:card class="{{ $isToday ? 'border-2 border-blue-500 shadow-md' : '' }}">
                <div class="p-4">
                    <!-- Day Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="{{ $isToday ? 'bg-blue-600' : 'bg-gray-200' }} w-12 h-12 rounded-lg flex items-center justify-center">
                                <div class="text-center">
                                    <div class="{{ $isToday ? 'text-white' : 'text-gray-600' }} text-xs font-medium">{{ $dayDate->format('M') }}</div>
                                    <div class="{{ $isToday ? 'text-white' : 'text-gray-900' }} text-lg font-bold leading-none">{{ $dayDate->format('d') }}</div>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-base font-bold {{ $isToday ? 'text-blue-900' : 'text-gray-900' }}">{{ $dayName }}</h3>
                                @if ($isToday)
                                    <p class="text-xs text-blue-600 font-medium">Today</p>
                                @else
                                    <p class="text-xs text-gray-500">{{ $dayDate->format('M d, Y') }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($schedules->count() > 0)
                            <flux:badge variant="filled" :color="$isToday ? 'blue' : 'gray'">
                                {{ $schedules->count() }} {{ Str::plural('session', $schedules->count()) }}
                            </flux:badge>
                        @endif
                    </div>

                    <!-- Schedule Items -->
                    @if ($schedules->count() > 0)
                        <div class="space-y-2">
                            @foreach ($schedules as $schedule)
                                <div class="bg-white border border-gray-200 rounded-lg p-3 hover:shadow-sm transition-all">
                                    <div class="flex items-start gap-3">
                                        <!-- Time -->
                                        <div class="flex-shrink-0">
                                            <div class="bg-gray-50 rounded-lg px-3 py-2 text-center min-w-[70px]">
                                                <div class="text-sm font-bold text-gray-900">
                                                    {{ \Carbon\Carbon::createFromFormat('H:i:s', $schedule->start_time)->format('h:i A') }}
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    {{ \Carbon\Carbon::createFromFormat('H:i:s', $schedule->start_time)->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i:s', $schedule->end_time)) }}m
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $schedule->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $schedule->platformAccount->account_name }}</p>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                <flux:badge variant="outline" :color="$this->getPlatformColor($schedule->platformAccount->platform->name)" size="sm">
                                                    {{ $schedule->platformAccount->platform->name }}
                                                </flux:badge>
                                                @if ($schedule->is_recurring)
                                                    <flux:badge variant="outline" color="purple" size="sm">
                                                        <div class="flex items-center gap-1">
                                                            <flux:icon.arrow-path class="w-3 h-3" />
                                                            <span>Recurring</span>
                                                        </div>
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6">
                            <flux:icon.calendar class="mx-auto w-10 h-10 text-gray-300 mb-2" />
                            <p class="text-sm text-gray-400">No sessions scheduled</p>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>

    <!-- Legend -->
    <flux:card class="mt-6">
        <div class="p-4">
            <div class="flex items-center gap-4 text-xs">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                    <span class="text-gray-600">Active</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                    <span class="text-gray-600">Inactive</span>
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon.arrow-path class="w-3 h-3 text-purple-600" />
                    <span class="text-gray-600">Recurring</span>
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
