<?php

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public int $weekOffset = 0;
    public string $activeTab = 'assigned'; // 'assigned' or 'self-schedule'

    // Self-schedule modal properties
    public bool $showScheduleModal = false;
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

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

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
        // Start week from Sunday (Carbon::SUNDAY = 0)
        return now()->addWeeks($this->weekOffset)->startOfWeek(Carbon::SUNDAY);
    }

    public function getCurrentWeekEndProperty()
    {
        // End week on Saturday
        return now()->addWeeks($this->weekOffset)->endOfWeek(Carbon::SATURDAY);
    }

    public function getIsCurrentWeekProperty(): bool
    {
        return $this->weekOffset === 0;
    }

    // Get assigned schedules from admin (from LiveSchedule table - used by admin calendar)
    // Only shows schedules created by admin (not by the host themselves)
    public function getSchedulesByDayProperty()
    {
        // Get schedules from LiveSchedule where this user is assigned as host
        // Only include schedules created by admin (created_by != live_host_id or created_by is null for legacy)
        $schedules = LiveSchedule::query()
            ->where('live_host_id', auth()->id())
            ->where('is_active', true)
            ->where(function ($query) {
                // Admin assigned = created_by is null (legacy) OR created_by is different from live_host_id
                $query->whereNull('created_by')
                    ->orWhereColumn('created_by', '!=', 'live_host_id');
            })
            ->with(['platformAccount.platform'])
            ->get()
            ->groupBy('day_of_week');

        $days = collect(range(0, 6))->mapWithKeys(function ($day) use ($schedules) {
            $daySchedules = $schedules->get($day, collect());
            return [$day => $daySchedules->sortBy('start_time')];
        });

        return $days;
    }

    // Get time slots for self-schedule
    public function getTimeSlotsProperty()
    {
        return LiveTimeSlot::query()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
    }

    // Get platform accounts assigned to this live host
    public function getMyPlatformAccountsProperty()
    {
        return auth()->user()
            ->assignedPlatformAccounts()
            ->with('platform')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    // Get self-scheduled items (from LiveSchedule where live_host_id = current user)
    public function getSelfSchedulesMapProperty()
    {
        $platformIds = $this->myPlatformAccounts->pluck('id');

        $schedules = LiveSchedule::query()
            ->whereIn('platform_account_id', $platformIds)
            ->where('live_host_id', auth()->id())
            ->where('is_active', true)
            ->get();

        $map = [];
        foreach ($schedules as $schedule) {
            $key = $schedule->platform_account_id . '-' . $schedule->day_of_week . '-' . $schedule->start_time;
            $map[$key] = $schedule;
        }

        return $map;
    }

    public function getSelfScheduleFor($platformId, $dayOfWeek, $timeSlot)
    {
        $key = $platformId . '-' . $dayOfWeek . '-' . $timeSlot->start_time;
        return $this->selfSchedulesMap[$key] ?? null;
    }

    // Open modal to select/deselect time slot
    public function openScheduleModal($platformId, $dayOfWeek, $timeSlotId)
    {
        $this->selectedPlatformId = $platformId;
        $this->selectedDayOfWeek = $dayOfWeek;
        $this->selectedTimeSlotId = $timeSlotId;
        $this->showScheduleModal = true;
    }

    // Toggle self-schedule
    public function toggleSchedule()
    {
        $timeSlot = LiveTimeSlot::find($this->selectedTimeSlotId);
        if (!$timeSlot) return;

        $existingSchedule = LiveSchedule::where('platform_account_id', $this->selectedPlatformId)
            ->where('day_of_week', $this->selectedDayOfWeek)
            ->where('start_time', $timeSlot->start_time)
            ->where('live_host_id', auth()->id())
            ->first();

        if ($existingSchedule) {
            // Remove self-assignment and delete future sessions
            $this->deleteFutureSessions($existingSchedule);
            $existingSchedule->update(['live_host_id' => null]);
        } else {
            // Check if slot exists without host
            $schedule = LiveSchedule::where('platform_account_id', $this->selectedPlatformId)
                ->where('day_of_week', $this->selectedDayOfWeek)
                ->where('start_time', $timeSlot->start_time)
                ->first();

            if ($schedule) {
                // Update existing schedule
                $schedule->update(['live_host_id' => auth()->id()]);
                $this->generateSessionsFromSchedule($schedule);
            } else {
                // Create new schedule with self as host (self-scheduled)
                $schedule = LiveSchedule::create([
                    'platform_account_id' => $this->selectedPlatformId,
                    'day_of_week' => $this->selectedDayOfWeek,
                    'start_time' => $timeSlot->start_time,
                    'end_time' => $timeSlot->end_time,
                    'is_recurring' => true,
                    'is_active' => true,
                    'live_host_id' => auth()->id(),
                    'created_by' => auth()->id(), // Self-scheduled: created_by = live_host_id
                ]);
                $this->generateSessionsFromSchedule($schedule);
            }
        }

        $this->closeModal();
    }

    // Generate sessions for the next 7 days from a schedule
    protected function generateSessionsFromSchedule(LiveSchedule $schedule): void
    {
        $daysAhead = 7;
        $startDate = now()->startOfDay();
        $endDate = now()->addDays($daysAhead);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->dayOfWeek === $schedule->day_of_week) {
                $scheduledTime = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->start_time);

                // Only generate if it's in the future
                if ($scheduledTime->isFuture()) {
                    // Check if session already exists
                    $exists = LiveSession::where('platform_account_id', $schedule->platform_account_id)
                        ->where('scheduled_start_at', $scheduledTime)
                        ->exists();

                    if (!$exists) {
                        LiveSession::create([
                            'platform_account_id' => $schedule->platform_account_id,
                            'live_schedule_id' => $schedule->id,
                            'live_host_id' => $schedule->live_host_id,
                            'title' => 'Live Stream - ' . $schedule->day_name,
                            'scheduled_start_at' => $scheduledTime,
                            'status' => 'scheduled',
                        ]);
                    }
                }
            }
        }
    }

    // Delete future scheduled sessions when host removes their schedule
    protected function deleteFutureSessions(LiveSchedule $schedule): void
    {
        LiveSession::where('live_schedule_id', $schedule->id)
            ->where('live_host_id', auth()->id())
            ->where('status', 'scheduled')
            ->where('scheduled_start_at', '>', now())
            ->delete();
    }

    public function closeModal()
    {
        $this->showScheduleModal = false;
        $this->selectedPlatformId = null;
        $this->selectedDayOfWeek = null;
        $this->selectedTimeSlotId = null;
    }

    // Stats for admin-assigned schedules only
    public function getTotalSchedulesProperty()
    {
        return LiveSchedule::where('live_host_id', auth()->id())
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('created_by')
                    ->orWhereColumn('created_by', '!=', 'live_host_id');
            })
            ->count();
    }

    public function getActiveSchedulesProperty()
    {
        return LiveSchedule::where('live_host_id', auth()->id())
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('created_by')
                    ->orWhereColumn('created_by', '!=', 'live_host_id');
            })
            ->count();
    }

    public function getRecurringSchedulesProperty()
    {
        return LiveSchedule::where('live_host_id', auth()->id())
            ->where('is_recurring', true)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('created_by')
                    ->orWhereColumn('created_by', '!=', 'live_host_id');
            })
            ->count();
    }

    public function getSessionsThisWeekProperty()
    {
        return auth()->user()->hostedSessions()
            ->whereBetween('scheduled_start_at', [
                now()->startOfWeek(Carbon::SUNDAY),
                now()->endOfWeek(Carbon::SATURDAY)
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

    public function getStatusColor($status)
    {
        return match($status) {
            'scheduled' => 'blue',
            'confirmed' => 'green',
            'in_progress' => 'yellow',
            'completed' => 'gray',
            'cancelled' => 'red',
            default => 'gray'
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
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.calendar class="w-4 h-4 text-blue-600" />
                <div>
                    <div class="text-lg font-bold text-blue-900 dark:text-blue-100">{{ $this->totalSchedules }}</div>
                    <div class="text-xs text-blue-600 dark:text-blue-400">Total</div>
                </div>
            </div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="w-4 h-4 text-green-600" />
                <div>
                    <div class="text-lg font-bold text-green-900 dark:text-green-100">{{ $this->activeSchedules }}</div>
                    <div class="text-xs text-green-600 dark:text-green-400">Active</div>
                </div>
            </div>
        </div>
        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.arrow-path class="w-4 h-4 text-purple-600" />
                <div>
                    <div class="text-lg font-bold text-purple-900 dark:text-purple-100">{{ $this->recurringSchedules }}</div>
                    <div class="text-xs text-purple-600 dark:text-purple-400">Recurring</div>
                </div>
            </div>
        </div>
        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <flux:icon.video-camera class="w-4 h-4 text-indigo-600" />
                <div>
                    <div class="text-lg font-bold text-indigo-900 dark:text-indigo-100">{{ $this->sessionsThisWeek }}</div>
                    <div class="text-xs text-indigo-600 dark:text-indigo-400">This Week</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-4 border-b border-gray-200 dark:border-zinc-700">
        <nav class="flex gap-4">
            <button
                wire:click="setTab('assigned')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'assigned' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <flux:icon.calendar-days class="w-4 h-4" />
                    Admin Assigned
                </div>
            </button>
            <button
                wire:click="setTab('self-schedule')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'self-schedule' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <flux:icon.pencil-square class="w-4 h-4" />
                    Self Schedule
                </div>
            </button>
        </nav>
    </div>

    @if ($activeTab === 'assigned')
        <!-- Week Navigation -->
        <flux:card class="mb-4">
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
                            <div class="text-xs text-blue-600 mt-0.5">Current Week</div>
                        @endif
                    </div>

                    <flux:button variant="ghost" size="sm" wire:click="nextWeek" class="flex-shrink-0">
                        <flux:icon.chevron-right class="w-5 h-5" />
                    </flux:button>
                </div>

                @if (!$this->isCurrentWeek)
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-zinc-700">
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

        <!-- Schedule Content (Admin Assigned) -->
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
                                <div class="{{ $isToday ? 'bg-blue-600' : 'bg-gray-200 dark:bg-zinc-700' }} w-12 h-12 rounded-lg flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="{{ $isToday ? 'text-white' : 'text-gray-600 dark:text-gray-400' }} text-xs font-medium">{{ $dayDate->format('M') }}</div>
                                        <div class="{{ $isToday ? 'text-white' : 'text-gray-900 dark:text-white' }} text-lg font-bold leading-none">{{ $dayDate->format('d') }}</div>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold {{ $isToday ? 'text-blue-900 dark:text-blue-100' : 'text-gray-900 dark:text-white' }}">{{ $dayName }}</h3>
                                    @if ($isToday)
                                        <p class="text-xs text-blue-600 font-medium">Today</p>
                                    @else
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $dayDate->format('M d, Y') }}</p>
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
                                    <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-3 hover:shadow-sm transition-all">
                                        <div class="flex items-start gap-3">
                                            <!-- Time -->
                                            <div class="flex-shrink-0">
                                                <div class="bg-gray-50 dark:bg-zinc-700 rounded-lg px-3 py-2 text-center min-w-[70px]">
                                                    @if ($schedule->start_time)
                                                        <div class="text-sm font-bold text-gray-900 dark:text-white">
                                                            {{ \Carbon\Carbon::createFromFormat('H:i:s', $schedule->start_time)->format('h:i A') }}
                                                        </div>
                                                        @if ($schedule->end_time)
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                                {{ \Carbon\Carbon::createFromFormat('H:i:s', $schedule->start_time)->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i:s', $schedule->end_time)) }}m
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="text-sm font-bold text-gray-900 dark:text-white">TBD</div>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Details -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $schedule->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                        {{ $schedule->platformAccount?->name ?? 'Unknown Platform' }}
                                                    </p>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                                    @if ($schedule->platformAccount?->platform)
                                                        <flux:badge variant="outline" :color="$this->getPlatformColor($schedule->platformAccount->platform->name)" size="sm">
                                                            {{ $schedule->platformAccount->platform->name }}
                                                        </flux:badge>
                                                    @endif
                                                    @if ($schedule->is_recurring)
                                                        <flux:badge variant="outline" color="purple" size="sm">
                                                            <div class="flex items-center gap-1">
                                                                <flux:icon.arrow-path class="w-3 h-3" />
                                                                <span>Recurring</span>
                                                            </div>
                                                        </flux:badge>
                                                    @endif
                                                    <flux:badge variant="outline" color="blue" size="sm">
                                                        Admin Assigned
                                                    </flux:badge>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <flux:icon.calendar class="mx-auto w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" />
                                <p class="text-sm text-gray-400 dark:text-gray-500">No sessions scheduled</p>
                            </div>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        <!-- Legend -->
        <flux:card class="mt-6">
            <div class="p-4">
                <div class="flex flex-wrap items-center gap-4 text-xs">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">Scheduled</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">Confirmed</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">In Progress</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon.arrow-path class="w-3 h-3 text-purple-600" />
                        <span class="text-gray-600 dark:text-gray-400">Recurring</span>
                    </div>
                </div>
            </div>
        </flux:card>

    @else
        <!-- Self Schedule Tab -->
        @if($this->myPlatformAccounts->isEmpty())
            <div class="text-center py-12 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <flux:icon name="building-storefront" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Platform Accounts Assigned</h3>
                <p class="text-gray-500 dark:text-gray-400">Please contact admin to assign you to platform accounts.</p>
            </div>
        @else
            <div class="mb-4">
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    Click on a time slot to mark yourself as available for that slot. Admin will be notified of your availability.
                </flux:text>
            </div>

            <!-- Schedule Grid -->
            <div class="overflow-x-auto">
                <div class="flex gap-4 min-w-max pb-4">
                    @foreach($this->myPlatformAccounts as $platformAccount)
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

                        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-lg overflow-hidden min-w-[380px] border border-gray-200 dark:border-zinc-700">
                            <!-- Platform Header -->
                            <div class="{{ $headerColor }} px-4 py-3">
                                <h2 class="text-white font-bold text-center text-lg uppercase tracking-wide">
                                    {{ $platformAccount->name }}
                                </h2>
                            </div>

                            <!-- Column Headers -->
                            <div class="grid grid-cols-3 bg-gray-100 dark:bg-zinc-700 border-b border-gray-200 dark:border-zinc-600">
                                <div class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                    Hari
                                </div>
                                <div class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                    Masa
                                </div>
                                <div class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                    Status
                                </div>
                            </div>

                            <!-- Schedule Rows -->
                            <div class="divide-y divide-gray-100 dark:divide-zinc-700 max-h-[600px] overflow-y-auto">
                                @if($this->timeSlots->isEmpty())
                                    <div class="text-center py-8 px-4">
                                        <flux:icon name="clock" class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Time slots belum dikonfigurasi</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Sila hubungi admin</p>
                                    </div>
                                @else
                                    @foreach($this->daysOfWeek as $dayIndex => $dayName)
                                        @foreach($this->timeSlots as $slotIndex => $timeSlot)
                                            @php
                                                $schedule = $this->getSelfScheduleFor($platformAccount->id, $dayIndex, $timeSlot);
                                                $isMySchedule = $schedule && $schedule->live_host_id === auth()->id();
                                                $isFirstSlotOfDay = $slotIndex === 0;
                                            @endphp

                                            <div
                                                class="grid grid-cols-3 hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors cursor-pointer {{ $isMySchedule ? 'bg-green-50 dark:bg-green-900/20' : '' }}"
                                                wire:click="openScheduleModal({{ $platformAccount->id }}, {{ $dayIndex }}, {{ $timeSlot->id }})"
                                            >
                                                <!-- Day Column -->
                                                <div class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    @if($isFirstSlotOfDay)
                                                        {{ $dayName }}
                                                    @endif
                                                </div>

                                                <!-- Time Column -->
                                                <div class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                                    {{ $timeSlot->time_range }}
                                                </div>

                                                <!-- Status Column -->
                                                <div class="px-2 py-1.5 flex items-center justify-center">
                                                    @if($isMySchedule)
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-green-500 text-white">
                                                            <flux:icon name="check" class="w-3 h-3 mr-1" />
                                                            Selected
                                                        </span>
                                                    @else
                                                        <span class="text-gray-300 dark:text-zinc-500 text-xs">-</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Legend for Self Schedule -->
            <flux:card class="mt-6">
                <div class="p-4">
                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded bg-green-500"></div>
                            <span class="text-gray-600 dark:text-gray-400">Selected (Your Availability)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded bg-gray-200 dark:bg-zinc-600"></div>
                            <span class="text-gray-600 dark:text-gray-400">Available Slot</span>
                        </div>
                    </div>
                </div>
            </flux:card>
        @endif
    @endif

    <!-- Self Schedule Modal -->
    <flux:modal wire:model="showScheduleModal" class="max-w-sm">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Schedule Availability</flux:heading>

            @if($selectedTimeSlotId && $selectedDayOfWeek !== null)
                @php
                    $timeSlot = \App\Models\LiveTimeSlot::find($selectedTimeSlotId);
                    $platform = \App\Models\PlatformAccount::find($selectedPlatformId);
                    $dayName = $daysOfWeek[$selectedDayOfWeek] ?? '';
                    $existingSchedule = $timeSlot ? $this->getSelfScheduleFor($selectedPlatformId, $selectedDayOfWeek, $timeSlot) : null;
                    $isMySchedule = $existingSchedule && $existingSchedule->live_host_id === auth()->id();
                @endphp

                <div class="mb-4 p-3 bg-gray-50 dark:bg-zinc-700 rounded-lg">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Platform:</strong> {{ $platform?->name }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Day:</strong> {{ $dayName }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Time:</strong> {{ $timeSlot?->time_range }}
                    </div>
                </div>

                @if($isMySchedule)
                    <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg mb-4">
                        <div class="flex items-center gap-2">
                            <flux:icon name="check-circle" class="w-5 h-5 text-green-600" />
                            <span class="text-sm text-green-700 dark:text-green-300">You are currently marked as available for this slot.</span>
                        </div>
                    </div>
                @endif

                <div class="flex gap-3">
                    @if($isMySchedule)
                        <flux:button variant="danger" wire:click="toggleSchedule" class="flex-1">
                            <div class="flex items-center justify-center">
                                <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                                Remove Availability
                            </div>
                        </flux:button>
                    @else
                        <flux:button variant="primary" wire:click="toggleSchedule" class="flex-1">
                            <div class="flex items-center justify-center">
                                <flux:icon name="check" class="w-4 h-4 mr-2" />
                                Mark as Available
                            </div>
                        </flux:button>
                    @endif
                    <flux:button variant="ghost" wire:click="closeModal">
                        Cancel
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
