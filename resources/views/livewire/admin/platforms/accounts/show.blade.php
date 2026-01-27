<?php

use App\Jobs\SyncTikTokOrders;
use App\Jobs\SyncTikTokProducts;
use App\Models\PendingPlatformProduct;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\ProductOrder;
use App\Services\TikTok\TikTokOrderSyncService;
use App\Services\TikTok\TikTokProductSyncService;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public Platform $platform;

    public PlatformAccount $account;

    public bool $isSyncing = false;

    public ?array $lastSyncResult = null;

    public array $syncStats = [];

    // Track sync state for notifications
    public ?string $lastKnownSyncTime = null;

    // Sync progress tracking
    public bool $showProgressModal = false;

    public ?array $syncProgress = null;

    // Tab management - persisted in URL
    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    // Orders tab - persisted in URL
    #[Url(as: 'search')]
    public string $orderSearch = '';

    #[Url(as: 'status')]
    public string $orderStatus = '';

    #[Url(as: 'sort')]
    public string $orderSort = 'order_date';

    #[Url(as: 'dir')]
    public string $orderSortDir = 'desc';

    // Manual sync modal
    public bool $showSyncModal = false;

    public string $syncFromDate = '';

    public string $syncToDate = '';

    public function mount(Platform $platform, PlatformAccount $account)
    {
        $this->platform = $platform;
        $this->account = $account;

        // Ensure the account belongs to this platform
        if ($this->account->platform_id !== $this->platform->id) {
            abort(404);
        }

        // Set default date range for sync (last 30 days)
        $this->syncFromDate = now()->subDays(30)->format('Y-m-d');
        $this->syncToDate = now()->format('Y-m-d');

        $this->loadSyncStats();
    }

    public function loadSyncStats(): void
    {
        $this->syncStats = [
            'total_orders' => ProductOrder::where('platform_account_id', $this->account->id)->count(),
            'total_revenue' => ProductOrder::where('platform_account_id', $this->account->id)->sum('total_amount'),
            'pending_orders' => ProductOrder::where('platform_account_id', $this->account->id)->where('status', 'pending')->count(),
            'confirmed_orders' => ProductOrder::where('platform_account_id', $this->account->id)->where('status', 'confirmed')->count(),
            'shipped_orders' => ProductOrder::where('platform_account_id', $this->account->id)->where('status', 'shipped')->count(),
            'delivered_orders' => ProductOrder::where('platform_account_id', $this->account->id)->where('status', 'delivered')->count(),
            'completed_orders' => ProductOrder::where('platform_account_id', $this->account->id)->where('status', 'completed')->count(),
            'cancelled_orders' => ProductOrder::where('platform_account_id', $this->account->id)->where('status', 'cancelled')->count(),
            // Product sync stats
            'linked_products' => PlatformSkuMapping::where('platform_account_id', $this->account->id)->where('is_active', true)->count(),
            'pending_products' => PendingPlatformProduct::where('platform_account_id', $this->account->id)->where('status', 'pending')->count(),
        ];

        $this->lastSyncResult = $this->account->metadata['last_order_sync_result'] ?? null;

        // Initialize last known sync time if not set
        if ($this->lastKnownSyncTime === null) {
            $this->lastKnownSyncTime = $this->account->last_order_sync_at?->toIso8601String();
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function refreshStats(): void
    {
        $previousSyncTime = $this->lastKnownSyncTime;

        $this->account->refresh();
        $this->loadSyncStats();

        // Check sync progress
        $this->checkSyncProgress();

        $currentSyncTime = $this->account->last_order_sync_at?->toIso8601String();

        // Check if a new sync completed
        if ($currentSyncTime && $currentSyncTime !== $previousSyncTime) {
            $this->lastKnownSyncTime = $currentSyncTime;

            $result = $this->lastSyncResult;
            if ($result) {
                $synced = $result['synced'] ?? 0;
                $created = $result['created'] ?? 0;
                $updated = $result['updated'] ?? 0;
                $failed = $result['failed'] ?? 0;

                // Build detailed message
                $parts = [];
                if ($synced > 0) {
                    $parts[] = "{$synced} orders processed";
                }
                if ($created > 0) {
                    $parts[] = "{$created} new";
                }
                if ($updated > 0) {
                    $parts[] = "{$updated} updated";
                }
                if ($failed > 0) {
                    $parts[] = "{$failed} failed";
                }

                $detailMessage = implode(', ', $parts);

                if ($failed > 0) {
                    $this->dispatch('notify', [
                        'type' => 'warning',
                        'message' => "Sync completed with issues: {$detailMessage}",
                    ]);
                } elseif ($created === 0 && $updated === 0) {
                    $this->dispatch('notify', [
                        'type' => 'info',
                        'message' => 'Sync completed! No new or updated orders found.',
                    ]);
                } else {
                    $this->dispatch('notify', [
                        'type' => 'success',
                        'message' => "Sync completed! {$detailMessage}",
                    ]);
                }

                // Close progress modal when sync is done
                $this->showProgressModal = false;
            }
        }
    }

    public function checkSyncProgress(): void
    {
        $this->syncProgress = TikTokOrderSyncService::getSyncProgress($this->account->id);

        // Auto-close progress modal when sync completes
        if ($this->syncProgress && ($this->syncProgress['status'] ?? '') === 'completed') {
            $this->showProgressModal = false;
        }
    }

    public function openProgressModal(): void
    {
        $this->checkSyncProgress();
        $this->showProgressModal = true;
    }

    public function closeProgressModal(): void
    {
        $this->showProgressModal = false;
    }

    public function getOrdersProperty()
    {
        return ProductOrder::where('platform_account_id', $this->account->id)
            ->when($this->orderSearch, function ($query) {
                $query->where(function ($q) {
                    $q->where('platform_order_id', 'like', "%{$this->orderSearch}%")
                        ->orWhere('platform_order_number', 'like', "%{$this->orderSearch}%")
                        ->orWhere('customer_name', 'like', "%{$this->orderSearch}%")
                        ->orWhere('buyer_username', 'like', "%{$this->orderSearch}%");
                });
            })
            ->when($this->orderStatus, function ($query) {
                $query->where('status', $this->orderStatus);
            })
            ->orderBy($this->orderSort, $this->orderSortDir)
            ->paginate(15);
    }

    public function sortBy(string $column): void
    {
        if ($this->orderSort === $column) {
            $this->orderSortDir = $this->orderSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->orderSort = $column;
            $this->orderSortDir = 'desc';
        }
    }

    public function updatedOrderSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOrderStatus(): void
    {
        $this->resetPage();
    }

    public function openSyncModal(): void
    {
        $this->showSyncModal = true;
    }

    public function closeSyncModal(): void
    {
        $this->showSyncModal = false;
    }

    public function syncWithDateRange(): void
    {
        if (! $this->account->isTikTokShop()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Order sync is only available for TikTok Shop accounts.',
            ]);

            return;
        }

        if (! $this->account->is_active) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot sync orders for inactive accounts.',
            ]);

            return;
        }

        $this->isSyncing = true;

        try {
            $fromTimestamp = \Carbon\Carbon::parse($this->syncFromDate)->startOfDay()->timestamp;
            $toTimestamp = \Carbon\Carbon::parse($this->syncToDate)->endOfDay()->timestamp;

            // Dispatch to queue
            SyncTikTokOrders::dispatch($this->account, [
                'create_time_from' => $fromTimestamp,
                'create_time_to' => $toTimestamp,
            ], true);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "Sync started for {$this->syncFromDate} to {$this->syncToDate}.",
            ]);

            // Trigger polling for sync completion
            $this->dispatch('sync-started');

            $this->closeSyncModal();

            // Open progress modal
            $this->showProgressModal = true;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to start sync: '.$e->getMessage(),
            ]);
        }

        $this->isSyncing = false;
    }

    public function syncOrdersNow(): void
    {
        if (! $this->account->isTikTokShop()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Order sync is only available for TikTok Shop accounts.',
            ]);

            return;
        }

        if (! $this->account->is_active) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot sync orders for inactive accounts.',
            ]);

            return;
        }

        $this->isSyncing = true;

        try {
            // Dispatch to queue to avoid timeout and resource issues with many orders
            SyncTikTokOrders::dispatch($this->account, [
                'create_time_from' => now()->subDays(7)->timestamp,
                'create_time_to' => now()->timestamp,
            ], true); // notifyOnCompletion = true

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Quick sync started (last 7 days).',
            ]);

            // Trigger polling for sync completion
            $this->dispatch('sync-started');

            // Open progress modal
            $this->showProgressModal = true;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to start sync: '.$e->getMessage(),
            ]);
        }

        $this->isSyncing = false;
    }

    public function refreshTokens(): void
    {
        if (! $this->account->isTikTokShop()) {
            return;
        }

        try {
            $authService = app(\App\Services\TikTok\TikTokAuthService::class);
            $success = $authService->refreshToken($this->account);

            if ($success) {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Tokens refreshed successfully.',
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Failed to refresh tokens. You may need to reconnect the account.',
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Token refresh failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Disconnect this TikTok Shop account from the API.
     * This deactivates credentials and clears the shop_id/account_id fields.
     */
    public function disconnectTikTokShop(): void
    {
        if (! $this->account->isTikTokShop()) {
            return;
        }

        try {
            $authService = app(\App\Services\TikTok\TikTokAuthService::class);
            $authService->disconnectAccount($this->account);

            // Also clear the shop_id and account_id so this shop can be linked to another account
            $this->account->update([
                'shop_id' => null,
                'account_id' => null,
            ]);

            // Refresh the account model
            $this->account->refresh();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'TikTok Shop has been disconnected. You can now link this account to a different shop, or link this shop to a different account.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to disconnect: '.$e->getMessage(),
            ]);
        }
    }

    public function syncProductsNow(): void
    {
        if (! $this->account->isTikTokShop()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Product sync is only available for TikTok Shop accounts.',
            ]);

            return;
        }

        if (! $this->account->is_active) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot sync products for inactive accounts.',
            ]);

            return;
        }

        try {
            SyncTikTokProducts::dispatch($this->account, [], true);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Product sync started. This may take a few minutes.',
            ]);

            // Trigger polling for sync completion
            $this->dispatch('product-sync-started');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to start product sync: '.$e->getMessage(),
            ]);
        }
    }

    public function toggleStatus()
    {
        $this->account->update(['is_active' => ! $this->account->is_active]);

        $this->dispatch('account-updated', [
            'message' => "Account '{$this->account->name}' has been ".($this->account->is_active ? 'activated' : 'deactivated'),
        ]);
    }

    public function deleteAccount()
    {
        $accountName = $this->account->name;
        $this->account->delete();

        return redirect()->route('platforms.accounts.index', $this->platform)
            ->with('success', "Account '{$accountName}' has been deleted successfully");
    }

    public function getStatusBadgeColor(string $status): string
    {
        return match ($status) {
            'pending' => 'amber',
            'confirmed' => 'blue',
            'processing' => 'cyan',
            'shipped' => 'purple',
            'delivered' => 'green',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'zinc',
        };
    }

    /**
     * Check if the account has active OAuth credentials.
     */
    public function hasOAuthCredentials(): bool
    {
        return $this->account->credentials()
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->exists();
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
                                Platforms
                            </div>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.accounts.index', $platform)" wire:navigate class="ml-4">
                            {{ $platform->display_name }} Accounts
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">{{ $account->name }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $account->name }}</flux:heading>
            <flux:text class="mt-2">{{ $platform->display_name }} account details and management</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" icon="key" :href="route('platforms.accounts.credentials', [$platform, $account])" wire:navigate>
                API Credentials
            </flux:button>
            <flux:button variant="outline" icon="pencil" :href="route('platforms.accounts.edit', [$platform, $account])" wire:navigate>
                Edit Account
            </flux:button>
            <flux:button
                variant="{{ $account->is_active ? 'outline' : 'primary' }}"
                wire:click="toggleStatus"
                wire:confirm="Are you sure you want to {{ $account->is_active ? 'deactivate' : 'activate' }} this account?"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="{{ $account->is_active ? 'x-mark' : 'check' }}" class="w-4 h-4 mr-1" />
                    {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                </div>
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

    {{-- Tab Navigation --}}
    @if($account->isTikTokShop())
        <div class="mb-6 border-b border-gray-200 dark:border-zinc-700">
            <nav class="-mb-px flex space-x-8">
                <button
                    wire:click="setTab('overview')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Overview
                </button>
                <button
                    wire:click="setTab('orders')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $activeTab === 'orders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Orders
                    <flux:badge size="sm" color="{{ $activeTab === 'orders' ? 'blue' : 'zinc' }}">{{ number_format($syncStats['total_orders'] ?? 0) }}</flux:badge>
                </button>
                <button
                    wire:click="setTab('products')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $activeTab === 'products' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Products
                    @if(($syncStats['pending_products'] ?? 0) > 0)
                        <flux:badge size="sm" color="amber">{{ number_format($syncStats['pending_products']) }} pending</flux:badge>
                    @else
                        <flux:badge size="sm" color="{{ $activeTab === 'products' ? 'blue' : 'zinc' }}">{{ number_format($syncStats['linked_products'] ?? 0) }}</flux:badge>
                    @endif
                </button>
            </nav>
        </div>
    @endif

    {{-- Tab Content --}}
    @if($activeTab === 'overview')
        {{-- Overview Tab Content --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Account Information --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Basic Information --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Account Information</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Account Name</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $account->name }}</flux:text>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Status</flux:text>
                            @if($account->is_active)
                                <flux:badge size="sm" color="green">Active</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">Inactive</flux:badge>
                            @endif
                        </div>

                        @if($account->account_id)
                            <div>
                                <flux:text size="sm" class="text-zinc-600 mb-1">Seller ID</flux:text>
                                <flux:text size="sm" class="font-mono">{{ $account->account_id }}</flux:text>
                            </div>
                        @endif

                        @if($account->shop_id)
                            <div>
                                <flux:text size="sm" class="text-zinc-600 mb-1">Shop ID</flux:text>
                                <flux:text size="sm" class="font-mono">{{ $account->shop_id }}</flux:text>
                            </div>
                        @endif

                        @if($account->country_code)
                            <div>
                                <flux:text size="sm" class="text-zinc-600 mb-1">Country</flux:text>
                                <flux:text size="sm">{{ $account->country_code }}</flux:text>
                            </div>
                        @endif

                        @if($account->currency)
                            <div>
                                <flux:text size="sm" class="text-zinc-600 mb-1">Currency</flux:text>
                                <flux:text size="sm">{{ $account->currency }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Sync Information --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Sync Information</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Auto Sync Orders</flux:text>
                            @if($account->auto_sync_orders)
                                <flux:badge size="sm" color="green">Enabled</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">Manual Only</flux:badge>
                            @endif
                        </div>

                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Connected At</flux:text>
                            <flux:text size="sm">
                                @if($account->connected_at)
                                    {{ $account->connected_at->format('M j, Y \a\t g:i A') }}
                                @else
                                    <span class="text-zinc-400">Not connected</span>
                                @endif
                            </flux:text>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Last Order Sync</flux:text>
                            <flux:text size="sm">
                                @if($account->last_order_sync_at)
                                    {{ $account->last_order_sync_at->diffForHumans() }}
                                @else
                                    <span class="text-zinc-400">Never synced</span>
                                @endif
                            </flux:text>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Quick Actions --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                    <div class="space-y-3">
                        {{-- Link to TikTok Shop button for accounts without OAuth --}}
                        @if($account->isTikTokShop() && !$this->hasOAuthCredentials())
                            <div class="mb-4 pb-4 border-b border-gray-200 dark:border-zinc-600">
                                <div class="flex items-center gap-2 mb-3">
                                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500" />
                                    <flux:text size="sm" class="text-amber-600 dark:text-amber-400 font-medium">API Not Connected</flux:text>
                                </div>
                                <flux:text size="xs" class="text-zinc-500 mb-3">
                                    Connect this account to TikTok Shop API to enable automatic order sync and other features.
                                </flux:text>
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    class="w-full"
                                    :href="route('tiktok.connect', ['link_account' => $account->id])"
                                >
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="link" class="w-4 h-4 mr-2" />
                                        Link to TikTok Shop
                                    </div>
                                </flux:button>
                            </div>
                        @endif

                        @if($account->isTikTokShop())
                            <flux:button
                                variant="primary"
                                size="sm"
                                class="w-full"
                                wire:click="syncOrdersNow"
                                wire:loading.attr="disabled"
                                :disabled="$isSyncing || !$account->is_active"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="arrow-path" class="w-4 h-4 mr-2" wire:loading.class="animate-spin" />
                                    <span wire:loading.remove>Quick Sync (Last 7 Days)</span>
                                    <span wire:loading>Syncing...</span>
                                </div>
                            </flux:button>

                            <flux:button
                                variant="outline"
                                size="sm"
                                class="w-full"
                                wire:click="openSyncModal"
                                :disabled="!$account->is_active"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="calendar" class="w-4 h-4 mr-2" />
                                    Manual Sync with Date Range
                                </div>
                            </flux:button>

                            <flux:button
                                variant="outline"
                                size="sm"
                                class="w-full"
                                wire:click="refreshTokens"
                                wire:loading.attr="disabled"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="key" class="w-4 h-4 mr-2" />
                                    Refresh API Tokens
                                </div>
                            </flux:button>

                            {{-- Disconnect TikTok Shop - only show if OAuth credentials exist --}}
                            @if($this->hasOAuthCredentials())
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    class="w-full text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 border-red-200 dark:border-red-800"
                                    wire:click="disconnectTikTokShop"
                                    wire:confirm="Are you sure you want to disconnect this TikTok Shop? This will deactivate the API credentials and allow you to link this account to a different shop."
                                    wire:loading.attr="disabled"
                                >
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="link-slash" class="w-4 h-4 mr-2" />
                                        Disconnect TikTok Shop
                                    </div>
                                </flux:button>
                            @endif
                        @endif

                        <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.credentials', [$platform, $account])" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="key" class="w-4 h-4 mr-2" />
                                Manage API Credentials
                            </div>
                        </flux:button>
                    </div>
                </div>

                {{-- Account Stats --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Account Stats</flux:heading>
                        <flux:button variant="ghost" size="xs" wire:click="refreshStats" wire:loading.attr="disabled">
                            <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" />
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-zinc-600">Total Orders</flux:text>
                            <flux:text size="sm" class="font-medium">{{ number_format($syncStats['total_orders'] ?? 0) }}</flux:text>
                        </div>

                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-zinc-600">Pending</flux:text>
                            <flux:badge size="sm" color="amber">{{ number_format($syncStats['pending_orders'] ?? 0) }}</flux:badge>
                        </div>

                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-zinc-600">Confirmed</flux:text>
                            <flux:badge size="sm" color="blue">{{ number_format($syncStats['confirmed_orders'] ?? 0) }}</flux:badge>
                        </div>

                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-zinc-600">Shipped</flux:text>
                            <flux:badge size="sm" color="purple">{{ number_format($syncStats['shipped_orders'] ?? 0) }}</flux:badge>
                        </div>

                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-zinc-600">Completed</flux:text>
                            <flux:badge size="sm" color="green">{{ number_format($syncStats['completed_orders'] ?? 0) }}</flux:badge>
                        </div>

                        <div class="flex justify-between pt-3 border-t border-gray-100 dark:border-zinc-700">
                            <flux:text size="sm" class="text-zinc-600">Total Revenue</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $account->currency ?? 'MYR' }} {{ number_format($syncStats['total_revenue'] ?? 0, 2) }}</flux:text>
                        </div>
                    </div>
                </div>

                @if($account->isTikTokShop() && $lastSyncResult)
                    {{-- Last Sync Result --}}
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                        <flux:heading size="lg" class="mb-4">Last Sync Result</flux:heading>

                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">Synced</flux:text>
                                <flux:badge size="sm" color="blue">{{ $lastSyncResult['synced'] ?? 0 }}</flux:badge>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">Created</flux:text>
                                <flux:badge size="sm" color="green">{{ $lastSyncResult['created'] ?? 0 }}</flux:badge>
                            </div>
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">Updated</flux:text>
                                <flux:badge size="sm" color="amber">{{ $lastSyncResult['updated'] ?? 0 }}</flux:badge>
                            </div>
                            @if(($lastSyncResult['failed'] ?? 0) > 0)
                                <div class="flex justify-between">
                                    <flux:text size="sm" class="text-zinc-600">Failed</flux:text>
                                    <flux:badge size="sm" color="red">{{ $lastSyncResult['failed'] }}</flux:badge>
                                </div>
                            @endif

                            @if($account->last_order_sync_at)
                                <div class="pt-2 border-t border-gray-100 dark:border-zinc-700">
                                    <flux:text size="xs" class="text-zinc-500">
                                        Last synced {{ $account->last_order_sync_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Danger Zone --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-red-200 dark:border-red-700 p-6">
                    <flux:heading size="lg" class="mb-4 text-red-600">Danger Zone</flux:heading>

                    <flux:button
                        variant="danger"
                        size="sm"
                        class="w-full"
                        wire:click="deleteAccount"
                        wire:confirm="Are you sure you want to delete this account? This action cannot be undone and will remove all associated data."
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="trash" class="w-4 h-4 mr-2" />
                            Delete Account
                        </div>
                    </flux:button>
                </div>
            </div>
        </div>
    @elseif($activeTab === 'orders')
        {{-- Orders Tab Content --}}
        <div class="space-y-6">
            {{-- Orders Header with Sync Button --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        {{-- Search --}}
                        <div class="relative">
                            <flux:input
                                type="text"
                                wire:model.live.debounce.300ms="orderSearch"
                                placeholder="Search orders..."
                                class="w-full md:w-64"
                            />
                        </div>

                        {{-- Status Filter --}}
                        <flux:select wire:model.live="orderStatus" class="w-full md:w-40">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </flux:select>
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- Last Sync Info --}}
                        @if($account->last_order_sync_at || $lastSyncResult)
                            <div class="hidden md:flex items-center gap-2 text-sm text-zinc-500 border-r border-gray-200 dark:border-zinc-700 pr-3">
                                @if($lastSyncResult)
                                    <div class="flex items-center gap-1">
                                        <flux:badge size="sm" color="green">{{ $lastSyncResult['created'] ?? 0 }} new</flux:badge>
                                        <flux:badge size="sm" color="amber">{{ $lastSyncResult['updated'] ?? 0 }} updated</flux:badge>
                                        @if(($lastSyncResult['failed'] ?? 0) > 0)
                                            <flux:badge size="sm" color="red">{{ $lastSyncResult['failed'] }} failed</flux:badge>
                                        @endif
                                    </div>
                                @endif
                                @if($account->last_order_sync_at)
                                    <span class="text-xs">{{ $account->last_order_sync_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        @endif

                        <flux:button
                            variant="outline"
                            size="sm"
                            wire:click="refreshStats"
                            wire:loading.attr="disabled"
                        >
                            <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" />
                        </flux:button>

                        <flux:button
                            variant="primary"
                            size="sm"
                            wire:click="openSyncModal"
                            :disabled="!$account->is_active"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                                Sync Orders
                            </div>
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Last Sync Result Banner --}}
            @if($lastSyncResult)
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-full">
                                    <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <flux:text size="sm" class="font-medium">Last Sync Completed</flux:text>
                                    @if($account->last_order_sync_at)
                                        <flux:text size="xs" class="text-zinc-500">{{ $account->last_order_sync_at->format('M j, Y \a\t g:i A') }}</flux:text>
                                    @endif
                                </div>
                            </div>

                            <div class="hidden sm:flex items-center gap-4 pl-4 border-l border-gray-200 dark:border-zinc-700">
                                <div class="text-center">
                                    <flux:text size="lg" class="font-bold text-blue-600">{{ $lastSyncResult['synced'] ?? 0 }}</flux:text>
                                    <flux:text size="xs" class="text-zinc-500">Total Synced</flux:text>
                                </div>
                                <div class="text-center">
                                    <flux:text size="lg" class="font-bold text-green-600">{{ $lastSyncResult['created'] ?? 0 }}</flux:text>
                                    <flux:text size="xs" class="text-zinc-500">New Orders</flux:text>
                                </div>
                                <div class="text-center">
                                    <flux:text size="lg" class="font-bold text-amber-600">{{ $lastSyncResult['updated'] ?? 0 }}</flux:text>
                                    <flux:text size="xs" class="text-zinc-500">Updated</flux:text>
                                </div>
                                @if(($lastSyncResult['failed'] ?? 0) > 0)
                                    <div class="text-center">
                                        <flux:text size="lg" class="font-bold text-red-600">{{ $lastSyncResult['failed'] }}</flux:text>
                                        <flux:text size="xs" class="text-zinc-500">Failed</flux:text>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <flux:button variant="ghost" size="xs" wire:click="refreshStats">
                            <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshStats" />
                        </flux:button>
                    </div>

                    {{-- Mobile view for sync stats --}}
                    <div class="sm:hidden mt-3 pt-3 border-t border-gray-200 dark:border-zinc-700">
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div>
                                <flux:text size="sm" class="font-bold text-blue-600">{{ $lastSyncResult['synced'] ?? 0 }}</flux:text>
                                <flux:text size="xs" class="text-zinc-500">Synced</flux:text>
                            </div>
                            <div>
                                <flux:text size="sm" class="font-bold text-green-600">{{ $lastSyncResult['created'] ?? 0 }}</flux:text>
                                <flux:text size="xs" class="text-zinc-500">New</flux:text>
                            </div>
                            <div>
                                <flux:text size="sm" class="font-bold text-amber-600">{{ $lastSyncResult['updated'] ?? 0 }}</flux:text>
                                <flux:text size="xs" class="text-zinc-500">Updated</flux:text>
                            </div>
                            @if(($lastSyncResult['failed'] ?? 0) > 0)
                                <div>
                                    <flux:text size="sm" class="font-bold text-red-600">{{ $lastSyncResult['failed'] }}</flux:text>
                                    <flux:text size="xs" class="text-zinc-500">Failed</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Show errors if any --}}
                    @if(!empty($lastSyncResult['errors']))
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-zinc-700">
                            <flux:text size="xs" class="text-red-600 font-medium mb-1">Sync Errors:</flux:text>
                            <div class="text-xs text-red-500 max-h-20 overflow-y-auto space-y-1">
                                @foreach(array_slice($lastSyncResult['errors'], 0, 3) as $error)
                                    @php
                                        // Extract just the order ID and simplified error
                                        $shortError = $error;
                                        if (preg_match('/Order (\d+):/', $error, $matches)) {
                                            $orderId = $matches[1];
                                            if (str_contains($error, 'database is locked')) {
                                                $shortError = "Order {$orderId}: Database temporarily busy (retry failed)";
                                            } elseif (str_contains($error, 'SQLSTATE')) {
                                                $shortError = "Order {$orderId}: Database error";
                                            } else {
                                                $shortError = \Illuminate\Support\Str::limit($error, 80);
                                            }
                                        }
                                    @endphp
                                    <div>• {{ $shortError }}</div>
                                @endforeach
                                @if(count($lastSyncResult['errors']) > 3)
                                    <div class="text-zinc-500">... and {{ count($lastSyncResult['errors']) - 3 }} more errors</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Orders Table --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead class="bg-gray-50 dark:bg-zinc-900">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer" wire:click="sortBy('platform_order_id')">
                                    <div class="flex items-center gap-1">
                                        Order ID
                                        @if($orderSort === 'platform_order_id')
                                            <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer" wire:click="sortBy('status')">
                                    <div class="flex items-center gap-1">
                                        Status
                                        @if($orderSort === 'status')
                                            <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer" wire:click="sortBy('total_amount')">
                                    <div class="flex items-center gap-1">
                                        Total
                                        @if($orderSort === 'total_amount')
                                            <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer" wire:click="sortBy('order_date')">
                                    <div class="flex items-center gap-1">
                                        Order Date
                                        @if($orderSort === 'order_date')
                                            <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                            @forelse($this->orders as $order)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">
                                            {{ $order->platform_order_number ?: $order->platform_order_id }}
                                        </div>
                                        @if($order->tracking_id)
                                            <div class="text-xs text-gray-500 dark:text-zinc-400">
                                                Track: {{ $order->tracking_id }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-zinc-100">{{ $order->customer_name ?: 'N/A' }}</div>
                                        @if($order->buyer_username)
                                            <div class="text-xs text-gray-500 dark:text-zinc-400">@{{ $order->buyer_username }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge size="sm" color="{{ $this->getStatusBadgeColor($order->status) }}">
                                            {{ ucfirst($order->status) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-zinc-100">
                                        {{ $order->currency ?? 'MYR' }} {{ number_format($order->total_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                                        @if($order->order_date)
                                            {{ $order->order_date->format('M j, Y') }}
                                            <div class="text-xs">{{ $order->order_date->format('g:i A') }}</div>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <flux:button variant="ghost" size="xs" :href="route('platforms.orders.show', [$platform, $order])" wire:navigate>
                                            <flux:icon name="eye" class="w-4 h-4" />
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <flux:icon name="inbox" class="w-12 h-12 mx-auto text-gray-400 mb-4" />
                                        <flux:text class="text-gray-500 dark:text-zinc-400">No orders found</flux:text>
                                        @if($account->isTikTokShop() && $account->is_active)
                                            <flux:button variant="outline" size="sm" class="mt-4" wire:click="openSyncModal">
                                                Sync Orders from TikTok
                                            </flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($this->orders->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                        {{ $this->orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    @elseif($activeTab === 'products')
        {{-- Products Tab Content --}}
        <div class="space-y-6">
            {{-- Products Header --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <flux:heading size="lg">Product Synchronization</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">Import products from TikTok Shop and link them to your internal catalog</flux:text>
                    </div>

                    <div class="flex items-center gap-3">
                        @if($account->last_product_sync_at)
                            <flux:text size="sm" class="text-zinc-500">
                                Last synced {{ $account->last_product_sync_at->diffForHumans() }}
                            </flux:text>
                        @endif

                        <flux:button
                            variant="primary"
                            size="sm"
                            wire:click="syncProductsNow"
                            wire:loading.attr="disabled"
                            :disabled="!$account->is_active"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="arrow-path" class="w-4 h-4 mr-2" wire:loading.class="animate-spin" wire:target="syncProductsNow" />
                                <span wire:loading.remove wire:target="syncProductsNow">Sync Products</span>
                                <span wire:loading wire:target="syncProductsNow">Syncing...</span>
                            </div>
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Product Stats Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                            <flux:icon name="link" class="w-5 h-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-zinc-100">{{ number_format($syncStats['linked_products'] ?? 0) }}</div>
                            <flux:text size="sm" class="text-zinc-500">Linked Products</flux:text>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                            <flux:icon name="clock" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-zinc-100">{{ number_format($syncStats['pending_products'] ?? 0) }}</div>
                            <flux:text size="sm" class="text-zinc-500">Pending Review</flux:text>
                        </div>
                    </div>
                </div>

                @php
                    $lastProductSync = $account->metadata['last_product_sync_result'] ?? null;
                @endphp

                @if($lastProductSync)
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <flux:icon name="sparkles" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-zinc-100">{{ number_format($lastProductSync['auto_linked'] ?? 0) }}</div>
                                <flux:text size="sm" class="text-zinc-500">Auto-Linked</flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                <flux:icon name="document-check" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-zinc-100">{{ number_format($lastProductSync['total'] ?? 0) }}</div>
                                <flux:text size="sm" class="text-zinc-500">Last Sync Total</flux:text>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Pending Products Alert --}}
            @if(($syncStats['pending_products'] ?? 0) > 0)
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:icon name="exclamation-triangle" class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                            <div>
                                <p class="font-medium text-amber-800 dark:text-amber-200">{{ $syncStats['pending_products'] }} products need review</p>
                                <p class="text-sm text-amber-600 dark:text-amber-400">These TikTok products couldn't be auto-matched and need manual linking.</p>
                            </div>
                        </div>
                        <flux:button variant="primary" size="sm" :href="route('platforms.accounts.pending-products', [$platform, $account])" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="eye" class="w-4 h-4 mr-2" />
                                Review Products
                            </div>
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- How It Works --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">How Product Sync Works</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mb-3">
                            <flux:icon name="arrow-down-tray" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <h4 class="font-medium text-gray-900 dark:text-zinc-100 mb-1">1. Import</h4>
                        <p class="text-sm text-zinc-500">Products are fetched from your TikTok Shop</p>
                    </div>

                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto bg-purple-100 dark:bg-purple-900/30 rounded-full flex items-center justify-center mb-3">
                            <flux:icon name="sparkles" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <h4 class="font-medium text-gray-900 dark:text-zinc-100 mb-1">2. Smart Match</h4>
                        <p class="text-sm text-zinc-500">Products are auto-matched by SKU, barcode, or name</p>
                    </div>

                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-3">
                            <flux:icon name="link" class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <h4 class="font-medium text-gray-900 dark:text-zinc-100 mb-1">3. Link</h4>
                        <p class="text-sm text-zinc-500">Review unmatched products and link or create new ones</p>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:button variant="outline" class="justify-start" :href="route('platforms.accounts.pending-products', [$platform, $account])" wire:navigate>
                        <div class="flex items-center">
                            <flux:icon name="clock" class="w-5 h-5 mr-3 text-amber-600" />
                            <div class="text-left">
                                <p class="font-medium">Review Pending Products</p>
                                <p class="text-sm text-zinc-500">Link or create products from TikTok imports</p>
                            </div>
                        </div>
                    </flux:button>

                    <flux:button variant="outline" class="justify-start" :href="route('platforms.sku-mappings.index', ['platform_account' => $account->id])" wire:navigate>
                        <div class="flex items-center">
                            <flux:icon name="document-text" class="w-5 h-5 mr-3 text-blue-600" />
                            <div class="text-left">
                                <p class="font-medium">Manage SKU Mappings</p>
                                <p class="text-sm text-zinc-500">View and edit product link configurations</p>
                            </div>
                        </div>
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Manual Sync Modal --}}
    <flux:modal wire:model="showSyncModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Manual Order Sync</flux:heading>
            <flux:text class="mb-6 text-zinc-600">Select a date range to sync orders from TikTok Shop.</flux:text>

            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label>From Date</flux:label>
                        <flux:input type="date" wire:model="syncFromDate" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>To Date</flux:label>
                        <flux:input type="date" wire:model="syncToDate" :max="now()->format('Y-m-d')" />
                    </flux:field>
                </div>

                <flux:callout variant="info" class="text-sm">
                    <flux:callout.heading>Note</flux:callout.heading>
                    <flux:callout.text>
                        Syncing is performed in the background. Large date ranges may take longer to complete.
                    </flux:callout.text>
                </flux:callout>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeSyncModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="syncWithDateRange" wire:loading.attr="disabled">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-path" class="w-4 h-4 mr-2" wire:loading.class="animate-spin" />
                        <span wire:loading.remove>Start Sync</span>
                        <span wire:loading>Starting...</span>
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Sync Progress Modal --}}
    @if($showProgressModal)
    <flux:modal wire:model="showProgressModal" class="max-w-lg" :dismissible="false">
        <div class="p-6" @if($syncProgress && ($syncProgress['status'] ?? '') === 'syncing') wire:poll.1s="checkSyncProgress" @endif>
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/30 mb-4">
                    <flux:icon name="arrow-path" class="w-8 h-8 text-blue-600 dark:text-blue-400 animate-spin" />
                </div>
                <flux:heading size="lg">Syncing Orders</flux:heading>
                <flux:text class="mt-2 text-zinc-600">Please wait while we sync your TikTok orders...</flux:text>
            </div>

            @if($syncProgress)
                {{-- Progress Bar --}}
                <div class="mb-6">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-zinc-600 dark:text-zinc-400">Progress</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $syncProgress['percentage'] ?? 0 }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-zinc-700 rounded-full h-3 overflow-hidden">
                        <div
                            class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-500 ease-out"
                            style="width: {{ $syncProgress['percentage'] ?? 0 }}%"
                        ></div>
                    </div>
                </div>

                {{-- Stats Grid --}}
                <div class="grid grid-cols-4 gap-3 mb-6">
                    <div class="text-center p-3 bg-gray-50 dark:bg-zinc-800 rounded-lg">
                        <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $syncProgress['processed'] ?? 0 }}</div>
                        <div class="text-xs text-zinc-500">of {{ $syncProgress['total'] ?? 0 }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">Processed</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $syncProgress['created'] ?? 0 }}</div>
                        <div class="text-xs text-green-600 dark:text-green-400 mt-1">New</div>
                    </div>
                    <div class="text-center p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $syncProgress['updated'] ?? 0 }}</div>
                        <div class="text-xs text-amber-600 dark:text-amber-400 mt-1">Updated</div>
                    </div>
                    <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $syncProgress['failed'] ?? 0 }}</div>
                        <div class="text-xs text-red-600 dark:text-red-400 mt-1">Failed</div>
                    </div>
                </div>

                {{-- Current Order --}}
                @if(($syncProgress['status'] ?? '') === 'syncing' && ($syncProgress['current_order'] ?? null))
                    <div class="text-center text-sm text-zinc-500 dark:text-zinc-400 mb-4">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                            Processing order: <span class="font-mono">{{ Str::limit($syncProgress['current_order'], 20) }}</span>
                        </span>
                    </div>
                @endif

                {{-- Status Message --}}
                @if(($syncProgress['status'] ?? '') === 'completed')
                    <div class="text-center">
                        <div class="inline-flex items-center gap-2 text-green-600 dark:text-green-400 font-medium">
                            <flux:icon name="check-circle" class="w-5 h-5" />
                            Sync completed successfully!
                        </div>
                    </div>
                @endif
            @else
                {{-- Loading state before progress data is available --}}
                <div class="text-center py-8">
                    <div class="inline-flex items-center gap-2 text-zinc-500">
                        <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                        <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse" style="animation-delay: 0.2s"></span>
                        <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse" style="animation-delay: 0.4s"></span>
                        <span class="ml-2">Fetching orders from TikTok...</span>
                    </div>
                </div>
            @endif

            <div class="mt-6 flex justify-center">
                <flux:button variant="ghost" wire:click="closeProgressModal">
                    Run in Background
                </flux:button>
            </div>
        </div>
    </flux:modal>
    @endif

    {{-- Toast Notification --}}
    <div
        x-data="{
            show: false,
            message: '',
            type: 'success',
            timeout: null
        }"
        x-on:notify.window="
            message = $event.detail.message;
            type = $event.detail.type || 'success';
            show = true;
            clearTimeout(timeout);
            timeout = setTimeout(() => show = false, 6000)
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div
            :class="{
                'bg-green-500': type === 'success',
                'bg-red-500': type === 'error',
                'bg-amber-500': type === 'warning',
                'bg-blue-500': type === 'info'
            }"
            class="text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 max-w-md"
        >
            {{-- Success icon --}}
            <svg x-show="type === 'success'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            {{-- Error icon --}}
            <svg x-show="type === 'error'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            {{-- Warning icon --}}
            <svg x-show="type === 'warning'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            {{-- Info icon --}}
            <svg x-show="type === 'info'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <span x-text="message" class="flex-1 text-sm font-medium"></span>
            <button @click="show = false" class="ml-2 hover:opacity-75 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Alpine polling for sync completion detection - more controlled --}}
    <div
        x-data="{
            polling: false,
            pollInterval: null,
            pollTimeout: null,
            startPolling() {
                this.polling = true;
                if (this.pollInterval) clearInterval(this.pollInterval);
                if (this.pollTimeout) clearTimeout(this.pollTimeout);

                this.pollInterval = setInterval(() => {
                    if (this.polling) {
                        $wire.refreshStats();
                    }
                }, 5000);

                // Auto-stop after 2 minutes
                this.pollTimeout = setTimeout(() => this.stopPolling(), 120000);
            },
            stopPolling() {
                this.polling = false;
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
                if (this.pollTimeout) {
                    clearTimeout(this.pollTimeout);
                    this.pollTimeout = null;
                }
            }
        }"
        x-on:sync-started.window="startPolling()"
        x-on:notify.window="if ($event.detail.type === 'success' || $event.detail.type === 'warning') stopPolling()"
    ></div>
</div>
