<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $perPage = 15;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function deleteHost($hostId)
    {
        $host = User::findOrFail($hostId);

        if ($host->platformAccounts()->count() > 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot delete live host with connected platform accounts.'
            ]);
            return;
        }

        $host->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Live host deleted successfully.'
        ]);
    }

    public function getLiveHostsProperty()
    {
        return User::query()
            ->where('role', 'live_host')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->withCount(['platformAccounts', 'liveSessions'])
            ->latest()
            ->paginate($this->perPage);
    }
}
?>

<div>
    <x-slot:title>Live Hosts</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Live Hosts</flux:heading>
            <flux:text class="mt-2">Manage users with live streaming access</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('admin.live-hosts.create') }}">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                Create Live Host
            </div>
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex gap-4">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name, email, or phone..."
                icon="magnifying-glass"
            />
        </div>

        <flux:select wire:model.live="statusFilter" placeholder="All Status">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
        </flux:select>

        @if($search || $statusFilter)
            <flux:button variant="ghost" wire:click="clearFilters">
                Clear Filters
            </flux:button>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Live Hosts</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ User::where('role', 'live_host')->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Hosts</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">
                {{ User::where('role', 'live_host')->where('status', 'active')->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Platform Accounts</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                {{ \App\Models\PlatformAccount::whereHas('user', fn($q) => $q->where('role', 'live_host'))->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Live Sessions Today</div>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                {{ \App\Models\LiveSession::whereDate('scheduled_start_at', today())->count() }}
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
                            Live Host
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Contact
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Platform Accounts
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Total Sessions
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->liveHosts as $host)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :initials="$host->initials()" />
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $host->name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            ID: #{{ $host->id }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <div class="text-gray-900 dark:text-white">{{ $host->email }}</div>
                                    @if($host->phone)
                                        <div class="text-gray-500 dark:text-gray-400">{{ $host->phone }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="outline" color="blue">
                                    {{ $host->platform_accounts_count }} account{{ $host->platform_accounts_count != 1 ? 's' : '' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $host->live_sessions_count }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge :color="$host->getStatusColor()">
                                    {{ ucfirst($host->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button variant="ghost" size="sm" href="{{ route('admin.live-hosts.show', $host) }}">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                            View
                                        </div>
                                    </flux:button>
                                    <flux:button variant="ghost" size="sm" href="{{ route('admin.live-hosts.edit', $host) }}">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                                            Edit
                                        </div>
                                    </flux:button>
                                    <flux:button variant="ghost" size="sm" color="red" wire:click="deleteHost({{ $host->id }})" wire:confirm="Are you sure you want to delete this live host?">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="trash" class="w-4 h-4 mr-1" />
                                            Delete
                                        </div>
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                @if($search || $statusFilter)
                                    No live hosts found matching your filters.
                                @else
                                    No live hosts yet. Add a user with the "Live Host" role to get started.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($this->liveHosts->hasPages())
        <div class="mt-4">
            {{ $this->liveHosts->links() }}
        </div>
    @endif
</div>
