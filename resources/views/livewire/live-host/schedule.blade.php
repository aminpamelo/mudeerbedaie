<?php

use App\Models\LiveSchedule;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use Livewire\Volt\Component;

new class extends Component {
    public int $weekOffset = 0;
    public string $activeTab = 'my-schedule';

    // Self-assignment modal properties
    public bool $showAssignmentModal = false;
    public ?int $selectedScheduleId = null;
    public ?int $selectedPlatformId = null;
    public ?int $selectedDayOfWeek = null;
    public ?int $selectedTimeSlotId = null;

    // Days of week in Malay (starting from Saturday)
    public array $daysOfWeek = [
        6 => 'SABTU',
        0 => 'AHAD',
        1 => 'ISNIN',
        2 => 'SELASA',
        3 => 'RABU',
        4 => 'KHAMIS',
        5 => 'JUMAAT',
    ];

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

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
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

    public function getTimeSlotsProperty()
    {
        return LiveTimeSlot::query()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
    }

    public function getPlatformAccountsProperty()
    {
        return PlatformAccount::query()
            ->with(['platform'])
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getSchedulesMapProperty()
    {
        $platformIds = $this->platformAccounts->pluck('id');

        $schedules = LiveSchedule::query()
            ->with(['liveHost'])
            ->whereIn('platform_account_id', $platformIds)
            ->where('is_active', true)
            ->get();

        // Create a map: platform_id-day-start_time => schedule
        $map = [];
        foreach ($schedules as $schedule) {
            $key = $schedule->platform_account_id . '-' . $schedule->day_of_week . '-' . $schedule->start_time;
            $map[$key] = $schedule;
        }

        return $map;
    }

    public function getScheduleFor($platformId, $dayOfWeek, $timeSlot)
    {
        $key = $platformId . '-' . $dayOfWeek . '-' . $timeSlot->start_time;
        return $this->schedulesMap[$key] ?? null;
    }

    public function getMySchedulesProperty()
    {
        return LiveSchedule::where('live_host_id', auth()->id())
            ->where('is_active', true)
            ->with(['platformAccount.platform'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');
    }

    public function getSchedulesByDayProperty()
    {
        $schedules = $this->mySchedules;

        // Ensure all days are present even if empty
        $days = collect(range(0, 6))->mapWithKeys(function ($day) use ($schedules) {
            return [$day => $schedules->get($day, collect())];
        });

        return $days;
    }

    public function getTotalSchedulesProperty()
    {
        return LiveSchedule::where('live_host_id', auth()->id())
            ->where('is_active', true)
            ->count();
    }

    public function getActiveSchedulesProperty()
    {
        return LiveSchedule::where('live_host_id', auth()->id())
            ->where('is_active', true)
            ->count();
    }

    public function getRecurringSchedulesProperty()
    {
        return LiveSchedule::where('live_host_id', auth()->id())
            ->where('is_recurring', true)
            ->count();
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

    public function getAvailableSlotsCountProperty(): int
    {
        $count = 0;
        foreach ($this->platformAccounts as $platform) {
            foreach ($this->daysOfWeek as $dayIndex => $dayName) {
                foreach ($this->timeSlots as $timeSlot) {
                    $schedule = $this->getScheduleFor($platform->id, $dayIndex, $timeSlot);
                    if (!$schedule || !$schedule->live_host_id) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    public function openAssignmentModal($platformId, $dayOfWeek, $timeSlotId, $scheduleId = null): void
    {
        $this->selectedPlatformId = $platformId;
        $this->selectedDayOfWeek = $dayOfWeek;
        $this->selectedTimeSlotId = $timeSlotId;
        $this->selectedScheduleId = $scheduleId;
        $this->showAssignmentModal = true;
    }

    public function assignToSelf(): void
    {
        $timeSlot = LiveTimeSlot::find($this->selectedTimeSlotId);
        if (!$timeSlot) {
            session()->flash('error', 'Time slot not found.');
            return;
        }

        if ($this->selectedScheduleId) {
            // Update existing schedule
            $schedule = LiveSchedule::find($this->selectedScheduleId);
            if ($schedule && !$schedule->live_host_id) {
                $schedule->update([
                    'live_host_id' => auth()->id(),
                ]);
                session()->flash('success', 'You have been assigned to this slot successfully!');
            } else {
                session()->flash('error', 'This slot is no longer available.');
            }
        } else {
            // Create new schedule
            LiveSchedule::create([
                'platform_account_id' => $this->selectedPlatformId,
                'day_of_week' => $this->selectedDayOfWeek,
                'start_time' => $timeSlot->start_time,
                'end_time' => $timeSlot->end_time,
                'is_recurring' => true,
                'is_active' => true,
                'live_host_id' => auth()->id(),
            ]);
            session()->flash('success', 'You have been assigned to this slot successfully!');
        }

        $this->closeModal();
    }

    public function unassignSelf(int $scheduleId): void
    {
        $schedule = LiveSchedule::find($scheduleId);

        if ($schedule && $schedule->live_host_id === auth()->id()) {
            $schedule->update(['live_host_id' => null]);
            session()->flash('success', 'You have been removed from this slot.');
        } else {
            session()->flash('error', 'Unable to remove assignment.');
        }
    }

    public function closeModal(): void
    {
        $this->showAssignmentModal = false;
        $this->selectedScheduleId = null;
        $this->selectedPlatformId = null;
        $this->selectedDayOfWeek = null;
        $this->selectedTimeSlotId = null;
    }

    public function getDayName($dayNumber)
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$dayNumber];
    }

    public function getDayNameMs($dayNumber)
    {
        return ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'][$dayNumber];
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
    <div class="mb-6">
        <flux:heading size="xl">My Schedule</flux:heading>
        <flux:text class="mt-2">Your weekly live streaming schedule</flux:text>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <div class="flex items-center gap-2 text-green-800 dark:text-green-300">
                <flux:icon.check-circle class="w-5 h-5" />
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-center gap-2 text-red-800 dark:text-red-300">
                <flux:icon.exclamation-circle class="w-5 h-5" />
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-800/50 rounded-lg">
                    <flux:icon.calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $this->totalSchedules }}</div>
                    <div class="text-xs text-blue-600 dark:text-blue-400 font-medium">My Slots</div>
                </div>
            </div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-100 dark:bg-green-800/50 rounded-lg">
                    <flux:icon.plus-circle class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $this->availableSlotsCount }}</div>
                    <div class="text-xs text-green-600 dark:text-green-400 font-medium">Available</div>
                </div>
            </div>
        </div>
        <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 dark:bg-purple-800/50 rounded-lg">
                    <flux:icon.arrow-path class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ $this->recurringSchedules }}</div>
                    <div class="text-xs text-purple-600 dark:text-purple-400 font-medium">Recurring</div>
                </div>
            </div>
        </div>
        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-800/50 rounded-lg">
                    <flux:icon.video-camera class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ $this->sessionsThisWeek }}</div>
                    <div class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">This Week</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="flex gap-6" aria-label="Tabs">
            <button
                wire:click="setActiveTab('my-schedule')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'my-schedule' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }}"
            >
                My Schedule
                @if($this->totalSchedules > 0)
                    <span class="ml-2 px-2.5 py-0.5 text-xs rounded-full {{ $activeTab === 'my-schedule' ? 'bg-blue-100 dark:bg-blue-800/50 text-blue-600 dark:text-blue-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $this->totalSchedules }}
                    </span>
                @endif
            </button>
            <button
                wire:click="setActiveTab('available-slots')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'available-slots' ? 'border-green-500 text-green-600 dark:text-green-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }}"
            >
                Available Slots
                @if($this->availableSlotsCount > 0)
                    <span class="ml-2 px-2.5 py-0.5 text-xs rounded-full {{ $activeTab === 'available-slots' ? 'bg-green-100 dark:bg-green-800/50 text-green-600 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $this->availableSlotsCount }}
                    </span>
                @endif
            </button>
        </nav>
    </div>

    <!-- My Schedule Tab Content -->
    @if($activeTab === 'my-schedule')
        <!-- Week Navigation -->
        <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-sm mb-6">
            <div class="p-4">
                <div class="flex items-center justify-between gap-3">
                    <flux:button variant="ghost" size="sm" wire:click="previousWeek" class="flex-shrink-0">
                        <flux:icon.chevron-left class="w-5 h-5" />
                    </flux:button>

                    <div class="flex-1 text-center">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $this->currentWeekStart->format('M d') }} - {{ $this->currentWeekEnd->format('M d, Y') }}
                        </div>
                        @if ($this->isCurrentWeek)
                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-0.5 font-medium">Current Week</div>
                        @endif
                    </div>

                    <flux:button variant="ghost" size="sm" wire:click="nextWeek" class="flex-shrink-0">
                        <flux:icon.chevron-right class="w-5 h-5" />
                    </flux:button>
                </div>

                @if (!$this->isCurrentWeek)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                        <flux:button variant="primary" size="sm" wire:click="jumpToToday" class="w-full">
                            <flux:icon.calendar class="w-4 h-4 mr-2" />
                            Jump to Today
                        </flux:button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Schedule Content -->
        <div class="space-y-4">
            @foreach ($this->schedulesByDay as $dayNumber => $schedules)
                @php
                    $isToday = $this->isCurrentWeek && $dayNumber === now()->dayOfWeek;
                    $dayName = $this->getDayName($dayNumber);
                    $dayDate = $this->currentWeekStart->copy()->addDays($dayNumber);
                @endphp

                <div class="bg-white dark:bg-zinc-800 border {{ $isToday ? 'border-blue-500 dark:border-blue-400 ring-2 ring-blue-500/20' : 'border-gray-200 dark:border-zinc-700' }} rounded-xl shadow-sm overflow-hidden">
                    <div class="p-5">
                        <!-- Day Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="{{ $isToday ? 'bg-blue-600 dark:bg-blue-500' : 'bg-gray-100 dark:bg-zinc-700' }} w-14 h-14 rounded-xl flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="{{ $isToday ? 'text-blue-100' : 'text-gray-500 dark:text-gray-400' }} text-xs font-medium">{{ $dayDate->format('M') }}</div>
                                        <div class="{{ $isToday ? 'text-white' : 'text-gray-900 dark:text-white' }} text-xl font-bold leading-none">{{ $dayDate->format('d') }}</div>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold {{ $isToday ? 'text-blue-900 dark:text-blue-100' : 'text-gray-900 dark:text-white' }}">{{ $dayName }}</h3>
                                    @if ($isToday)
                                        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Today</p>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $dayDate->format('M d, Y') }}</p>
                                    @endif
                                </div>
                            </div>
                            @if ($schedules->count() > 0)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $isToday ? 'bg-blue-100 dark:bg-blue-800/50 text-blue-700 dark:text-blue-300' : 'bg-gray-100 dark:bg-zinc-700 text-gray-700 dark:text-gray-300' }}">
                                    {{ $schedules->count() }} {{ Str::plural('session', $schedules->count()) }}
                                </span>
                            @endif
                        </div>

                        <!-- Schedule Items -->
                        @if ($schedules->count() > 0)
                            <div class="space-y-3">
                                @foreach ($schedules as $schedule)
                                    <div class="bg-gray-50 dark:bg-zinc-700/50 border border-gray-200 dark:border-zinc-600 rounded-xl p-4 hover:shadow-md dark:hover:shadow-zinc-900/30 transition-all">
                                        <div class="flex items-start gap-4">
                                            <!-- Time -->
                                            <div class="flex-shrink-0">
                                                <div class="bg-white dark:bg-zinc-600 rounded-lg px-4 py-2 text-center min-w-[80px] shadow-sm">
                                                    <div class="text-sm font-bold text-gray-900 dark:text-white">
                                                        {{ \Carbon\Carbon::parse($schedule->start_time)->format('h:i A') }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                        {{ \Carbon\Carbon::parse($schedule->start_time)->diffInMinutes(\Carbon\Carbon::parse($schedule->end_time)) }} min
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Details -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $schedule->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                                    <p class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ $schedule->platformAccount->name ?? $schedule->platformAccount->account_name }}</p>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                                    <flux:badge variant="outline" :color="$this->getPlatformColor($schedule->platformAccount->platform->name)" size="sm">
                                                        {{ $schedule->platformAccount->platform->name }}
                                                    </flux:badge>
                                                    @if ($schedule->is_recurring)
                                                        <flux:badge variant="outline" color="purple" size="sm">
                                                            <flux:icon.arrow-path class="w-3 h-3 mr-1" />
                                                            Recurring
                                                        </flux:badge>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Unassign Button -->
                                            <div class="flex-shrink-0">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="unassignSelf({{ $schedule->id }})"
                                                    wire:confirm="Are you sure you want to remove yourself from this slot?"
                                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                >
                                                    <flux:icon.x-mark class="w-5 h-5" />
                                                </flux:button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <div class="p-3 bg-gray-100 dark:bg-zinc-700 rounded-full w-14 h-14 mx-auto mb-3 flex items-center justify-center">
                                    <flux:icon.calendar class="w-7 h-7 text-gray-400 dark:text-zinc-500" />
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">No sessions scheduled</p>
                                <flux:button variant="ghost" size="sm" wire:click="setActiveTab('available-slots')">
                                    Browse available slots
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Legend -->
        <div class="mt-6 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl p-4">
            <div class="flex flex-wrap items-center gap-6 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    <span class="text-gray-600 dark:text-gray-400">Active</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                    <span class="text-gray-600 dark:text-gray-400">Inactive</span>
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon.arrow-path class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                    <span class="text-gray-600 dark:text-gray-400">Recurring</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Available Slots Tab Content (Spreadsheet Style) -->
    @if($activeTab === 'available-slots')
        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-center gap-2 text-blue-800 dark:text-blue-300">
                <flux:icon.information-circle class="w-5 h-5" />
                <span class="text-sm">Click on any available slot (marked with green +) to assign yourself.</span>
            </div>
        </div>

        @if($this->timeSlots->isEmpty())
            <!-- No Time Slots Warning -->
            <div class="text-center py-16 bg-yellow-50 dark:bg-yellow-900/20 rounded-xl border border-yellow-200 dark:border-yellow-800">
                <div class="p-4 bg-yellow-100 dark:bg-yellow-800/30 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <flux:icon.exclamation-triangle class="w-8 h-8 text-yellow-600 dark:text-yellow-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Time Slots Available</h3>
                <p class="text-gray-500 dark:text-gray-400">Time slots have not been configured yet. Please contact the administrator.</p>
            </div>
        @elseif($this->platformAccounts->isEmpty())
            <!-- No Platform Accounts -->
            <div class="text-center py-16 bg-gray-50 dark:bg-zinc-800/50 rounded-xl border border-gray-200 dark:border-zinc-700">
                <div class="p-4 bg-gray-100 dark:bg-zinc-700 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <flux:icon.calendar-days class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Platform Accounts</h3>
                <p class="text-gray-500 dark:text-gray-400">No platform accounts are available for scheduling.</p>
            </div>
        @else
            <!-- Legend -->
            <div class="mb-4 flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-center gap-2">
                    <div class="w-5 h-5 bg-green-100 dark:bg-green-800/50 border-2 border-dashed border-green-400 dark:border-green-500 rounded flex items-center justify-center">
                        <span class="text-green-600 dark:text-green-400 text-xs">+</span>
                    </div>
                    <span>Available (Click to assign)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-5 h-5 bg-blue-500 rounded"></div>
                    <span>Your slot</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-5 h-5 bg-gray-200 dark:bg-zinc-600 rounded"></div>
                    <span>Taken by others</span>
                </div>
            </div>

            <!-- Schedule Grid (Spreadsheet Style) -->
            <div class="overflow-x-auto pb-4">
                <div class="flex gap-4 min-w-max">
                    @foreach($this->platformAccounts as $platformAccount)
                        @php
                            $headerColors = [
                                0 => 'bg-green-500',
                                1 => 'bg-orange-500',
                                2 => 'bg-blue-500',
                                3 => 'bg-purple-500',
                                4 => 'bg-pink-500',
                            ];
                            $headerColor = $headerColors[$loop->index % 5];
                        @endphp

                        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-lg overflow-hidden min-w-[340px] border border-gray-200 dark:border-zinc-700">
                            <!-- Platform Header -->
                            <div class="{{ $headerColor }} px-4 py-3">
                                <h2 class="text-white font-bold text-center text-sm uppercase tracking-wide">
                                    {{ $platformAccount->name }}
                                </h2>
                            </div>

                            <!-- Column Headers -->
                            <div class="grid grid-cols-3 bg-gray-100 dark:bg-zinc-700 border-b border-gray-200 dark:border-zinc-600">
                                <div class="px-3 py-2.5 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                    Hari
                                </div>
                                <div class="px-3 py-2.5 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                    Masa
                                </div>
                                <div class="px-3 py-2.5 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                    Host
                                </div>
                            </div>

                            <!-- Schedule Rows -->
                            <div class="divide-y divide-gray-100 dark:divide-zinc-700 max-h-[500px] overflow-y-auto">
                                @foreach($this->daysOfWeek as $dayIndex => $dayName)
                                    @foreach($this->timeSlots as $slotIndex => $timeSlot)
                                        @php
                                            $schedule = $this->getScheduleFor($platformAccount->id, $dayIndex, $timeSlot);
                                            $host = $schedule?->liveHost;
                                            $isMySlot = $host && $host->id === auth()->id();
                                            $isAvailable = !$host;
                                            $isFirstSlotOfDay = $slotIndex === 0;
                                        @endphp

                                        @if($isAvailable)
                                            <!-- Available Slot - Clickable -->
                                            <div
                                                class="grid grid-cols-3 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors cursor-pointer border-l-4 border-green-400 dark:border-green-500"
                                                wire:click="openAssignmentModal({{ $platformAccount->id }}, {{ $dayIndex }}, {{ $timeSlot->id }}, {{ $schedule?->id ?? 'null' }})"
                                            >
                                                <!-- Day Column -->
                                                <div class="px-3 py-2.5 text-xs font-medium text-gray-700 dark:text-gray-300 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    @if($isFirstSlotOfDay)
                                                        {{ $dayName }}
                                                    @endif
                                                </div>

                                                <!-- Time Column -->
                                                <div class="px-3 py-2.5 text-xs text-gray-600 dark:text-gray-400 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    {{ $timeSlot->time_range }}
                                                </div>

                                                <!-- Host Column - Available -->
                                                <div class="px-2 py-2 flex items-center justify-center">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-green-100 dark:bg-green-800/50 text-green-700 dark:text-green-300 border-2 border-dashed border-green-400 dark:border-green-500">
                                                        <flux:icon.plus class="w-3 h-3 mr-1" />
                                                        Available
                                                    </span>
                                                </div>
                                            </div>
                                        @elseif($isMySlot)
                                            <!-- My Slot -->
                                            <div class="grid grid-cols-3 bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-500">
                                                <!-- Day Column -->
                                                <div class="px-3 py-2.5 text-xs font-medium text-gray-700 dark:text-gray-300 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    @if($isFirstSlotOfDay)
                                                        {{ $dayName }}
                                                    @endif
                                                </div>

                                                <!-- Time Column -->
                                                <div class="px-3 py-2.5 text-xs text-gray-600 dark:text-gray-400 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    {{ $timeSlot->time_range }}
                                                </div>

                                                <!-- Host Column - Me -->
                                                <div class="px-2 py-2 flex items-center justify-center">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-blue-500 text-white">
                                                        <flux:icon.check class="w-3 h-3 mr-1" />
                                                        You
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <!-- Taken by Others -->
                                            <div class="grid grid-cols-3 bg-gray-50 dark:bg-zinc-800">
                                                <!-- Day Column -->
                                                <div class="px-3 py-2.5 text-xs font-medium text-gray-700 dark:text-gray-300 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    @if($isFirstSlotOfDay)
                                                        {{ $dayName }}
                                                    @endif
                                                </div>

                                                <!-- Time Column -->
                                                <div class="px-3 py-2.5 text-xs text-gray-600 dark:text-gray-400 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    {{ $timeSlot->time_range }}
                                                </div>

                                                <!-- Host Column - Others -->
                                                <div class="px-2 py-2 flex items-center justify-center">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium truncate max-w-full"
                                                        style="background-color: {{ $host->host_color ?? '#e5e7eb' }}; color: {{ $host->host_text_color ?? '#374151' }};"
                                                        title="{{ $host->name }}"
                                                    >
                                                        {{ Str::limit($host->name, 10) }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <!-- Self-Assignment Modal -->
    <flux:modal wire:model="showAssignmentModal" class="max-w-md">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-green-100 dark:bg-green-800/50 rounded-lg">
                    <flux:icon.plus-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:heading size="lg">Assign Yourself</flux:heading>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Confirm slot assignment</p>
                </div>
            </div>

            @if($selectedTimeSlotId && $selectedDayOfWeek !== null && $selectedPlatformId)
                @php
                    $timeSlot = \App\Models\LiveTimeSlot::find($selectedTimeSlotId);
                    $platform = \App\Models\PlatformAccount::find($selectedPlatformId);
                    $dayName = $daysOfWeek[$selectedDayOfWeek] ?? '';
                @endphp

                <div class="mb-6 p-4 bg-gray-50 dark:bg-zinc-700/50 rounded-xl border border-gray-200 dark:border-zinc-600 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white dark:bg-zinc-600 rounded-lg shadow-sm">
                            <flux:icon.building-storefront class="w-4 h-4 text-gray-600 dark:text-gray-300" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Platform</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $platform?->name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white dark:bg-zinc-600 rounded-lg shadow-sm">
                            <flux:icon.calendar class="w-4 h-4 text-gray-600 dark:text-gray-300" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Day</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $dayName }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white dark:bg-zinc-600 rounded-lg shadow-sm">
                            <flux:icon.clock class="w-4 h-4 text-gray-600 dark:text-gray-300" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Time</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $timeSlot?->time_range }}</p>
                        </div>
                    </div>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    This slot will be added to your weekly recurring schedule.
                </p>
            @endif

            <div class="flex gap-3">
                <flux:button variant="primary" wire:click="assignToSelf" class="flex-1">
                    <flux:icon.check class="w-4 h-4 mr-2" />
                    Confirm Assignment
                </flux:button>
                <flux:button variant="ghost" wire:click="closeModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
