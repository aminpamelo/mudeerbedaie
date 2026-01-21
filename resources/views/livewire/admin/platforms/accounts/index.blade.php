<?php

use App\Models\Platform;
use App\Models\PlatformAccount;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Platform $platform;

    public $search = '';

    public $statusFilter = '';

    public $sortBy = 'created_at';

    public $sortDirection = 'desc';

    public function mount(Platform $platform)
    {
        $this->platform = $platform;
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function toggleStatus($accountId)
    {
        $account = PlatformAccount::findOrFail($accountId);
        $account->update(['is_active' => ! $account->is_active]);

        $this->dispatch('account-updated', [
            'message' => "Account '{$account->name}' has been ".($account->is_active ? 'activated' : 'deactivated'),
        ]);
    }

    public function deleteAccount($accountId)
    {
        $account = PlatformAccount::findOrFail($accountId);
        $accountName = $account->name;
        $account->delete();

        $this->dispatch('account-deleted', [
            'message' => "Account '{$accountName}' has been deleted successfully",
        ]);
    }

    public function with()
    {
        $query = $this->platform->accounts()->with('liveHosts');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('account_id', 'like', "%{$this->search}%")
                    ->orWhere('shop_id', 'like', "%{$this->search}%")
                    ->orWhere('business_manager_id', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $accounts = $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(12);

        return [
            'accounts' => $accounts,
            'totalAccounts' => $this->platform->accounts()->count(),
            'activeAccounts' => $this->platform->accounts()->where('is_active', true)->count(),
            'connectedAccounts' => $this->platform->accounts()->whereNotNull('last_sync_at')->count(),
        ];
    }
}; ?>

<div>
    {{-- Breadcrumb Navigation --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <flux:button variant="ghost" size="sm" :href="route('platforms.index')" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                                Back to Platforms
                            </div>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">{{ $platform->display_name }} Accounts</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $platform->display_name }} - Account Centre</flux:heading>
            <flux:text class="mt-2">Manage seller accounts, shop IDs, and business manager connections for {{ $platform->display_name }}</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" icon="plus" :href="route('platforms.accounts.create', $platform)" wire:navigate>
                Add Account
            </flux:button>
            <flux:button variant="primary" icon="arrow-down-tray">
                Export Accounts
            </flux:button>
        </div>
    </div>

    {{-- Platform Info Card --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="flex items-center space-x-4">
            @if($platform->logo_url)
                <img src="{{ $platform->logo_url }}" alt="{{ $platform->name }}" class="w-12 h-12 rounded-lg">
            @else
                <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white text-xl font-bold"
                     style="background: {{ $platform->color_primary ?? '#6b7280' }}">
                    {{ substr($platform->name, 0, 1) }}
                </div>
            @endif
            <div class="flex-1">
                <flux:heading size="sm">{{ $platform->display_name }}</flux:heading>
                <flux:text size="sm" class="text-zinc-600">{{ ucfirst(str_replace('_', ' ', $platform->type)) }} Platform</flux:text>
                <div class="flex items-center mt-2 space-x-4">
                    @if($platform->is_active)
                        <flux:badge size="sm" color="green">Active</flux:badge>
                    @else
                        <flux:badge size="sm" color="red">Inactive</flux:badge>
                    @endif
                    @if($platform->settings['api_available'] ?? false)
                        <flux:badge size="sm" color="blue">API Available</flux:badge>
                    @else
                        <flux:badge size="sm" color="amber">Manual Only</flux:badge>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-zinc-600">Total Accounts</flux:text>
                    <flux:heading size="lg">{{ $totalAccounts }}</flux:heading>
                </div>
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <flux:icon name="user-group" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Active Accounts</flux:text>
                    <flux:heading size="lg">{{ $activeAccounts }}</flux:heading>
                </div>
                <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <flux:icon name="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Last Synced</flux:text>
                    <flux:heading size="lg">{{ $connectedAccounts }}</flux:heading>
                </div>
                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="flex flex-col lg:flex-row gap-4">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search accounts by name, seller ID, shop ID..."
                    icon="magnifying-glass"
                />
            </div>

            <div class="flex gap-3">
                <flux:select wire:model.live="statusFilter" placeholder="All Status">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                </flux:select>

                @if($search || $statusFilter)
                    <flux:button
                        variant="outline"
                        wire:click="$set('search', ''); $set('statusFilter', '')"
                        icon="x-mark"
                        size="sm"
                    >
                        Clear
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Accounts Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($accounts as $account)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden hover:shadow-lg transition-shadow">
                {{-- Account Header --}}
                <div class="p-4 border-b border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-700/50">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="sm">{{ $account->name }}</flux:heading>
                            <flux:text size="xs" class="text-zinc-600">
                                Created {{ $account->created_at->format('M j, Y') }}
                            </flux:text>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($account->is_active)
                                <flux:badge size="sm" color="green">Active</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">Inactive</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Account Details --}}
                <div class="p-4">
                    {{-- Live Hosts --}}
                    @if($account->liveHosts->isNotEmpty())
                        <div class="mb-4 pb-4 border-b">
                            <flux:text size="xs" class="text-zinc-600 mb-2">Live Hosts:</flux:text>
                            <div class="flex flex-wrap gap-1">
                                @foreach($account->liveHosts as $host)
                                    <flux:badge size="sm" color="blue">{{ $host->name }}</flux:badge>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Account IDs --}}
                    <div class="space-y-2 mb-4">
                        @if($account->account_id)
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-600">Seller ID:</span>
                                <span class="font-mono text-zinc-900">{{ $account->account_id }}</span>
                            </div>
                        @endif

                        @if($account->shop_id)
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-600">Shop ID:</span>
                                <span class="font-mono text-zinc-900">{{ $account->shop_id }}</span>
                            </div>
                        @endif

                        @if($account->business_manager_id)
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-600">Business Manager:</span>
                                <span class="font-mono text-zinc-900">{{ $account->business_manager_id }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Connection Status --}}
                    <div class="mb-4">
                        @if($account->last_sync_at)
                            <div class="flex items-center text-green-600">
                                <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                <flux:text size="xs">Last synced {{ $account->last_sync_at->diffForHumans() }}</flux:text>
                            </div>
                        @else
                            <div class="flex items-center text-amber-600">
                                <flux:icon name="exclamation-triangle" class="w-4 h-4 mr-1" />
                                <flux:text size="xs">Never synced</flux:text>
                            </div>
                        @endif
                    </div>

                    {{-- Account Notes --}}
                    @if($account->description)
                        <div class="mb-4">
                            <flux:text size="xs" class="text-zinc-500 mb-1">Notes:</flux:text>
                            <flux:text size="xs" class="text-zinc-700 line-clamp-2">{{ $account->description }}</flux:text>
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="px-4 py-3 bg-gray-50 dark:bg-zinc-700/50 border-t border-gray-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div class="flex space-x-2">
                            <flux:button
                                size="sm"
                                variant="outline"
                                :href="route('platforms.accounts.show', [$platform, $account])"
                                wire:navigate
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                    View
                                </div>
                            </flux:button>

                            <flux:button
                                size="sm"
                                variant="outline"
                                :href="route('platforms.accounts.edit', [$platform, $account])"
                                wire:navigate
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                                    Edit
                                </div>
                            </flux:button>
                        </div>

                        <div class="flex space-x-2">
                            <flux:button
                                size="sm"
                                variant="{{ $account->is_active ? 'outline' : 'primary' }}"
                                wire:click="toggleStatus({{ $account->id }})"
                                wire:confirm="Are you sure you want to {{ $account->is_active ? 'deactivate' : 'activate' }} this account?"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="{{ $account->is_active ? 'x-mark' : 'check' }}" class="w-4 h-4 mr-1" />
                                    {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                                </div>
                            </flux:button>

                            <flux:button
                                size="sm"
                                variant="danger"
                                wire:click="deleteAccount({{ $account->id }})"
                                wire:confirm="Are you sure you want to delete this account? This action cannot be undone."
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="trash" class="w-4 h-4" />
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="text-center py-12">
                    <flux:icon name="user-group" class="w-12 h-12 text-zinc-400 mx-auto mb-4" />
                    <flux:heading size="lg" class="text-zinc-600 mb-2">No accounts found</flux:heading>
                    <flux:text class="text-zinc-500 mb-4">
                        @if($search || $statusFilter)
                            Try adjusting your search criteria or filters.
                        @else
                            Get started by adding your first {{ $platform->display_name }} account.
                        @endif
                    </flux:text>
                    @if(!$search && !$statusFilter)
                        <flux:button variant="primary" :href="route('platforms.accounts.create', $platform)" wire:navigate>
                            Add Your First Account
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($accounts->hasPages())
        <div class="mt-6">
            {{ $accounts->links() }}
        </div>
    @endif
</div>