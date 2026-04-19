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

        return collect([
            0 => ['name' => 'Sunday', 'short' => 'Sun', 'schedules' => $schedules->where('day_of_week', 0)->values()],
            1 => ['name' => 'Monday', 'short' => 'Mon', 'schedules' => $schedules->where('day_of_week', 1)->values()],
            2 => ['name' => 'Tuesday', 'short' => 'Tue', 'schedules' => $schedules->where('day_of_week', 2)->values()],
            3 => ['name' => 'Wednesday', 'short' => 'Wed', 'schedules' => $schedules->where('day_of_week', 3)->values()],
            4 => ['name' => 'Thursday', 'short' => 'Thu', 'schedules' => $schedules->where('day_of_week', 4)->values()],
            5 => ['name' => 'Friday', 'short' => 'Fri', 'schedules' => $schedules->where('day_of_week', 5)->values()],
            6 => ['name' => 'Saturday', 'short' => 'Sat', 'schedules' => $schedules->where('day_of_week', 6)->values()],
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

    public function getStatsProperty()
    {
        return [
            'total' => LiveSchedule::count(),
            'active' => LiveSchedule::active()->count(),
            'recurring' => LiveSchedule::recurring()->count(),
            'this_week' => \App\Models\LiveSession::whereBetween('scheduled_start_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    public function getTimeRangeProperty()
    {
        // Fixed broadcast window: 8 AM to midnight
        return ['start' => 8, 'end' => 24];
    }
}
?>

<div x-data="{ now: new Date() }" x-init="setInterval(() => { now = new Date() }, 60000)">
    <x-slot:title>Live Schedules</x-slot:title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500;9..144,600;9..144,700&family=JetBrains+Mono:wght@400;500;600;700&family=Inter+Tight:wght@300;400;500;600;700&display=swap');

        .bb-display { font-family: 'Fraunces', 'Times New Roman', serif; font-variation-settings: 'opsz' 144, 'SOFT' 50; letter-spacing: -0.025em; }
        .bb-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }
        .bb-body { font-family: 'Inter Tight', ui-sans-serif, system-ui, sans-serif; letter-spacing: -0.01em; }

        .bb-grain::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.015) 1px, transparent 1px);
            background-size: 3px 3px;
            pointer-events: none; z-index: 0;
        }

        @keyframes bb-fade-up { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .bb-enter { animation: bb-fade-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both; }

        @keyframes bb-pulse-ring { 0% { transform: scale(1); opacity: 0.5; } 100% { transform: scale(2.5); opacity: 0; } }
        .bb-live-ring::after {
            content: ''; position: absolute; inset: 0; border-radius: 9999px;
            background: currentColor; animation: bb-pulse-ring 2s ease-out infinite;
        }

        /* Timeline grid */
        :root {
            --bb-hour-px: 72px;
            --bb-col-width: minmax(0, 1fr);
        }

        .bb-grid-lines {
            background-image:
                linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 100% var(--bb-hour-px);
        }
        :is(.dark) .bb-grid-lines {
            background-image: linear-gradient(to bottom, rgba(255,255,255,0.05) 1px, transparent 1px);
        }
        .bb-grid-lines-light {
            background-image: linear-gradient(to bottom, rgba(0,0,0,0.05) 1px, transparent 1px);
            background-size: 100% var(--bb-hour-px);
        }

        .bb-schedule-block { transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s; }
        .bb-schedule-block:hover { transform: translateY(-1px); z-index: 20; }
        .bb-schedule-block:hover .bb-actions { opacity: 1; transform: translateY(0); }
        .bb-actions { opacity: 0; transform: translateY(2px); transition: all 0.18s ease; }

        .bb-action-btn { backdrop-filter: blur(8px); }

        /* Scrollbar */
        .bb-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .bb-scroll::-webkit-scrollbar-track { background: transparent; }
        .bb-scroll::-webkit-scrollbar-thumb { background: rgba(120,120,120,0.3); border-radius: 3px; }
        .bb-scroll::-webkit-scrollbar-thumb:hover { background: rgba(120,120,120,0.5); }

        .bb-amber-line {
            position: relative;
        }
        .bb-amber-line::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px;
            background: linear-gradient(to bottom, transparent 0%, rgb(245 158 11) 50%, transparent 100%);
        }

        .bb-today-wash {
            background: linear-gradient(to bottom, rgba(245,158,11,0.05) 0%, transparent 40%);
        }
        :is(.dark) .bb-today-wash {
            background: linear-gradient(to bottom, rgba(245,158,11,0.06) 0%, transparent 40%);
        }

        /* Filter pills refine */
        .bb-pill { transition: all 0.15s ease; }
        .bb-pill:hover { border-color: rgba(245,158,11,0.4); }
    </style>

    {{-- ============================================================
         HEADER — Editorial masthead with inline statistics
         ============================================================ --}}
    <div class="bb-enter relative mb-10" style="animation-delay: 0s;">
        <div class="flex items-end justify-between gap-6 flex-wrap">
            <div class="min-w-0">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <div class="relative w-2 h-2 rounded-full bg-red-500 text-red-500">
                            <span class="absolute inset-0 rounded-full bg-red-500 bb-live-ring"></span>
                        </div>
                        <span class="bb-mono text-[10px] uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">
                            On-Air · Weekly Board
                        </span>
                    </div>
                    <span class="h-px w-8 bg-zinc-300 dark:bg-zinc-700"></span>
                    <span class="bb-mono text-[10px] uppercase tracking-[0.22em] text-zinc-400 dark:text-zinc-500">
                        {{ now()->format('D · d M Y') }}
                    </span>
                </div>

                <h1 class="bb-display text-5xl md:text-6xl font-light text-zinc-900 dark:text-zinc-50 leading-[0.95]">
                    Live Schedules<span class="text-amber-500">.</span>
                </h1>
                <p class="bb-body mt-3 text-sm text-zinc-500 dark:text-zinc-400 max-w-lg">
                    Weekly broadcast timetable — orchestrate every stream across platforms, hosts &amp; time slots.
                </p>
            </div>

            <div class="flex items-center gap-2">
                {{-- View toggle — refined --}}
                <div class="flex items-center rounded-full bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-1">
                    <button
                        wire:click="setViewMode('calendar')"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all {{ $viewMode === 'calendar' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-50 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-50' }}"
                    >
                        <flux:icon name="calendar-days" class="w-3.5 h-3.5" />
                        <span class="bb-mono tracking-tight">Grid</span>
                    </button>
                    <button
                        wire:click="setViewMode('table')"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all {{ $viewMode === 'table' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-50 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-50' }}"
                    >
                        <flux:icon name="list-bullet" class="w-3.5 h-3.5" />
                        <span class="bb-mono tracking-tight">List</span>
                    </button>
                </div>

                <a
                    href="{{ route('admin.live-schedules.create') }}"
                    class="group inline-flex items-center gap-2 px-5 py-2.5 bg-zinc-900 dark:bg-amber-500 text-white dark:text-zinc-950 rounded-full text-sm font-medium hover:bg-amber-500 dark:hover:bg-amber-400 transition-all shadow-sm hover:shadow-md"
                >
                    <flux:icon name="plus" class="w-4 h-4 group-hover:rotate-90 transition-transform duration-300" />
                    <span class="bb-body">New Schedule</span>
                </a>
            </div>
        </div>

        {{-- STAT RIBBON — inline, editorial --}}
        <div class="mt-8 pt-6 border-t border-zinc-200 dark:border-zinc-800">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-x-8 gap-y-6">
                @foreach([
                    ['label' => 'Total schedules', 'value' => $this->stats['total'], 'accent' => 'zinc', 'icon' => 'calendar'],
                    ['label' => 'Active right now', 'value' => $this->stats['active'], 'accent' => 'emerald', 'icon' => 'signal'],
                    ['label' => 'Recurring', 'value' => $this->stats['recurring'], 'accent' => 'amber', 'icon' => 'arrow-path'],
                    ['label' => 'Sessions this week', 'value' => $this->stats['this_week'], 'accent' => 'violet', 'icon' => 'sparkles'],
                ] as $i => $stat)
                    @php
                        $accentMap = [
                            'zinc'    => 'text-zinc-900 dark:text-zinc-50',
                            'emerald' => 'text-emerald-600 dark:text-emerald-400',
                            'amber'   => 'text-amber-600 dark:text-amber-400',
                            'violet'  => 'text-violet-600 dark:text-violet-400',
                        ];
                        $dotMap = [
                            'zinc'    => 'bg-zinc-400',
                            'emerald' => 'bg-emerald-500',
                            'amber'   => 'bg-amber-500',
                            'violet'  => 'bg-violet-500',
                        ];
                    @endphp
                    <div class="bb-enter group" style="animation-delay: {{ 0.1 + $i * 0.06 }}s;">
                        <div class="flex items-center gap-1.5 mb-2">
                            <span class="w-1 h-1 rounded-full {{ $dotMap[$stat['accent']] }}"></span>
                            <span class="bb-mono text-[10px] uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">
                                {{ $stat['label'] }}
                            </span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="bb-display text-4xl font-light {{ $accentMap[$stat['accent']] }}">
                                {{ str_pad((string) $stat['value'], 2, '0', STR_PAD_LEFT) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============================================================
         FILTER BAR — Command-bar style
         ============================================================ --}}
    <div class="bb-enter mb-6" style="animation-delay: 0.35s;">
        <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-zinc-50 dark:bg-zinc-900/60 border border-zinc-200 dark:border-zinc-800">
            <div class="relative flex-1 min-w-[220px]">
                <flux:icon name="magnifying-glass" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Search hosts, accounts..."
                    class="bb-body w-full h-10 pl-10 pr-3 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all"
                />
            </div>

            @php
                $selectBase = 'bb-pill bb-body h-10 px-3 pr-9 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-700 dark:text-zinc-200 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 cursor-pointer appearance-none bg-no-repeat bg-[right_0.75rem_center]';
                $selectArrow = 'bg-[url(\'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20viewBox%3D%220%200%2024%2024%22%20stroke%3D%22%23a1a1aa%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E\')]';
            @endphp

            <select wire:model.live="platformFilter" class="{{ $selectBase }} {{ $selectArrow }}">
                <option value="">All platforms</option>
                @foreach($this->platforms as $platform)
                    <option value="{{ $platform->id }}">{{ $platform->display_name }}</option>
                @endforeach
            </select>

            <select wire:model.live="accountFilter" class="{{ $selectBase }} {{ $selectArrow }}">
                <option value="">All accounts</option>
                @foreach($this->accounts as $account)
                    <option value="{{ $account->id }}">
                        {{ $account->name }} · {{ $account->platform->display_name }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="dayFilter" class="{{ $selectBase }} {{ $selectArrow }}">
                <option value="">All days</option>
                <option value="0">Sunday</option>
                <option value="1">Monday</option>
                <option value="2">Tuesday</option>
                <option value="3">Wednesday</option>
                <option value="4">Thursday</option>
                <option value="5">Friday</option>
                <option value="6">Saturday</option>
            </select>

            <select wire:model.live="statusFilter" class="{{ $selectBase }} {{ $selectArrow }}">
                <option value="">All status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>

            @if($search || $platformFilter || $accountFilter || $dayFilter !== '' || $statusFilter !== '')
                <button
                    wire:click="clearFilters"
                    class="bb-body h-10 px-3 text-xs text-zinc-500 dark:text-zinc-400 hover:text-amber-600 dark:hover:text-amber-400 transition-colors flex items-center gap-1"
                >
                    <flux:icon name="x-mark" class="w-3.5 h-3.5" />
                    Reset
                </button>
            @endif
        </div>
    </div>

    @if($viewMode === 'calendar')
    {{-- ============================================================
         GRID VIEW — Real time-based broadcast timeline
         ============================================================ --}}
    @php
        $timeRange = $this->timeRange;
        $hourStart = $timeRange['start'];
        $hourEnd = $timeRange['end'];
        $totalHours = $hourEnd - $hourStart;
        $hourPx = 72;
        $totalGridHeight = $totalHours * $hourPx;
        $todayIndex = now()->dayOfWeek;
        $nowMinutesFromStart = max(0, (now()->hour - $hourStart) * 60 + now()->minute);
        $nowPosition = ($nowMinutesFromStart / 60) * $hourPx;
        $showNowLine = now()->hour >= $hourStart && now()->hour < $hourEnd;

        // Platform color system — intentional, signal-based
        $platformColors = [
            'TikTok Shop'   => ['bar' => 'bg-pink-500', 'tint' => 'from-pink-500/10', 'text' => 'text-pink-600 dark:text-pink-400', 'label' => 'TTS'],
            'Facebook Shop' => ['bar' => 'bg-blue-500', 'tint' => 'from-blue-500/10', 'text' => 'text-blue-600 dark:text-blue-400', 'label' => 'FB'],
            'Shopee'        => ['bar' => 'bg-orange-500', 'tint' => 'from-orange-500/10', 'text' => 'text-orange-600 dark:text-orange-400', 'label' => 'SPE'],
        ];
    @endphp

    <div class="bb-enter" style="animation-delay: 0.45s;">
        {{-- Platform legend --}}
        <div class="mb-3 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-4 text-xs">
                @foreach($platformColors as $platformName => $config)
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-sm {{ $config['bar'] }}"></span>
                        <span class="bb-mono text-[10px] uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ $platformName }}</span>
                    </div>
                @endforeach
            </div>
            <div class="bb-mono text-[10px] uppercase tracking-[0.15em] text-zinc-400 dark:text-zinc-500 flex items-center gap-2">
                <span>{{ sprintf('%02d:00', $hourStart) }}</span>
                <span class="h-px w-8 bg-zinc-300 dark:bg-zinc-700"></span>
                <span>{{ $hourEnd === 24 ? '00:00' : sprintf('%02d:00', $hourEnd) }}</span>
            </div>
        </div>

        <div class="relative rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 overflow-hidden shadow-sm">
            {{-- Column headers --}}
            <div class="grid sticky top-0 z-30 backdrop-blur-md bg-white/85 dark:bg-zinc-950/85 border-b border-zinc-200 dark:border-zinc-800" style="grid-template-columns: 64px repeat(7, 1fr);">
                <div class="py-3 px-2 border-r border-zinc-200 dark:border-zinc-800 flex items-center justify-center">
                    <flux:icon name="clock" class="w-3.5 h-3.5 text-zinc-400" />
                </div>
                @foreach($this->schedulesByDay as $dayIndex => $dayData)
                    @php $isToday = $dayIndex === $todayIndex; @endphp
                    <div class="relative py-3 px-3 border-r last:border-r-0 border-zinc-200 dark:border-zinc-800 {{ $isToday ? 'bg-amber-500/[0.04] dark:bg-amber-500/[0.06]' : '' }}">
                        @if($isToday)
                            <div class="absolute top-0 left-0 right-0 h-[2px] bg-amber-500"></div>
                        @endif
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="bb-mono text-[10px] uppercase tracking-[0.2em] {{ $isToday ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-400 dark:text-zinc-500' }}">
                                    {{ $dayData['short'] }}
                                </span>
                                <span class="bb-display text-lg font-normal {{ $isToday ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-zinc-100' }} leading-tight">
                                    {{ $dayData['name'] }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                @if($isToday)
                                    <span class="bb-mono text-[9px] uppercase tracking-[0.15em] px-1.5 py-0.5 rounded-sm bg-amber-500 text-white font-semibold">Live</span>
                                @endif
                                <span class="bb-mono text-[11px] font-semibold text-zinc-600 dark:text-zinc-300 tabular-nums">
                                    {{ str_pad((string) $dayData['schedules']->count(), 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Time grid body --}}
            <div class="relative grid bb-scroll overflow-y-auto max-h-[calc(100vh-480px)] min-h-[540px]" style="grid-template-columns: 64px repeat(7, 1fr);">
                {{-- Hour axis --}}
                <div class="border-r border-zinc-200 dark:border-zinc-800 relative" style="height: {{ $totalGridHeight }}px;">
                    @for($h = $hourStart; $h < $hourEnd; $h++)
                        @php
                            $display = $h === 0 ? '12 AM' : ($h === 12 ? '12 PM' : ($h > 12 ? ($h - 12) . ' PM' : $h . ' AM'));
                            $top = ($h - $hourStart) * $hourPx;
                        @endphp
                        <div class="absolute left-0 right-0 text-right pr-2.5 pt-0.5" style="top: {{ $top }}px;">
                            <span class="bb-mono text-[10px] font-medium text-zinc-400 dark:text-zinc-500 tracking-tight">
                                {{ $display }}
                            </span>
                        </div>
                    @endfor
                </div>

                {{-- Day columns --}}
                @foreach($this->schedulesByDay as $dayIndex => $dayData)
                    @php $isToday = $dayIndex === $todayIndex; @endphp
                    <div class="relative border-r last:border-r-0 border-zinc-200 dark:border-zinc-800 bb-grid-lines dark:bb-grid-lines {{ !session('dark') ? 'bb-grid-lines-light dark:bb-grid-lines' : '' }} {{ $isToday ? 'bb-today-wash' : '' }}" style="height: {{ $totalGridHeight }}px;">

                        {{-- Current time line (today only) --}}
                        @if($isToday && $showNowLine)
                            <div class="absolute left-0 right-0 z-20 flex items-center pointer-events-none" style="top: {{ $nowPosition }}px;">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500 shadow-[0_0_0_3px_rgba(245,158,11,0.2)] -ml-[3px]"></div>
                                <div class="flex-1 h-[1.5px] bg-gradient-to-r from-amber-500 via-amber-500/70 to-transparent"></div>
                                <div class="bb-mono text-[9px] font-semibold text-amber-600 dark:text-amber-400 bg-white dark:bg-zinc-950 px-1.5 py-0.5 rounded-sm absolute right-1 -top-2 tracking-tight">
                                    {{ now()->format('g:i A') }}
                                </div>
                            </div>
                        @endif

                        {{-- Schedule blocks --}}
                        @foreach($dayData['schedules'] as $idx => $schedule)
                            @php
                                $start = \Carbon\Carbon::parse($schedule->start_time);
                                $end = \Carbon\Carbon::parse($schedule->end_time);
                                $startMinutes = $start->hour * 60 + $start->minute;
                                $endMinutes = $end->hour * 60 + $end->minute;
                                $duration = max(30, $endMinutes - $startMinutes);

                                // Offset from grid start
                                $topMinutes = $startMinutes - ($hourStart * 60);
                                $topPx = ($topMinutes / 60) * $hourPx;
                                $heightPx = ($duration / 60) * $hourPx;

                                // Skip if outside visible range
                                if ($startMinutes < $hourStart * 60 || $startMinutes >= $hourEnd * 60) continue;

                                $platformName = $schedule->platformAccount->platform->display_name;
                                $pc = $platformColors[$platformName] ?? ['bar' => 'bg-zinc-400', 'tint' => 'from-zinc-500/10', 'text' => 'text-zinc-600 dark:text-zinc-400', 'label' => 'OTH'];

                                $hostName = $schedule->liveHost?->name;
                                $hostColor = $schedule->liveHost?->host_color;
                                $hostTextColor = $schedule->liveHost?->host_text_color;
                            @endphp

                            <div
                                wire:key="sched-{{ $schedule->id }}"
                                class="bb-schedule-block absolute left-1 right-1 rounded-lg overflow-hidden group/block {{ $schedule->is_active ? 'opacity-100' : 'opacity-50' }}"
                                style="top: {{ $topPx }}px; height: {{ $heightPx }}px; animation: bb-fade-up 0.45s cubic-bezier(0.16, 1, 0.3, 1) {{ 0.5 + $idx * 0.03 }}s both;"
                            >
                                <div class="relative h-full bg-gradient-to-br {{ $pc['tint'] }} to-transparent border border-zinc-200 dark:border-zinc-700/80 bg-zinc-50 dark:bg-zinc-900 rounded-lg hover:border-zinc-300 dark:hover:border-zinc-600 hover:shadow-lg hover:shadow-black/5 dark:hover:shadow-black/40 transition-all">
                                    {{-- Platform signal bar (left edge) --}}
                                    <div class="absolute top-0 bottom-0 left-0 w-[3px] {{ $pc['bar'] }} rounded-l-lg"></div>

                                    {{-- Inactive diagonal hatching --}}
                                    @if(!$schedule->is_active)
                                        <div class="absolute inset-0 pointer-events-none opacity-40" style="background-image: repeating-linear-gradient(-45deg, transparent 0 6px, rgba(120,120,120,0.15) 6px 7px);"></div>
                                    @endif

                                    <div class="relative h-full p-2.5 pl-3.5 flex flex-col">
                                        {{-- Time header --}}
                                        <div class="flex items-start justify-between gap-1 mb-1">
                                            <div class="min-w-0">
                                                <div class="bb-mono text-xs font-semibold text-zinc-900 dark:text-zinc-50 tabular-nums leading-none">
                                                    {{ $start->format('g:i') }}<span class="text-zinc-400 dark:text-zinc-500 font-normal ml-0.5 text-[10px]">{{ $start->format('A') }}</span>
                                                </div>
                                                <div class="bb-mono text-[10px] text-zinc-400 dark:text-zinc-500 tabular-nums mt-0.5 leading-none">
                                                    {{ $duration }}min · {{ $end->format('g:i A') }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                @if($schedule->is_recurring)
                                                    <flux:icon name="arrow-path" class="w-3 h-3 text-zinc-400 dark:text-zinc-500" title="Recurring" />
                                                @endif
                                                <span class="bb-mono text-[9px] font-bold uppercase tracking-wide {{ $pc['text'] }}">{{ $pc['label'] }}</span>
                                            </div>
                                        </div>

                                        {{-- Host --}}
                                        @if($heightPx >= 60)
                                            <div class="mt-auto">
                                                @if($hostName)
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $hostColor }};"></span>
                                                        <span class="bb-body text-[11px] font-medium truncate" style="color: {{ $hostTextColor }};">
                                                            {{ $hostName }}
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-1.5 text-zinc-400 dark:text-zinc-500">
                                                        <span class="w-1.5 h-1.5 rounded-full border border-current"></span>
                                                        <span class="bb-body text-[11px] italic">Unassigned</span>
                                                    </div>
                                                @endif
                                                <div class="bb-body text-[10px] text-zinc-400 dark:text-zinc-500 truncate mt-0.5">
                                                    {{ $schedule->platformAccount->name }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Hover actions --}}
                                    <div class="bb-actions absolute bottom-1.5 right-1.5 flex items-center gap-0.5">
                                        <button
                                            wire:click="toggleActive({{ $schedule->id }})"
                                            class="bb-action-btn w-6 h-6 rounded-md bg-white/90 dark:bg-zinc-800/90 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:text-amber-600 dark:hover:text-amber-400 hover:border-amber-500/50 flex items-center justify-center transition-colors"
                                            title="{{ $schedule->is_active ? 'Pause' : 'Activate' }}"
                                        >
                                            <flux:icon name="{{ $schedule->is_active ? 'pause' : 'play' }}" class="w-3 h-3" />
                                        </button>
                                        <a
                                            href="{{ route('admin.live-schedules.edit', $schedule) }}"
                                            class="bb-action-btn w-6 h-6 rounded-md bg-white/90 dark:bg-zinc-800/90 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-500/50 flex items-center justify-center transition-colors"
                                            title="Edit"
                                        >
                                            <flux:icon name="pencil-square" class="w-3 h-3" />
                                        </a>
                                        <button
                                            wire:click="deleteSchedule({{ $schedule->id }})"
                                            wire:confirm="Delete this schedule? This cannot be undone."
                                            class="bb-action-btn w-6 h-6 rounded-md bg-white/90 dark:bg-zinc-800/90 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:text-red-600 dark:hover:text-red-400 hover:border-red-500/50 flex items-center justify-center transition-colors"
                                            title="Delete"
                                        >
                                            <flux:icon name="trash" class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Empty day state --}}
                        @if($dayData['schedules']->isEmpty())
                            <div class="absolute inset-x-0 top-24 flex flex-col items-center justify-center pointer-events-none">
                                <div class="w-8 h-[1px] bg-zinc-200 dark:bg-zinc-800 mb-2"></div>
                                <span class="bb-mono text-[9px] uppercase tracking-[0.2em] text-zinc-300 dark:text-zinc-700">Empty</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @else
    {{-- ============================================================
         LIST VIEW — Clean editorial table
         ============================================================ --}}
    <div class="bb-enter rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 overflow-hidden shadow-sm" style="animation-delay: 0.45s;">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50 dark:bg-zinc-900/60 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        @foreach(['Day', 'Time · Duration', 'Platform', 'Account', 'Host', 'Type', 'Status', ''] as $heading)
                            <th class="bb-mono text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400 py-3.5 px-5 first:pl-6 last:pr-6">
                                {{ $heading }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                    @forelse($this->schedules as $idx => $schedule)
                        @php
                            $start = \Carbon\Carbon::parse($schedule->start_time);
                            $end = \Carbon\Carbon::parse($schedule->end_time);
                            $duration = $start->diffInMinutes($end);
                            $platformName = $schedule->platformAccount->platform->display_name;
                            $pc = $platformColors[$platformName] ?? ['bar' => 'bg-zinc-400', 'text' => 'text-zinc-600 dark:text-zinc-400'];
                        @endphp
                        <tr class="group hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors" style="animation: bb-fade-up 0.35s {{ 0.5 + $idx * 0.02 }}s both;">
                            <td class="py-4 pl-6 pr-5">
                                <div class="flex items-center gap-2">
                                    <span class="w-1 h-8 rounded-full {{ now()->dayOfWeek === $schedule->day_of_week ? 'bg-amber-500' : 'bg-zinc-200 dark:bg-zinc-700' }}"></span>
                                    <div>
                                        <div class="bb-display text-sm text-zinc-900 dark:text-zinc-100">{{ $schedule->day_name }}</div>
                                        @if(now()->dayOfWeek === $schedule->day_of_week)
                                            <div class="bb-mono text-[9px] uppercase tracking-wider text-amber-600 dark:text-amber-400">Today</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-5">
                                <div class="bb-mono text-sm font-semibold text-zinc-900 dark:text-zinc-100 tabular-nums">
                                    {{ $start->format('g:i A') }} → {{ $end->format('g:i A') }}
                                </div>
                                <div class="bb-mono text-[11px] text-zinc-500 dark:text-zinc-400 tabular-nums">{{ $duration }} minutes</div>
                            </td>
                            <td class="py-4 px-5">
                                <div class="inline-flex items-center gap-2 px-2 py-1 rounded-md bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
                                    <span class="w-1.5 h-1.5 rounded-sm {{ $pc['bar'] }}"></span>
                                    <span class="bb-body text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $platformName }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-5">
                                <span class="bb-body text-sm text-zinc-700 dark:text-zinc-200">{{ $schedule->platformAccount->name }}</span>
                            </td>
                            <td class="py-4 px-5">
                                @if($schedule->liveHost)
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $schedule->liveHost->host_color }};"></span>
                                        <span class="bb-body text-sm font-medium" style="color: {{ $schedule->liveHost->host_text_color }};">
                                            {{ $schedule->liveHost->name }}
                                        </span>
                                    </div>
                                @else
                                    <span class="bb-body text-sm italic text-zinc-400 dark:text-zinc-500">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-4 px-5">
                                @if($schedule->is_recurring)
                                    <span class="inline-flex items-center gap-1 bb-mono text-[10px] uppercase tracking-wider text-blue-600 dark:text-blue-400">
                                        <flux:icon name="arrow-path" class="w-3 h-3" />
                                        Recurring
                                    </span>
                                @else
                                    <span class="bb-mono text-[10px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">One-off</span>
                                @endif
                            </td>
                            <td class="py-4 px-5">
                                <span class="inline-flex items-center gap-1.5 bb-mono text-[10px] uppercase tracking-wider {{ $schedule->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400 dark:text-zinc-500' }}">
                                    <span class="relative flex w-1.5 h-1.5">
                                        @if($schedule->is_active)
                                            <span class="absolute inline-flex w-full h-full rounded-full bg-emerald-500 opacity-75 animate-ping"></span>
                                        @endif
                                        <span class="relative inline-flex w-1.5 h-1.5 rounded-full {{ $schedule->is_active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                    </span>
                                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-4 pr-6 pl-5">
                                <div class="flex items-center justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button
                                        wire:click="toggleActive({{ $schedule->id }})"
                                        class="w-7 h-7 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 flex items-center justify-center transition-colors"
                                        title="{{ $schedule->is_active ? 'Pause' : 'Activate' }}"
                                    >
                                        <flux:icon name="{{ $schedule->is_active ? 'pause' : 'play' }}" class="w-3.5 h-3.5" />
                                    </button>
                                    <a
                                        href="{{ route('admin.live-schedules.edit', $schedule) }}"
                                        class="w-7 h-7 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 hover:text-blue-600 dark:hover:text-blue-400 flex items-center justify-center transition-colors"
                                        title="Edit"
                                    >
                                        <flux:icon name="pencil-square" class="w-3.5 h-3.5" />
                                    </a>
                                    <button
                                        wire:click="deleteSchedule({{ $schedule->id }})"
                                        wire:confirm="Delete this schedule? This cannot be undone."
                                        class="w-7 h-7 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 hover:text-red-600 dark:hover:text-red-400 flex items-center justify-center transition-colors"
                                        title="Delete"
                                    >
                                        <flux:icon name="trash" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-12 h-12 rounded-full border border-dashed border-zinc-300 dark:border-zinc-700 flex items-center justify-center">
                                        <flux:icon name="calendar-days" class="w-5 h-5 text-zinc-400" />
                                    </div>
                                    <div>
                                        <div class="bb-display text-lg text-zinc-700 dark:text-zinc-300">Nothing on the board</div>
                                        <div class="bb-body text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                                            @if($search || $platformFilter || $dayFilter !== '' || $statusFilter !== '')
                                                Try loosening your filters.
                                            @else
                                                Create your first streaming schedule to get started.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($this->schedules->hasPages())
        <div class="mt-6">
            {{ $this->schedules->links() }}
        </div>
    @endif
    @endif
</div>
