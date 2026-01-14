<?php

use App\Models\LiveSchedule;
use App\Models\Platform;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', keep: true)]
    public $search = '';

    #[Url(as: 'platform', keep: true)]
    public $platformFilter = '';

    #[Url(as: 'account', keep: true)]
    public $accountFilter = '';

    #[Url(as: 'day', keep: true)]
    public $dayFilter = '';

    #[Url(as: 'status', keep: true)]
    public $statusFilter = '';

    public $perPage = 50;

    #[Url(as: 'view', keep: true)]
    public $viewMode = 'calendar'; // 'table' or 'calendar'

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->platformFilter = '';
        $this->accountFilter = '';
        $this->dayFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function deleteSchedule($scheduleId)
    {
        $schedule = LiveSchedule::find($scheduleId);
        if ($schedule) {
            $schedule->delete();
            session()->flash('success', 'Schedule deleted successfully.');
        }
    }

    public function toggleActive($scheduleId)
    {
        $schedule = LiveSchedule::find($scheduleId);
        if ($schedule) {
            $schedule->update(['is_active' => ! $schedule->is_active]);
            session()->flash('success', 'Schedule status updated.');
        }
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function getSchedulesByDayProperty()
    {
        $schedules = LiveSchedule::query()
            ->with(['platformAccount.platform', 'platformAccount.user', 'liveHost'])
            ->when($this->search, function ($query) {
                $query->whereHas('platformAccount', function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', function ($u) {
                            $u->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->platformFilter, function ($query) {
                $query->whereHas('platformAccount', function ($q) {
                    $q->where('platform_id', $this->platformFilter);
                });
            })
            ->when($this->accountFilter, function ($query) {
                $query->where('platform_account_id', $this->accountFilter);
            })
            ->when($this->statusFilter !== '', function ($query) {
                if ($this->statusFilter === '1') {
                    $query->where('is_active', true);
                } else {
                    $query->where('is_active', false);
                }
            })
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group schedules by day
        return collect([
            0 => ['name' => 'Sunday', 'schedules' => $schedules->where('day_of_week', 0)],
            1 => ['name' => 'Monday', 'schedules' => $schedules->where('day_of_week', 1)],
            2 => ['name' => 'Tuesday', 'schedules' => $schedules->where('day_of_week', 2)],
            3 => ['name' => 'Wednesday', 'schedules' => $schedules->where('day_of_week', 3)],
            4 => ['name' => 'Thursday', 'schedules' => $schedules->where('day_of_week', 4)],
            5 => ['name' => 'Friday', 'schedules' => $schedules->where('day_of_week', 5)],
            6 => ['name' => 'Saturday', 'schedules' => $schedules->where('day_of_week', 6)],
        ]);
    }

    public function getSchedulesProperty()
    {
        return LiveSchedule::query()
            ->with(['platformAccount.platform', 'platformAccount.user', 'liveHost'])
            ->when($this->search, function ($query) {
                $query->whereHas('platformAccount', function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', function ($u) {
                            $u->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->platformFilter, function ($query) {
                $query->whereHas('platformAccount', function ($q) {
                    $q->where('platform_id', $this->platformFilter);
                });
            })
            ->when($this->accountFilter, function ($query) {
                $query->where('platform_account_id', $this->accountFilter);
            })
            ->when($this->dayFilter !== '', function ($query) {
                $query->where('day_of_week', $this->dayFilter);
            })
            ->when($this->statusFilter !== '', function ($query) {
                if ($this->statusFilter === '1') {
                    $query->where('is_active', true);
                } else {
                    $query->where('is_active', false);
                }
            })
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate($this->perPage);
    }

    public function getPlatformsProperty()
    {
        return Platform::active()->ordered()->get();
    }

    public function getAccountsProperty()
    {
        return \App\Models\PlatformAccount::query()
            ->with(['platform', 'user'])
            ->when($this->platformFilter, function ($query) {
                $query->where('platform_id', $this->platformFilter);
            })
            ->whereHas('liveSchedules')
            ->orderBy('name')
            ->get();
    }
}
?>

<div>
    <x-slot:title>Live Schedules</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Live Schedules</flux:heading>
            <flux:text class="mt-2">Weekly timetable for live streaming sessions</flux:text>
        </div>
        <div class="flex gap-3">
            <!-- View Mode Toggle -->
            <div class="flex bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                <button
                    wire:click="setViewMode('calendar')"
                    class="px-3 py-1.5 rounded transition-colors {{ $viewMode === 'calendar' ? 'bg-white dark:bg-gray-700 shadow-sm' : 'hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                >
                    <flux:icon name="calendar" class="w-4 h-4" />
                </button>
                <button
                    wire:click="setViewMode('table')"
                    class="px-3 py-1.5 rounded transition-colors {{ $viewMode === 'table' ? 'bg-white dark:bg-gray-700 shadow-sm' : 'hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                >
                    <flux:icon name="table-cells" class="w-4 h-4" />
                </button>
            </div>

            <flux:button variant="primary" href="{{ route('admin.live-schedules.create') }}">
                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                Add Schedule
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex gap-4 items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by host or account..."
                icon="magnifying-glass"
            />
        </div>

        <flux:select wire:model.live="platformFilter" placeholder="All Platforms">
            <option value="">All Platforms</option>
            @foreach($this->platforms as $platform)
                <option value="{{ $platform->id }}">{{ $platform->display_name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="accountFilter" placeholder="All Accounts">
            <option value="">All Accounts</option>
            @foreach($this->accounts as $account)
                <option value="{{ $account->id }}">
                    {{ $account->name }} ({{ $account->platform->display_name }})
                </option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="dayFilter" placeholder="All Days">
            <option value="">All Days</option>
            <option value="0">Sunday</option>
            <option value="1">Monday</option>
            <option value="2">Tuesday</option>
            <option value="3">Wednesday</option>
            <option value="4">Thursday</option>
            <option value="5">Friday</option>
            <option value="6">Saturday</option>
        </flux:select>

        <flux:select wire:model.live="statusFilter" placeholder="All Status">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </flux:select>

        @if($search || $platformFilter || $accountFilter || $dayFilter !== '' || $statusFilter !== '')
            <flux:button variant="ghost" wire:click="clearFilters">
                Clear Filters
            </flux:button>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Schedules</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ LiveSchedule::count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Schedules</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">
                {{ LiveSchedule::active()->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Recurring Schedules</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                {{ LiveSchedule::recurring()->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Sessions This Week</div>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                {{ \App\Models\LiveSession::whereBetween('scheduled_start_at', [now()->startOfWeek(), now()->endOfWeek()])->count() }}
            </div>
        </div>
    </div>

    @if($viewMode === 'calendar')
    <!-- Calendar View -->
    <div class="grid grid-cols-1 lg:grid-cols-7 gap-3">
        @foreach($this->schedulesByDay as $dayIndex => $dayData)
            @php
                $isToday = now()->dayOfWeek === $dayIndex;
                $dayColors = [
                    0 => ['bg' => 'from-orange-50 to-red-50', 'border' => 'border-orange-200', 'text' => 'text-orange-700', 'hover' => 'hover:from-orange-100 hover:to-red-100'],
                    1 => ['bg' => 'from-gray-50 to-slate-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'hover' => 'hover:from-gray-100 hover:to-slate-100'],
                    2 => ['bg' => 'from-pink-50 to-rose-50', 'border' => 'border-pink-200', 'text' => 'text-pink-700', 'hover' => 'hover:from-pink-100 hover:to-rose-100'],
                    3 => ['bg' => 'from-emerald-50 to-green-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-700', 'hover' => 'hover:from-emerald-100 hover:to-green-100'],
                    4 => ['bg' => 'from-amber-50 to-yellow-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'hover' => 'hover:from-amber-100 hover:to-yellow-100'],
                    5 => ['bg' => 'from-blue-50 to-indigo-50', 'border' => 'border-blue-200', 'text' => 'text-blue-700', 'hover' => 'hover:from-blue-100 hover:to-indigo-100'],
                    6 => ['bg' => 'from-purple-50 to-violet-50', 'border' => 'border-purple-200', 'text' => 'text-purple-700', 'hover' => 'hover:from-purple-100 hover:to-violet-100'],
                ];
                $dayColor = $dayColors[$dayIndex];
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-lg border {{ $isToday ? 'border-blue-400 shadow-md ring-2 ring-blue-400/20' : 'border-gray-200 dark:border-gray-700' }} hover:shadow-lg transition-all duration-200">
                <!-- Day Header - Interactive with Subtle Gradients -->
                <div class="relative px-4 py-3 bg-gradient-to-br {{ $isToday ? 'from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20' : $dayColor['bg'] . ' dark:from-gray-800 dark:to-gray-850' }} {{ $dayColor['hover'] }} transition-all duration-200 cursor-pointer group">
                    <!-- Top accent line -->
                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r {{ $isToday ? 'from-blue-400 to-indigo-400' : $dayColor['border'] }} opacity-0 group-hover:opacity-100 transition-opacity"></div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-sm {{ $isToday ? 'text-blue-700 dark:text-blue-400' : $dayColor['text'] . ' dark:text-gray-300' }} group-hover:scale-105 transition-transform">
                                {{ $dayData['name'] }}
                            </h3>
                            @if($isToday)
                                <span class="flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-blue-500 text-white rounded shadow-sm animate-pulse">
                                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                                    Today
                                </span>
                            @endif
                        </div>

                        <!-- Session count badge -->
                        <div class="flex items-center gap-1.5 px-2 py-1 rounded-md bg-white/60 dark:bg-gray-900/40 backdrop-blur-sm border {{ $isToday ? 'border-blue-200' : 'border-gray-200 dark:border-gray-700' }} shadow-sm group-hover:shadow transition-shadow">
                            <flux:icon name="calendar" class="w-3 h-3 {{ $isToday ? 'text-blue-600' : 'text-gray-500' }}" />
                            <span class="text-xs font-semibold {{ $isToday ? 'text-blue-700 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $dayData['schedules']->count() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Schedules for this day -->
                <div class="p-2 space-y-2 min-h-[200px] max-h-[600px] overflow-y-auto">
                    @forelse($dayData['schedules'] as $schedule)
                        @php
                            $platformColors = [
                                'TikTok Shop' => ['dot' => 'bg-gray-800', 'border' => 'border-gray-300', 'text' => 'text-gray-700'],
                                'Facebook Shop' => ['dot' => 'bg-blue-600', 'border' => 'border-blue-300', 'text' => 'text-blue-700'],
                                'Shopee' => ['dot' => 'bg-orange-500', 'border' => 'border-orange-300', 'text' => 'text-orange-700'],
                            ];
                            $platformColor = $platformColors[$schedule->platformAccount->platform->display_name] ?? ['dot' => 'bg-gray-600', 'border' => 'border-gray-300', 'text' => 'text-gray-700'];
                        @endphp
                        <div class="group">
                            <div class="relative bg-white dark:bg-gray-900 rounded-lg border {{ $schedule->is_active ? $platformColor['border'] : 'border-gray-200 dark:border-gray-700' }} hover:shadow-md transition-all p-3">
                                <!-- Time - Primary Focus -->
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-base font-semibold text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($schedule->start_time)->diffInMinutes(\Carbon\Carbon::parse($schedule->end_time)) }}m
                                        </span>
                                    </div>

                                    <!-- Status Indicator -->
                                    <div class="flex items-center gap-1.5">
                                        @if($schedule->is_recurring)
                                            <flux:icon name="arrow-path" class="w-3 h-3 text-gray-400" title="Recurring" />
                                        @endif
                                        <div class="w-2 h-2 rounded-full {{ $schedule->is_active ? $platformColor['dot'] : 'bg-gray-300 dark:bg-gray-600' }}" title="{{ $schedule->is_active ? 'Active' : 'Inactive' }}"></div>
                                    </div>
                                </div>

                                <!-- Host Name -->
                                @if($schedule->liveHost)
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mb-1"
                                        style="background-color: {{ $schedule->liveHost->host_color }}; color: {{ $schedule->liveHost->host_text_color }};"
                                    >
                                        {{ $schedule->liveHost->name }}
                                    </span>
                                @else
                                    <p class="text-sm text-gray-400 dark:text-gray-500 mb-1 italic truncate">
                                        Not assigned
                                    </p>
                                @endif

                                <!-- Platform - Minimal -->
                                <p class="text-xs {{ $platformColor['text'] }} dark:text-gray-400">
                                    {{ $schedule->platformAccount->platform->display_name }}
                                </p>

                                <!-- Actions - Always visible but subtle -->
                                <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 flex gap-1">
                                    <button
                                        wire:click="toggleActive({{ $schedule->id }})"
                                        class="flex-1 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 rounded transition-colors"
                                        title="{{ $schedule->is_active ? 'Deactivate' : 'Activate' }}"
                                    >
                                        <flux:icon name="{{ $schedule->is_active ? 'pause' : 'play' }}" class="w-3 h-3 mx-auto" />
                                    </button>
                                    <a
                                        href="{{ route('admin.live-schedules.edit', $schedule) }}"
                                        class="flex-1 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 rounded transition-colors"
                                        title="Edit"
                                    >
                                        <flux:icon name="pencil" class="w-3 h-3 mx-auto" />
                                    </a>
                                    <button
                                        wire:click="deleteSchedule({{ $schedule->id }})"
                                        wire:confirm="Are you sure you want to delete this schedule?"
                                        class="flex-1 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                                        title="Delete"
                                    >
                                        <flux:icon name="trash" class="w-3 h-3 mx-auto" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-32 text-center">
                            <flux:icon name="calendar-days" class="w-6 h-6 text-gray-300 dark:text-gray-600 mb-2" />
                            <p class="text-xs text-gray-400 dark:text-gray-500">No sessions</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
    @else
    <!-- Table View -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Day
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Time
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Platform
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Account
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Host
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Recurring
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->schedules as $schedule)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $schedule->day_name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $schedule->time_range }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="outline" color="blue">
                                    {{ $schedule->platformAccount->platform->display_name }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $schedule->platformAccount->name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($schedule->liveHost)
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium"
                                        style="background-color: {{ $schedule->liveHost->host_color }}; color: {{ $schedule->liveHost->host_text_color }};"
                                    >
                                        {{ $schedule->liveHost->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">Not assigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($schedule->is_recurring)
                                    <flux:badge color="green">Yes</flux:badge>
                                @else
                                    <flux:badge color="gray">No</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge :color="$schedule->is_active ? 'green' : 'gray'">
                                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex gap-2">
                                    <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $schedule->id }})">
                                        {{ $schedule->is_active ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                    <flux:button variant="ghost" size="sm" href="{{ route('admin.live-schedules.edit', $schedule) }}">
                                        Edit
                                    </flux:button>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="deleteSchedule({{ $schedule->id }})"
                                        wire:confirm="Are you sure you want to delete this schedule?"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                @if($search || $platformFilter || $dayFilter !== '' || $statusFilter !== '')
                                    No schedules found matching your filters.
                                @else
                                    No schedules yet. Create your first streaming schedule to get started.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($this->schedules->hasPages())
        <div class="mt-4">
            {{ $this->schedules->links() }}
        </div>
    @endif
    @endif
</div>
