<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public function getTotalSessionsProperty()
    {
        return auth()->user()->hostedSessions()->count();
    }

    public function getUpcomingSessionsProperty()
    {
        return auth()->user()->hostedSessions()
            ->upcoming()
            ->count();
    }

    public function getLiveNowProperty()
    {
        return auth()->user()->hostedSessions()
            ->live()
            ->count();
    }

    public function getCompletedThisWeekProperty()
    {
        return auth()->user()->hostedSessions()
            ->ended()
            ->where('actual_end_at', '>=', now()->startOfWeek())
            ->count();
    }

    public function getActivePlatformAccountsProperty()
    {
        return auth()->user()->assignedPlatformAccounts()
            ->where('is_active', true)
            ->count();
    }

    public function getTotalViewersProperty()
    {
        return auth()->user()->hostedSessions()
            ->ended()
            ->join('live_analytics', 'live_sessions.id', '=', 'live_analytics.live_session_id')
            ->sum('live_analytics.viewers_peak') ?? 0;
    }

    public function getTodaySessionsProperty()
    {
        return auth()->user()->hostedSessions()
            ->with(['platformAccount.platform', 'analytics', 'liveSchedule'])
            ->whereDate('scheduled_start_at', today())
            ->orderBy('scheduled_start_at')
            ->get();
    }

    public function getUpcomingSessionsListProperty()
    {
        return auth()->user()->hostedSessions()
            ->with(['platformAccount.platform', 'analytics', 'liveSchedule'])
            ->upcoming()
            ->orderBy('scheduled_start_at')
            ->limit(5)
            ->get();
    }

    public function getPlatformAccountsProperty()
    {
        return auth()->user()->assignedPlatformAccounts()
            ->with(['platform', 'liveSchedules', 'liveSessions'])
            ->withCount(['liveSchedules', 'liveSessions'])
            ->get();
    }

    public function getRecentAnalyticsProperty()
    {
        return auth()->user()->hostedSessions()
            ->ended()
            ->with('analytics')
            ->whereHas('analytics')
            ->orderBy('actual_end_at', 'desc')
            ->limit(5)
            ->get();
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">Live Host Dashboard</flux:heading>
        <flux:text class="mt-2">Welcome back, {{ auth()->user()->name }}! Here's your live streaming overview.</flux:text>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="rounded-md bg-blue-50 p-3">
                    <flux:icon.video-camera class="h-6 w-6 text-blue-600" />
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-semibold text-gray-900">{{ $this->totalSessions }}</p>
                    <p class="text-sm text-gray-500">Total Sessions</p>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="rounded-md bg-indigo-50 p-3">
                    <flux:icon.calendar class="h-6 w-6 text-indigo-600" />
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-semibold text-gray-900">{{ $this->upcomingSessions }}</p>
                    <p class="text-sm text-gray-500">Upcoming</p>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="rounded-md bg-green-50 p-3">
                    <flux:icon.signal class="h-6 w-6 text-green-600" />
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-semibold text-gray-900">{{ $this->liveNow }}</p>
                    <p class="text-sm text-gray-500">Live Now</p>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="rounded-md bg-purple-50 p-3">
                    <flux:icon.check-circle class="h-6 w-6 text-purple-600" />
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-semibold text-gray-900">{{ $this->completedThisWeek }}</p>
                    <p class="text-sm text-gray-500">This Week</p>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="rounded-md bg-orange-50 p-3">
                    <flux:icon.link class="h-6 w-6 text-orange-600" />
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-semibold text-gray-900">{{ $this->activePlatformAccounts }}</p>
                    <p class="text-sm text-gray-500">Active Accounts</p>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="rounded-md bg-pink-50 p-3">
                    <flux:icon.users class="h-6 w-6 text-pink-600" />
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($this->totalViewers) }}</p>
                    <p class="text-sm text-gray-500">Total Viewers</p>
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Today's Schedule -->
        <flux:card>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Today's Schedule</flux:heading>
                    <flux:badge variant="filled" color="blue">{{ today()->format('M d, Y') }}</flux:badge>
                </div>

                @if ($this->todaySessions->count() > 0)
                    <div class="space-y-3">
                        @foreach ($this->todaySessions as $session)
                            <a href="{{ route('live-host.sessions.show', $session) }}"
                               class="block p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                               wire:navigate>
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <flux:badge variant="filled" :color="$session->status_color">
                                                {{ ucfirst($session->status) }}
                                            </flux:badge>
                                            @if ($session->isAdminAssigned())
                                                <flux:badge variant="outline" color="blue" size="sm">Admin</flux:badge>
                                            @else
                                                <flux:badge variant="outline" color="purple" size="sm">Self</flux:badge>
                                            @endif
                                            <span class="text-sm font-medium text-gray-900">
                                                {{ $session->scheduled_start_at->format('h:i A') }}
                                            </span>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $session->title }}</p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            {{ $session->platformAccount->name }}
                                        </p>
                                    </div>
                                    <flux:icon.chevron-right class="w-5 h-5 text-gray-400" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon.calendar class="mx-auto h-12 w-12 text-gray-400 mb-3" />
                        <p class="text-gray-500">No sessions scheduled for today</p>
                        <flux:button variant="ghost" href="{{ route('live-host.schedule') }}" class="mt-3" wire:navigate>
                            View Full Schedule
                        </flux:button>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Platform Accounts -->
        <flux:card>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Platform Accounts</flux:heading>
                    <flux:badge variant="outline">{{ $this->platformAccounts->count() }} Connected</flux:badge>
                </div>

                @if ($this->platformAccounts->count() > 0)
                    <div class="space-y-3">
                        @foreach ($this->platformAccounts as $account)
                            <div class="p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <flux:badge variant="filled" :color="$account->is_active ? 'green' : 'gray'">
                                                {{ $account->is_active ? 'Active' : 'Inactive' }}
                                            </flux:badge>
                                        </div>
                                        <p class="font-semibold text-gray-900">{{ $account->platform->name ?? 'Unknown Platform' }}</p>
                                        <p class="text-sm text-gray-600 mt-1">{{ $account->name ?: 'No account name' }}</p>
                                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                            <span>{{ $account->live_schedules_count }} schedules</span>
                                            <span>•</span>
                                            <span>{{ $account->live_sessions_count }} sessions</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon.link class="mx-auto h-12 w-12 text-gray-400 mb-3" />
                        <p class="text-gray-500">No platform accounts connected</p>
                        <p class="text-sm text-gray-400 mt-1">Contact your administrator to set up accounts</p>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- Upcoming Sessions -->
    <flux:card class="mb-6">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Upcoming Sessions</flux:heading>
                <flux:button variant="ghost" href="{{ route('live-host.sessions.index') }}" wire:navigate>
                    View All
                </flux:button>
            </div>

            @if ($this->upcomingSessionsList->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Session</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scheduled Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($this->upcomingSessionsList as $session)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm font-medium text-gray-900">{{ $session->title }}</div>
                                            @if ($session->isAdminAssigned())
                                                <flux:badge variant="outline" color="blue" size="sm">Admin</flux:badge>
                                            @else
                                                <flux:badge variant="outline" color="purple" size="sm">Self</flux:badge>
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">{{ Str::limit($session->description, 50) }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">{{ $session->platformAccount->name }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">{{ $session->scheduled_start_at->format('M d, Y') }}</div>
                                        <div class="text-sm text-gray-500">{{ $session->scheduled_start_at->format('h:i A') }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <flux:badge variant="filled" :color="$session->status_color">
                                            {{ ucfirst($session->status) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            href="{{ route('live-host.sessions.show', $session) }}"
                                            wire:navigate
                                        >
                                            View
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon.video-camera class="mx-auto h-12 w-12 text-gray-400 mb-3" />
                    <p class="text-gray-500">No upcoming sessions</p>
                    <p class="text-sm text-gray-400 mt-1">Your next sessions will appear here</p>
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Recent Performance -->
    @if ($this->recentAnalytics->count() > 0)
        <flux:card>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Recent Performance</flux:heading>
                    <flux:badge variant="outline">Last 5 Sessions</flux:badge>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Session</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Peak Viewers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Viewers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Engagement</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($this->recentAnalytics as $session)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $session->title }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $session->actual_end_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ number_format($session->analytics->viewers_peak) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ number_format($session->analytics->viewers_avg) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            {{ number_format($session->analytics->total_engagement) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ number_format($session->analytics->engagement_rate, 1) }}% rate
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $session->analytics->duration_minutes }} min
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
