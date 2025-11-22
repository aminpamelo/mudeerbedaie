<?php

use App\Models\LiveSession;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $platformFilter = '';
    public $dateFilter = '';
    public $perPage = 15;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingPlatformFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->platformFilter = '';
        $this->dateFilter = '';
        $this->resetPage();
    }

    public function getSessionsProperty()
    {
        return auth()->user()->liveSessions()
            ->with(['platformAccount.platform', 'analytics'])
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->platformFilter, function ($query) {
                $query->whereHas('platformAccount', function ($q) {
                    $q->where('platform_id', $this->platformFilter);
                });
            })
            ->when($this->dateFilter, function ($query) {
                match($this->dateFilter) {
                    'today' => $query->whereDate('scheduled_start_at', today()),
                    'this_week' => $query->whereBetween('scheduled_start_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'next_week' => $query->whereBetween('scheduled_start_at', [now()->addWeek()->startOfWeek(), now()->addWeek()->endOfWeek()]),
                    'this_month' => $query->whereMonth('scheduled_start_at', now()->month)->whereYear('scheduled_start_at', now()->year),
                    'past' => $query->where('scheduled_start_at', '<', now()),
                    default => null
                };
            })
            ->orderBy('scheduled_start_at', 'desc')
            ->paginate($this->perPage);
    }

    public function getTotalSessionsProperty()
    {
        return auth()->user()->liveSessions()->count();
    }

    public function getUpcomingSessionsProperty()
    {
        return auth()->user()->liveSessions()->upcoming()->count();
    }

    public function getLiveNowProperty()
    {
        return auth()->user()->liveSessions()->live()->count();
    }

    public function getCompletedSessionsProperty()
    {
        return auth()->user()->liveSessions()->ended()->count();
    }

    public function getPlatformsProperty()
    {
        return auth()->user()
            ->platformAccounts()
            ->with('platform')
            ->get()
            ->pluck('platform')
            ->unique('id');
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Sessions</flux:heading>
            <flux:text class="mt-2">View and manage all your live streaming sessions</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('live-host.dashboard') }}" wire:navigate>
            <div class="flex items-center">
                <flux:icon.chevron-left class="w-4 h-4 mr-1" />
                Back to Dashboard
            </div>
        </flux:button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
                    <p class="text-2xl font-semibold text-gray-900">{{ $this->completedSessions }}</p>
                    <p class="text-sm text-gray-500">Completed</p>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search sessions..."
                        icon="magnifying-glass"
                    />
                </div>

                <div>
                    <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                        <option value="">All Statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="live">Live</option>
                        <option value="ended">Ended</option>
                        <option value="cancelled">Cancelled</option>
                    </flux:select>
                </div>

                <div>
                    <flux:select wire:model.live="platformFilter" placeholder="All Platforms">
                        <option value="">All Platforms</option>
                        @foreach ($this->platforms as $platform)
                            <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:select wire:model.live="dateFilter" placeholder="All Time">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="this_week">This Week</option>
                        <option value="next_week">Next Week</option>
                        <option value="this_month">This Month</option>
                        <option value="past">Past Sessions</option>
                    </flux:select>
                </div>

                <div>
                    <flux:button variant="ghost" wire:click="clearFilters" class="w-full">
                        Clear Filters
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Sessions Table -->
    <flux:card>
        <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Session
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Platform
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Scheduled Time
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Duration
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Viewers
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($this->sessions as $session)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $session->title }}</div>
                                <div class="text-sm text-gray-500">{{ Str::limit($session->description, 40) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ $session->platformAccount->platform->name }}</div>
                                <div class="text-sm text-gray-500">{{ $session->platformAccount->account_name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    {{ $session->scheduled_start_at->format('M d, Y') }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $session->scheduled_start_at->format('h:i A') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                @if ($session->duration)
                                    {{ $session->duration }} min
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <flux:badge variant="filled" :color="$session->status_color">
                                    {{ ucfirst($session->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4">
                                @if ($session->analytics)
                                    <div class="text-sm text-gray-900">
                                        Peak: {{ number_format($session->analytics->viewers_peak) }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Avg: {{ number_format($session->analytics->viewers_avg) }}
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400">—</span>
                                @endif
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
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <flux:icon.video-camera class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                                    <p class="text-lg font-medium">No sessions found</p>
                                    <p class="text-sm">Try adjusting your search criteria</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->sessions->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $this->sessions->links() }}
            </div>
        @endif
    </flux:card>

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
