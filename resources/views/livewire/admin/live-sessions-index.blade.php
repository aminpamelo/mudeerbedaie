<?php

use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $platformFilter = '';

    public $accountFilter = '';

    public $dateFilter = '';

    public $sourceFilter = '';

    public $perPage = 20;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->platformFilter = '';
        $this->accountFilter = '';
        $this->dateFilter = '';
        $this->sourceFilter = '';
        $this->resetPage();
    }

    public function getSessionsProperty()
    {
        return LiveSession::query()
            ->with(['platformAccount.platform', 'liveHost', 'analytics', 'liveSchedule'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('platformAccount', function ($pa) {
                            $pa->where('name', 'like', '%'.$this->search.'%');
                        })
                        ->orWhereHas('liveHost', function ($lh) {
                            $lh->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->platformFilter, function ($query) {
                $query->whereHas('platformAccount', function ($q) {
                    $q->where('platform_id', $this->platformFilter);
                });
            })
            ->when($this->accountFilter, function ($query) {
                $query->where('platform_account_id', $this->accountFilter);
            })
            ->when($this->dateFilter, function ($query) {
                $query->whereDate('scheduled_start_at', $this->dateFilter);
            })
            ->when($this->sourceFilter, function ($query) {
                match ($this->sourceFilter) {
                    // Admin = no schedule OR schedule created by someone other than the host
                    'admin' => $query->where(function ($q) {
                        $q->whereNull('live_schedule_id')
                            ->orWhereHas('liveSchedule', function ($ls) {
                                $ls->where(function ($lsq) {
                                    $lsq->whereNull('created_by')
                                        ->orWhereColumn('created_by', '!=', 'live_host_id');
                                });
                            });
                    }),
                    // Self = schedule exists AND created by the same host
                    'self' => $query->whereHas('liveSchedule', function ($ls) {
                        $ls->whereColumn('created_by', '=', 'live_host_id');
                    }),
                    default => null
                };
            })
            ->latest('scheduled_start_at')
            ->paginate($this->perPage);
    }

    public function getPlatformsProperty()
    {
        return Platform::active()->ordered()->get();
    }

    public function getAccountsProperty()
    {
        return PlatformAccount::query()
            ->with(['platform', 'user'])
            ->active()
            ->orderBy('name')
            ->get();
    }
}
?>

<div>
    <x-slot:title>Live Sessions</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Live Sessions</flux:heading>
            <flux:text class="mt-2">View all scheduled and past streaming sessions</flux:text>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex gap-3 items-center">
        <div class="w-72">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by title, host, or account..."
                icon="magnifying-glass"
            />
        </div>

        <div class="w-40">
            <flux:select wire:model.live="statusFilter" placeholder="All Status">
                <option value="">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="live">Live Now</option>
                <option value="ended">Ended</option>
                <option value="cancelled">Cancelled</option>
            </flux:select>
        </div>

        <div class="w-44">
            <flux:select wire:model.live="platformFilter" placeholder="All Platforms">
                <option value="">All Platforms</option>
                @foreach($this->platforms as $platform)
                    <option value="{{ $platform->id }}">{{ $platform->display_name }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-56">
            <flux:select wire:model.live="accountFilter" placeholder="All Accounts">
                <option value="">All Accounts</option>
                @foreach($this->accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-40">
            <flux:input
                type="date"
                wire:model.live="dateFilter"
                placeholder="Filter by date"
            />
        </div>

        <div class="w-40">
            <flux:select wire:model.live="sourceFilter" placeholder="All Sources">
                <option value="">All Sources</option>
                <option value="admin">Admin Assigned</option>
                <option value="self">Self Schedule</option>
            </flux:select>
        </div>

        @if($search || $statusFilter || $platformFilter || $accountFilter || $dateFilter || $sourceFilter)
            <flux:button variant="ghost" wire:click="clearFilters" size="sm">
                Clear
            </flux:button>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sessions</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ LiveSession::count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Upcoming</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                {{ LiveSession::scheduled()->where('scheduled_start_at', '>', now())->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Live Now</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">
                {{ LiveSession::live()->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed</div>
            <div class="text-2xl font-bold text-gray-600 dark:text-gray-400 mt-1">
                {{ LiveSession::ended()->count() }}
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Session
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Host
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Platform
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Scheduled Time
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Duration
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
                    @forelse($this->sessions as $session)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ $session->title }}
                                        </span>
                                        @if($session->isAdminAssigned())
                                            <flux:badge variant="outline" color="blue" size="sm">Admin</flux:badge>
                                        @else
                                            <flux:badge variant="outline" color="purple" size="sm">Self</flux:badge>
                                        @endif
                                    </div>
                                    @if($session->description)
                                        <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                            {{ Str::limit($session->description, 50) }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <div class="text-gray-900 dark:text-white">{{ $session->liveHost?->name ?? 'N/A' }}</div>
                                    <div class="text-gray-500 dark:text-gray-400">{{ $session->platformAccount?->name ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="outline" color="blue">
                                    {{ $session->platformAccount?->platform?->display_name ?? 'N/A' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <div class="text-gray-900 dark:text-white">{{ $session->scheduled_start_at->format('M d, Y') }}</div>
                                    <div class="text-gray-500 dark:text-gray-400">{{ $session->scheduled_start_at->format('h:i A') }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    @if($session->duration)
                                        {{ $session->duration }} min
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge :color="$session->status_color">
                                    {{ ucfirst($session->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:button variant="ghost" size="sm" href="{{ route('admin.live-sessions.show', $session) }}">
                                    View
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                @if($search || $statusFilter || $platformFilter || $accountFilter || $dateFilter)
                                    No sessions found matching your filters.
                                @else
                                    No sessions yet. Create a schedule to auto-generate sessions.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($this->sessions->hasPages())
        <div class="mt-4">
            {{ $this->sessions->links() }}
        </div>
    @endif
</div>
