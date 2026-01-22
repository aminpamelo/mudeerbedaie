<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public User $host;

    public string $activeTab = 'overview';

    public function mount(User $host): void
    {
        $this->host = $host->load([
            'assignedPlatformAccounts.platform',
            'assignedPlatformAccounts.liveSchedules',
            'assignedPlatformAccounts.liveSessions' => fn ($q) => $q->latest()->limit(10),
        ]);
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function deleteHost(): void
    {
        if ($this->host->assignedPlatformAccounts()->count() > 0) {
            session()->flash('error', 'Cannot delete live host with connected platform accounts.');

            return;
        }

        $this->host->delete();

        session()->flash('success', 'Live host deleted successfully.');

        $this->redirect(route('admin.live-hosts'), navigate: true);
    }

    public function getSchedulesByDayProperty()
    {
        $schedules = $this->host->assignedPlatformAccounts->flatMap->liveSchedules;

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

    public function getSessionsByDayProperty()
    {
        // Get all sessions for the host
        $sessions = $this->host->hostedSessions()
            ->with(['platformAccount.platform'])
            ->whereBetween('scheduled_start_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->orderBy('scheduled_start_at')
            ->get();

        // Group sessions by day of week
        return collect([
            0 => ['name' => 'Sunday', 'date' => now()->startOfWeek()->addDays(0), 'sessions' => $sessions->filter(fn ($s) => $s->scheduled_start_at->dayOfWeek === 0)],
            1 => ['name' => 'Monday', 'date' => now()->startOfWeek()->addDays(1), 'sessions' => $sessions->filter(fn ($s) => $s->scheduled_start_at->dayOfWeek === 1)],
            2 => ['name' => 'Tuesday', 'date' => now()->startOfWeek()->addDays(2), 'sessions' => $sessions->filter(fn ($s) => $s->scheduled_start_at->dayOfWeek === 2)],
            3 => ['name' => 'Wednesday', 'date' => now()->startOfWeek()->addDays(3), 'sessions' => $sessions->filter(fn ($s) => $s->scheduled_start_at->dayOfWeek === 3)],
            4 => ['name' => 'Thursday', 'date' => now()->startOfWeek()->addDays(4), 'sessions' => $sessions->filter(fn ($s) => $s->scheduled_start_at->dayOfWeek === 4)],
            5 => ['name' => 'Friday', 'date' => now()->startOfWeek()->addDays(5), 'sessions' => $sessions->filter(fn ($s) => $s->scheduled_start_at->dayOfWeek === 5)],
            6 => ['name' => 'Saturday', 'date' => now()->startOfWeek()->addDays(6), 'sessions' => $sessions->filter(fn ($s) => $s->scheduled_start_at->dayOfWeek === 6)],
        ]);
    }

    public function with(): array
    {
        $stats = [
            'platform_accounts' => $this->host->assignedPlatformAccounts()->count(),
            'active_accounts' => $this->host->assignedPlatformAccounts()->where('is_active', true)->count(),
            'schedules' => $this->host->assignedPlatformAccounts()->withCount('liveSchedules')->get()->sum('live_schedules_count'),
            'total_sessions' => $this->host->hostedSessions()->count(),
            'upcoming_sessions' => $this->host->hostedSessions()->where('status', 'scheduled')->where('scheduled_start_at', '>', now())->count(),
            'live_now' => $this->host->hostedSessions()->where('status', 'live')->count(),
        ];

        return compact('stats');
    }
}; ?>

<div>
    <x-slot:title>{{ $host->name }} - Live Host</x-slot:title>

    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" href="{{ route('admin.live-hosts') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    Back
                </div>
            </flux:button>
            <div>
                <flux:heading size="xl">{{ $host->name }}</flux:heading>
                <flux:text class="mt-1">Live Host Details</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="outline" href="{{ route('admin.live-hosts.edit', $host) }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                    Edit
                </div>
            </flux:button>
            <flux:button variant="danger" wire:click="deleteHost" wire:confirm="Are you sure you want to delete this live host?">
                <div class="flex items-center justify-center">
                    <flux:icon name="trash" class="w-4 h-4 mr-1" />
                    Delete
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Assigned Accounts</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['platform_accounts'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Accounts</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $stats['active_accounts'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Schedules</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $stats['schedules'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sessions</div>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ $stats['total_sessions'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Upcoming</div>
            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">{{ $stats['upcoming_sessions'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Live Now</div>
            <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $stats['live_now'] }}</div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <div class="flex gap-6">
            <button
                wire:click="setActiveTab('overview')"
                class="pb-3 border-b-2 transition-colors {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                Overview
            </button>
            <button
                wire:click="setActiveTab('accounts')"
                class="pb-3 border-b-2 transition-colors {{ $activeTab === 'accounts' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                Platform Accounts ({{ $stats['platform_accounts'] }})
            </button>
            <button
                wire:click="setActiveTab('schedules')"
                class="pb-3 border-b-2 transition-colors {{ $activeTab === 'schedules' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                Schedules ({{ $stats['schedules'] }})
            </button>
            <button
                wire:click="setActiveTab('sessions')"
                class="pb-3 border-b-2 transition-colors {{ $activeTab === 'sessions' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                Sessions ({{ $stats['total_sessions'] }})
            </button>
        </div>
    </div>

    <!-- Tab Content -->
    <div>
        @if($activeTab === 'overview')
            <!-- Overview Tab -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Host Information -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <flux:heading size="lg" class="mb-4">Host Information</flux:heading>
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $host->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $host->email }}</p>
                        </div>
                        @if($host->phone)
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</p>
                                <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $host->phone }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p>
                            <div class="mt-1">
                                <flux:badge :color="$host->getStatusColor()">
                                    {{ ucfirst($host->status) }}
                                </flux:badge>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Role</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">Live Host</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Member Since</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $host->created_at->format('F j, Y') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Sessions -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <flux:heading size="lg" class="mb-4">Recent Sessions</flux:heading>
                    <div class="space-y-3">
                        @forelse($host->hostedSessions()->latest('scheduled_start_at')->take(5)->get() as $session)
                            <div class="flex items-start justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $session->title ?? 'Live Session' }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $session->scheduled_start_at->format('M j, Y g:i A') }}
                                    </p>
                                </div>
                                <flux:badge :color="$session->status_color">
                                    {{ ucfirst($session->status) }}
                                </flux:badge>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No sessions yet</p>
                        @endforelse
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'accounts')
            <!-- Platform Accounts Tab -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Assigned Platform Accounts</flux:heading>
                    <div class="space-y-4">
                        @forelse($host->assignedPlatformAccounts as $account)
                            <div class="flex items-start justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <flux:heading size="base">{{ $account->name }}</flux:heading>
                                        <flux:badge :color="$account->is_active ? 'green' : 'gray'">
                                            {{ $account->is_active ? 'Active' : 'Inactive' }}
                                        </flux:badge>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Platform</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $account->platform->display_name }}</p>
                                        </div>
                                        @if($account->email)
                                            <div>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $account->email }}</p>
                                            </div>
                                        @endif
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Schedules</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $account->liveSchedules->count() }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Sessions</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $account->liveSessions->count() }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <p class="text-sm text-gray-500 dark:text-gray-400">No platform accounts connected yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'schedules')
            <!-- Schedules Tab - Calendar View -->
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
                        <!-- Day Header -->
                        <div class="relative px-4 py-3 bg-gradient-to-br {{ $isToday ? 'from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20' : $dayColor['bg'] . ' dark:from-gray-800 dark:to-gray-850' }} {{ $dayColor['hover'] }} transition-all duration-200 cursor-pointer group">
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

                                <!-- Schedule count badge -->
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

                                        <!-- Account Name -->
                                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-1 font-medium truncate">
                                            {{ $schedule->platformAccount->name }}
                                        </p>

                                        <!-- Platform - Minimal -->
                                        <p class="text-xs {{ $platformColor['text'] }} dark:text-gray-400">
                                            {{ $schedule->platformAccount->platform->display_name }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-32 text-center">
                                    <flux:icon name="calendar-days" class="w-6 h-6 text-gray-300 dark:text-gray-600 mb-2" />
                                    <p class="text-xs text-gray-400 dark:text-gray-500">No schedules</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

        @elseif($activeTab === 'sessions')
            <!-- Live Sessions Tab - Calendar View -->
            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Showing sessions for {{ now()->startOfWeek()->format('M j') }} - {{ now()->endOfWeek()->format('M j, Y') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-7 gap-3">
                @foreach($this->sessionsByDay as $dayIndex => $dayData)
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
                        <!-- Day Header -->
                        <div class="relative px-4 py-3 bg-gradient-to-br {{ $isToday ? 'from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20' : $dayColor['bg'] . ' dark:from-gray-800 dark:to-gray-850' }} {{ $dayColor['hover'] }} transition-all duration-200 cursor-pointer group">
                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r {{ $isToday ? 'from-blue-400 to-indigo-400' : $dayColor['border'] }} opacity-0 group-hover:opacity-100 transition-opacity"></div>

                            <div class="flex items-center justify-between">
                                <div class="flex flex-col gap-1">
                                    <h3 class="font-semibold text-sm {{ $isToday ? 'text-blue-700 dark:text-blue-400' : $dayColor['text'] . ' dark:text-gray-300' }} group-hover:scale-105 transition-transform">
                                        {{ $dayData['name'] }}
                                    </h3>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $dayData['date']->format('M j') }}
                                    </span>
                                </div>

                                <div class="flex flex-col items-end gap-1">
                                    @if($isToday)
                                        <span class="flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-blue-500 text-white rounded shadow-sm animate-pulse">
                                            <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                                            Today
                                        </span>
                                    @endif
                                    <!-- Session count badge -->
                                    <div class="flex items-center gap-1.5 px-2 py-1 rounded-md bg-white/60 dark:bg-gray-900/40 backdrop-blur-sm border {{ $isToday ? 'border-blue-200' : 'border-gray-200 dark:border-gray-700' }} shadow-sm group-hover:shadow transition-shadow">
                                        <flux:icon name="video-camera" class="w-3 h-3 {{ $isToday ? 'text-blue-600' : 'text-gray-500' }}" />
                                        <span class="text-xs font-semibold {{ $isToday ? 'text-blue-700 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300' }}">
                                            {{ $dayData['sessions']->count() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sessions for this day -->
                        <div class="p-2 space-y-2 min-h-[200px] max-h-[600px] overflow-y-auto">
                            @forelse($dayData['sessions']->sortBy('scheduled_start_at') as $session)
                                @php
                                    $statusColors = [
                                        'scheduled' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-300', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500'],
                                        'live' => ['bg' => 'bg-green-50', 'border' => 'border-green-300', 'text' => 'text-green-700', 'dot' => 'bg-green-500'],
                                        'ended' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400'],
                                        'cancelled' => ['bg' => 'bg-red-50', 'border' => 'border-red-300', 'text' => 'text-red-700', 'dot' => 'bg-red-500'],
                                    ];
                                    $statusColor = $statusColors[$session->status] ?? $statusColors['ended'];
                                @endphp
                                <div class="group">
                                    <div class="relative bg-white dark:bg-gray-900 rounded-lg border {{ $statusColor['border'] }} hover:shadow-md transition-all p-3">
                                        <!-- Time and Status -->
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-baseline gap-2">
                                                <span class="text-base font-semibold text-gray-900 dark:text-white">
                                                    {{ $session->scheduled_start_at->format('g:i A') }}
                                                </span>
                                                @if($session->duration)
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $session->duration }}m
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Status Indicator -->
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-2 h-2 rounded-full {{ $statusColor['dot'] }}" title="{{ ucfirst($session->status) }}"></div>
                                            </div>
                                        </div>

                                        <!-- Title -->
                                        <p class="text-sm text-gray-900 dark:text-white mb-1 font-medium truncate">
                                            {{ $session->title ?? 'Live Session' }}
                                        </p>

                                        <!-- Platform Account -->
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-1 truncate">
                                            {{ $session->platformAccount->name }}
                                        </p>

                                        <!-- Platform -->
                                        <p class="text-xs {{ $statusColor['text'] }} dark:text-gray-400">
                                            {{ $session->platformAccount->platform->display_name }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-32 text-center">
                                    <flux:icon name="video-camera" class="w-6 h-6 text-gray-300 dark:text-gray-600 mb-2" />
                                    <p class="text-xs text-gray-400 dark:text-gray-500">No sessions</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
