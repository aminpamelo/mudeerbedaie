<?php

use App\Jobs\SyncTikTokAffiliates;
use App\Jobs\SyncTikTokAnalytics;
use App\Jobs\SyncTikTokFinance;
use App\Jobs\SyncTikTokOrders;
use App\Jobs\SyncTikTokProducts;
use App\Models\PendingPlatformProduct;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApp;
use App\Models\PlatformSkuMapping;
use App\Models\ProductOrder;
use App\Models\TiktokAffiliateOrder;
use App\Models\TiktokCreator;
use App\Models\TiktokFinanceStatement;
use App\Models\TiktokFinanceTransaction;
use App\Models\TiktokProductPerformance;
use App\Models\TiktokShopPerformanceSnapshot;
use App\Services\TikTok\TikTokOrderSyncService;
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

    public ?string $lastKnownSyncTime = null;

    public bool $showProgressModal = false;

    public ?array $syncProgress = null;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    #[Url(as: 'search')]
    public string $orderSearch = '';

    #[Url(as: 'status')]
    public string $orderStatus = '';

    #[Url(as: 'sort')]
    public string $orderSort = 'order_date';

    #[Url(as: 'dir')]
    public string $orderSortDir = 'desc';

    public bool $showSyncModal = false;

    public string $syncFromDate = '';

    public string $syncToDate = '';

    public function mount(Platform $platform, PlatformAccount $account)
    {
        $this->platform = $platform;
        $this->account = $account;

        if ($this->account->platform_id !== $this->platform->id) {
            abort(404);
        }

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
            'linked_products' => PlatformSkuMapping::where('platform_account_id', $this->account->id)->where('is_active', true)->count(),
            'pending_products' => PendingPlatformProduct::where('platform_account_id', $this->account->id)->where('status', 'pending')->count(),
            'analytics_snapshots' => TiktokShopPerformanceSnapshot::where('platform_account_id', $this->account->id)->count(),
            'product_performance' => TiktokProductPerformance::where('platform_account_id', $this->account->id)->count(),
            'creators' => TiktokCreator::where('platform_account_id', $this->account->id)->count(),
            'affiliate_orders' => TiktokAffiliateOrder::where('platform_account_id', $this->account->id)->count(),
            'finance_statements' => TiktokFinanceStatement::where('platform_account_id', $this->account->id)->count(),
            'finance_transactions' => TiktokFinanceTransaction::where('platform_account_id', $this->account->id)->count(),
        ];

        $this->lastSyncResult = $this->account->metadata['last_order_sync_result'] ?? null;

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

        $this->checkSyncProgress();

        $currentSyncTime = $this->account->last_order_sync_at?->toIso8601String();

        if ($currentSyncTime && $currentSyncTime !== $previousSyncTime) {
            $this->lastKnownSyncTime = $currentSyncTime;

            $result = $this->lastSyncResult;
            if ($result) {
                $synced = $result['synced'] ?? 0;
                $created = $result['created'] ?? 0;
                $updated = $result['updated'] ?? 0;
                $failed = $result['failed'] ?? 0;

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

                $this->showProgressModal = false;
            }
        }
    }

    public function checkSyncProgress(): void
    {
        $this->syncProgress = TikTokOrderSyncService::getSyncProgress($this->account->id);

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

            SyncTikTokOrders::dispatch($this->account, [
                'create_time_from' => $fromTimestamp,
                'create_time_to' => $toTimestamp,
            ], true);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "Sync started for {$this->syncFromDate} to {$this->syncToDate}.",
            ]);

            $this->dispatch('sync-started');

            $this->closeSyncModal();

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
            SyncTikTokOrders::dispatch($this->account, [
                'create_time_from' => now()->subDays(7)->timestamp,
                'create_time_to' => now()->timestamp,
            ], true);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Quick sync started (last 7 days).',
            ]);

            $this->dispatch('sync-started');

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

    public function disconnectTikTokShop(): void
    {
        if (! $this->account->isTikTokShop()) {
            return;
        }

        try {
            $authService = app(\App\Services\TikTok\TikTokAuthService::class);
            $authService->disconnectAccount($this->account);

            $this->account->update([
                'shop_id' => null,
                'account_id' => null,
            ]);

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

            $this->dispatch('product-sync-started');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to start product sync: '.$e->getMessage(),
            ]);
        }
    }

    public function syncFinanceNow(): void
    {
        $this->dispatchTikTokSyncJob(
            job: SyncTikTokFinance::class,
            label: 'Finance',
            notFoundMsg: 'Finance sync is only available for TikTok Shop accounts.',
        );
    }

    public function syncAnalyticsNow(): void
    {
        $this->dispatchTikTokSyncJob(
            job: SyncTikTokAnalytics::class,
            label: 'Analytics',
            notFoundMsg: 'Analytics sync is only available for TikTok Shop accounts.',
        );
    }

    public function syncAffiliatesNow(): void
    {
        $this->dispatchTikTokSyncJob(
            job: SyncTikTokAffiliates::class,
            label: 'Affiliate',
            notFoundMsg: 'Affiliate sync is only available for TikTok Shop accounts.',
        );
    }

    private function dispatchTikTokSyncJob(string $job, string $label, string $notFoundMsg): void
    {
        if (! $this->account->isTikTokShop()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $notFoundMsg]);

            return;
        }

        if (! $this->account->is_active) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => "Cannot sync {$label} for inactive accounts.",
            ]);

            return;
        }

        try {
            $job::dispatch($this->account);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "{$label} sync queued. It will run in the background.",
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => "Failed to queue {$label} sync: ".$e->getMessage(),
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

    public function getStatusPillClass(string $status): string
    {
        return match ($status) {
            'pending' => 'warn',
            'confirmed' => 'info',
            'processing' => 'info',
            'shipped' => 'info',
            'delivered' => 'active',
            'completed' => 'active',
            'cancelled' => 'danger',
            default => 'idle',
        };
    }

    public function hasOAuthCredentials(): bool
    {
        return $this->account->credentials()
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->exists();
    }

    public function with(): array
    {
        return [
            'apps' => PlatformApp::where('platform_id', $this->platform->id)
                ->where('is_active', true)
                ->orderBy('category')
                ->get(),
            'credentialsByAppId' => $this->account->credentials()
                ->with('platformApp')
                ->whereNotNull('platform_app_id')
                ->where('credential_type', 'oauth_token')
                ->where('is_active', true)
                ->get()
                ->keyBy('platform_app_id'),
        ];
    }
}; ?>

<div>
    {{-- Page-scoped fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&family=jetbrains-mono:400,500&display=swap" rel="stylesheet">

    <style>
        .pf-page,
        .pf-page * { font-family: 'Manrope', ui-sans-serif, system-ui, -apple-system, sans-serif; }
        .pf-page .num,
        .pf-page .mono,
        .pf-page code { font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace; font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; letter-spacing: -0.01em; }

        .pf-page { color-scheme: light dark; }

        .pf-surface { background: #ffffff; border: 1px solid rgb(228 228 231 / 1); border-radius: 14px; transition: all .2s cubic-bezier(.2,.7,.2,1); }
        .dark .pf-surface { background: rgb(9 9 11 / 1); border-color: rgb(39 39 42 / 1); }

        .pf-card { transition: all .25s cubic-bezier(.2,.7,.2,1); }
        .pf-card:hover { border-color: rgb(212 212 216); box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 8px 24px -8px rgb(0 0 0 / 0.08); }
        .dark .pf-card:hover { border-color: rgb(63 63 70); box-shadow: 0 1px 2px rgb(0 0 0 / 0.5), 0 8px 24px -4px rgb(0 0 0 / 0.6); }

        /* Section heading */
        .section-h { font-size: 13px; font-weight: 600; letter-spacing: -0.005em; color: rgb(9 9 11); }
        .dark .section-h { color: #fff; }
        .section-eyebrow { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: rgb(113 113 122); }
        .dark .section-eyebrow { color: rgb(161 161 170); }

        /* Pill tabs (segmented horizontally scrollable) */
        .pf-tabs { display: inline-flex; align-items: center; gap: 2px; padding: 3px; border-radius: 11px; background: rgb(244 244 245); }
        .dark .pf-tabs { background: rgb(24 24 27); }
        .pf-tab {
            display: inline-flex; align-items: center; gap: 7px;
            height: 34px; padding: 0 14px;
            border-radius: 8px;
            font-size: 13px; font-weight: 500; line-height: 1;
            color: rgb(82 82 91); background: transparent;
            transition: all .15s ease; cursor: pointer; border: none; white-space: nowrap;
        }
        .dark .pf-tab { color: rgb(161 161 170); }
        .pf-tab:hover { color: rgb(24 24 27); }
        .dark .pf-tab:hover { color: rgb(244 244 245); }
        .pf-tab[aria-pressed="true"] { color: rgb(24 24 27); background: #ffffff; box-shadow: 0 1px 2px rgb(0 0 0 / .08), 0 0 0 1px rgb(228 228 231); }
        .dark .pf-tab[aria-pressed="true"] { color: #fff; background: rgb(39 39 42); box-shadow: 0 0 0 1px rgb(63 63 70); }
        .pf-tab .count {
            font-family: 'JetBrains Mono', monospace; font-size: 10.5px; font-weight: 500;
            padding: 2px 6px; border-radius: 4px; background: rgb(228 228 231); color: rgb(82 82 91);
            font-variant-numeric: tabular-nums; line-height: 1;
        }
        .dark .pf-tab .count { background: rgb(39 39 42); color: rgb(161 161 170); }
        .pf-tab[aria-pressed="true"] .count { background: rgb(24 24 27); color: #fff; }
        .dark .pf-tab[aria-pressed="true"] .count { background: rgb(82 82 91); color: #fff; }
        .pf-tab .count.warn { background: rgb(254 243 199); color: rgb(146 64 14); }
        .dark .pf-tab .count.warn { background: rgb(120 53 15 / .35); color: rgb(252 211 77); }

        /* Status pill */
        .stat-pill {
            display: inline-flex; align-items: center; gap: 6px;
            height: 22px; padding: 0 8px; border-radius: 999px;
            font-size: 11px; font-weight: 600; letter-spacing: -0.005em;
            border: 1px solid transparent;
        }
        .stat-pill.active   { background: rgb(220 252 231); color: rgb(21 128 61); border-color: rgb(187 247 208); }
        .stat-pill.idle     { background: rgb(244 244 245); color: rgb(82 82 91); border-color: rgb(228 228 231); }
        .stat-pill.warn     { background: rgb(254 243 199); color: rgb(146 64 14); border-color: rgb(253 230 138); }
        .stat-pill.info     { background: rgb(219 234 254); color: rgb(29 78 216); border-color: rgb(191 219 254); }
        .stat-pill.danger   { background: rgb(254 226 226); color: rgb(185 28 28); border-color: rgb(254 202 202); }
        .stat-pill.purple   { background: rgb(243 232 255); color: rgb(126 34 206); border-color: rgb(233 213 255); }
        .dark .stat-pill.active { background: rgb(20 83 45 / .25); color: rgb(134 239 172); border-color: rgb(22 101 52 / .5); }
        .dark .stat-pill.idle   { background: rgb(39 39 42); color: rgb(161 161 170); border-color: rgb(63 63 70); }
        .dark .stat-pill.warn   { background: rgb(120 53 15 / .25); color: rgb(252 211 77); border-color: rgb(146 64 14 / .5); }
        .dark .stat-pill.info   { background: rgb(30 58 138 / .25); color: rgb(147 197 253); border-color: rgb(30 64 175 / .5); }
        .dark .stat-pill.danger { background: rgb(127 29 29 / .25); color: rgb(252 165 165); border-color: rgb(153 27 27 / .5); }
        .dark .stat-pill.purple { background: rgb(88 28 135 / .25); color: rgb(216 180 254); border-color: rgb(107 33 168 / .5); }
        .stat-pill .ring { width: 6px; height: 6px; border-radius: 999px; background: currentColor; opacity: .9; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            height: 36px; padding: 0 14px;
            border-radius: 9px;
            font-size: 13px; font-weight: 500; line-height: 1;
            transition: all .15s ease; cursor: pointer; border: none; text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary { background: rgb(9 9 11); color: #fff; }
        .btn-primary:hover { background: rgb(39 39 42); }
        .dark .btn-primary { background: #fff; color: rgb(9 9 11); }
        .dark .btn-primary:hover { background: rgb(228 228 231); }
        .btn-secondary { background: #fff; color: rgb(24 24 27); border: 1px solid rgb(228 228 231); }
        .btn-secondary:hover { border-color: rgb(161 161 170); }
        .dark .btn-secondary { background: rgb(9 9 11); color: #fff; border-color: rgb(39 39 42); }
        .dark .btn-secondary:hover { border-color: rgb(82 82 91); }
        .btn-ghost { background: transparent; color: rgb(82 82 91); }
        .btn-ghost:hover { background: rgb(244 244 245); color: rgb(24 24 27); }
        .dark .btn-ghost { color: rgb(161 161 170); }
        .dark .btn-ghost:hover { background: rgb(39 39 42); color: #fff; }
        .btn-danger-ghost { background: transparent; color: rgb(220 38 38); }
        .btn-danger-ghost:hover { background: rgb(254 226 226); color: rgb(185 28 28); }
        .dark .btn-danger-ghost { color: rgb(248 113 113); }
        .dark .btn-danger-ghost:hover { background: rgb(127 29 29 / .4); color: rgb(252 165 165); }
        .btn-danger { background: rgb(220 38 38); color: #fff; }
        .btn-danger:hover { background: rgb(185 28 28); }
        .btn-sm { height: 30px; padding: 0 11px; font-size: 12.5px; border-radius: 7px; }
        .btn-icon { width: 32px; padding: 0; }
        .btn-brand {
            background: linear-gradient(180deg, rgb(24 24 27), rgb(9 9 11));
            color: #fff;
            box-shadow: inset 0 1px 0 rgb(255 255 255 / .1);
        }
        .btn-brand:hover { background: linear-gradient(180deg, rgb(39 39 42), rgb(24 24 27)); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }

        /* Field row */
        .field-block { display: flex; flex-direction: column; gap: 4px; }
        .field-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; color: rgb(113 113 122); }
        .dark .field-label { color: rgb(161 161 170); }
        .field-value { font-size: 13.5px; font-weight: 500; color: rgb(24 24 27); }
        .dark .field-value { color: rgb(228 228 231); }
        .field-value.muted { color: rgb(161 161 170); font-weight: 400; }
        .field-value.mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12.5px; font-weight: 400; }

        /* Logo wrapper */
        .pf-logo { width: 44px; height: 44px; border-radius: 11px; display: grid; place-items: center; color: #fff; font-weight: 600; font-size: 16px; letter-spacing: -0.02em; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgb(0 0 0 / .04); position: relative; overflow: hidden; }
        .pf-logo::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.18), transparent 50%); pointer-events: none; }

        /* Platform banner */
        .pf-banner { position: relative; overflow: hidden; }
        .pf-banner::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--accent, #71717a);
        }

        /* Back link */
        .back-link {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 13px; font-weight: 500;
            color: rgb(113 113 122); text-decoration: none;
            transition: color .15s ease;
        }
        .back-link:hover { color: rgb(24 24 27); }
        .dark .back-link { color: rgb(161 161 170); }
        .dark .back-link:hover { color: #fff; }

        /* Reveal */
        .reveal { opacity: 0; transform: translateY(6px); animation: reveal .5s cubic-bezier(.2,.7,.2,1) forwards; }
        .reveal.d1 { animation-delay: .04s; } .reveal.d2 { animation-delay: .10s; } .reveal.d3 { animation-delay: .16s; } .reveal.d4 { animation-delay: .22s; }
        @keyframes reveal { to { opacity: 1; transform: none; } }

        /* Stats stack — sidebar style */
        .stat-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px dashed rgb(244 244 245); }
        .dark .stat-row { border-bottom-color: rgb(39 39 42); }
        .stat-row:last-child { border-bottom: none; }
        .stat-row .lbl { font-size: 12.5px; color: rgb(82 82 91); }
        .dark .stat-row .lbl { color: rgb(161 161 170); }
        .stat-row .val { font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; color: rgb(24 24 27); font-variant-numeric: tabular-nums; }
        .dark .stat-row .val { color: rgb(244 244 245); }

        /* Tables (clean modern) */
        .pf-table { width: 100%; font-size: 13px; }
        .pf-table thead th {
            padding: 11px 16px; text-align: left;
            font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
            color: rgb(113 113 122);
            background: rgb(250 250 250); border-bottom: 1px solid rgb(228 228 231);
        }
        .dark .pf-table thead th { color: rgb(161 161 170); background: rgb(24 24 27); border-bottom-color: rgb(39 39 42); }
        .pf-table tbody td { padding: 13px 16px; border-bottom: 1px solid rgb(244 244 245); color: rgb(39 39 42); }
        .dark .pf-table tbody td { border-bottom-color: rgb(39 39 42 / .6); color: rgb(228 228 231); }
        .pf-table tbody tr:last-child td { border-bottom: none; }
        .pf-table tbody tr { transition: background-color .12s ease; }
        .pf-table tbody tr:hover { background: rgb(250 250 250); }
        .dark .pf-table tbody tr:hover { background: rgb(24 24 27 / .5); }
        .pf-table .text-right { text-align: right; }

        /* Search input */
        .pf-search {
            width: 100%; height: 38px; padding: 0 12px 0 36px;
            background: #fff; border: 1px solid rgb(228 228 231); border-radius: 9px;
            font-size: 13px; color: rgb(24 24 27);
            transition: all .15s ease;
        }
        .dark .pf-search { background: rgb(9 9 11); border-color: rgb(39 39 42); color: #fff; }
        .pf-search::placeholder { color: rgb(161 161 170); }
        .pf-search:focus { outline: none; border-color: rgb(82 82 91); box-shadow: 0 0 0 4px rgb(244 244 245); }
        .dark .pf-search:focus { border-color: rgb(113 113 122); box-shadow: 0 0 0 4px rgb(39 39 42 / .8); }
        .pf-select {
            height: 38px; padding: 0 32px 0 12px;
            background: #fff; border: 1px solid rgb(228 228 231); border-radius: 9px;
            font-size: 13px; color: rgb(63 63 70);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center;
            appearance: none;
        }
        .dark .pf-select { background-color: rgb(9 9 11); border-color: rgb(39 39 42); color: rgb(228 228 231); }

        /* KPI mini card (analytics) */
        .kpi { padding: 16px; border-radius: 12px; background: rgb(250 250 250); border: 1px solid rgb(244 244 245); }
        .dark .kpi { background: rgb(24 24 27 / .5); border-color: rgb(39 39 42); }
        .kpi .lbl { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: .07em; color: rgb(113 113 122); }
        .dark .kpi .lbl { color: rgb(161 161 170); }
        .kpi .val { font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 600; color: rgb(9 9 11); margin-top: 6px; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
        .dark .kpi .val { color: #fff; }
        .kpi .val.emerald { color: rgb(5 150 105); }
        .kpi .val.blue { color: rgb(37 99 235); }
        .dark .kpi .val.emerald { color: rgb(52 211 153); }
        .dark .kpi .val.blue { color: rgb(96 165 250); }

        /* Sync mini stat */
        .sync-stat { padding: 12px 14px; border-radius: 10px; background: rgb(250 250 250); border: 1px solid rgb(244 244 245); text-align: center; }
        .dark .sync-stat { background: rgb(24 24 27 / .5); border-color: rgb(39 39 42); }
        .sync-stat .lbl { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: rgb(113 113 122); }
        .dark .sync-stat .lbl { color: rgb(161 161 170); }
        .sync-stat .val { font-family: 'JetBrains Mono', monospace; font-size: 20px; font-weight: 700; line-height: 1.1; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .sync-stat.success .val { color: rgb(5 150 105); }
        .sync-stat.warn .val    { color: rgb(217 119 6); }
        .sync-stat.danger .val  { color: rgb(220 38 38); }
        .sync-stat.neutral .val { color: rgb(24 24 27); }
        .dark .sync-stat.neutral .val { color: #fff; }
        .dark .sync-stat.success .val { color: rgb(52 211 153); }
        .dark .sync-stat.warn .val    { color: rgb(252 211 77); }
        .dark .sync-stat.danger .val  { color: rgb(248 113 113); }

        /* Inline alert */
        .pf-alert { display: flex; gap: 14px; padding: 16px 18px; border-radius: 12px; }
        .pf-alert.warn { background: rgb(254 252 232); border: 1px solid rgb(254 240 138); color: rgb(146 64 14); }
        .dark .pf-alert.warn { background: rgb(120 53 15 / .15); border-color: rgb(146 64 14 / .4); color: rgb(252 211 77); }
        .pf-alert.danger { background: rgb(254 242 242); border: 1px solid rgb(254 202 202); }
        .dark .pf-alert.danger { background: rgb(127 29 29 / .15); border-color: rgb(153 27 27 / .4); }
        .pf-alert.info { background: rgb(239 246 255); border: 1px solid rgb(191 219 254); color: rgb(30 64 175); }
        .dark .pf-alert.info { background: rgb(30 58 138 / .15); border-color: rgb(30 64 175 / .4); color: rgb(147 197 253); }

        /* Step card (how it works) */
        .step-card { padding: 18px; border-radius: 12px; background: rgb(250 250 250); border: 1px solid rgb(244 244 245); }
        .dark .step-card { background: rgb(24 24 27 / .5); border-color: rgb(39 39 42); }
        .step-card .num { font-size: 11px; font-weight: 700; color: rgb(113 113 122); letter-spacing: 0.1em; }
        .dark .step-card .num { color: rgb(161 161 170); }
        .step-card h4 { font-size: 14px; font-weight: 600; color: rgb(9 9 11); margin: 6px 0 4px; }
        .dark .step-card h4 { color: #fff; }
        .step-card p { font-size: 12.5px; color: rgb(113 113 122); line-height: 1.5; }
        .dark .step-card p { color: rgb(161 161 170); }

        /* Empty state */
        .empty-state { padding: 56px 24px; text-align: center; }
        .empty-state .icon { width: 44px; height: 44px; margin: 0 auto 14px; border-radius: 12px; background: rgb(244 244 245); display: grid; place-items: center; color: rgb(113 113 122); }
        .dark .empty-state .icon { background: rgb(39 39 42); color: rgb(161 161 170); }
        .empty-state h3 { font-size: 14px; font-weight: 600; color: rgb(24 24 27); }
        .dark .empty-state h3 { color: #fff; }
        .empty-state p { font-size: 12.5px; color: rgb(113 113 122); margin-top: 6px; }
        .dark .empty-state p { color: rgb(161 161 170); }

        /* Modal */
        .pf-modal-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 50px -10px rgb(0 0 0 / .25); }
        .dark .pf-modal-card { background: rgb(9 9 11); border: 1px solid rgb(39 39 42); }

        /* Sync progress bar */
        .pf-progress { height: 8px; background: rgb(244 244 245); border-radius: 999px; overflow: hidden; }
        .dark .pf-progress { background: rgb(39 39 42); }
        .pf-progress > i { display: block; height: 100%; background: linear-gradient(90deg, rgb(16 185 129), rgb(52 211 153)); border-radius: 999px; transition: width .4s ease; }

        /* ── Toast ──────────────────────────────────────────── */
        .pf-toast-host { position: fixed; bottom: 20px; right: 20px; z-index: 200; pointer-events: none; }
        .pf-toast {
            position: relative;
            display: flex; align-items: flex-start; gap: 12px;
            min-width: 320px; max-width: 420px;
            padding: 12px 14px 12px 14px;
            background: #ffffff;
            border: 1px solid rgb(228 228 231);
            border-radius: 12px;
            box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 12px 32px -8px rgb(0 0 0 / .14);
            overflow: hidden;
            pointer-events: auto;
        }
        .dark .pf-toast { background: rgb(9 9 11); border-color: rgb(39 39 42); box-shadow: 0 1px 2px rgb(0 0 0 / .5), 0 16px 40px -10px rgb(0 0 0 / .6); }
        .pf-toast::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
            background: rgb(113 113 122);
        }
        .pf-toast.success::before { background: linear-gradient(180deg, rgb(16 185 129), rgb(5 150 105)); }
        .pf-toast.error::before   { background: linear-gradient(180deg, rgb(239 68 68), rgb(220 38 38)); }
        .pf-toast.warning::before { background: linear-gradient(180deg, rgb(245 158 11), rgb(217 119 6)); }
        .pf-toast.info::before    { background: linear-gradient(180deg, rgb(59 130 246), rgb(37 99 235)); }

        .pf-toast .ic {
            width: 28px; height: 28px; flex-shrink: 0; border-radius: 8px;
            display: grid; place-items: center;
            margin-left: 4px;
        }
        .pf-toast.success .ic { background: rgb(220 252 231); color: rgb(5 150 105); }
        .pf-toast.error .ic   { background: rgb(254 226 226); color: rgb(220 38 38); }
        .pf-toast.warning .ic { background: rgb(254 243 199); color: rgb(217 119 6); }
        .pf-toast.info .ic    { background: rgb(219 234 254); color: rgb(37 99 235); }
        .dark .pf-toast.success .ic { background: rgb(20 83 45 / .35); color: rgb(134 239 172); }
        .dark .pf-toast.error .ic   { background: rgb(127 29 29 / .35); color: rgb(252 165 165); }
        .dark .pf-toast.warning .ic { background: rgb(120 53 15 / .35); color: rgb(252 211 77); }
        .dark .pf-toast.info .ic    { background: rgb(30 58 138 / .35); color: rgb(147 197 253); }

        .pf-toast .msg {
            flex: 1; min-width: 0;
            font-size: 13px; font-weight: 500; line-height: 1.45;
            color: rgb(24 24 27); padding-top: 5px;
        }
        .dark .pf-toast .msg { color: rgb(244 244 245); }

        .pf-toast .close {
            width: 22px; height: 22px; flex-shrink: 0;
            display: grid; place-items: center;
            border-radius: 6px;
            color: rgb(161 161 170); background: transparent;
            border: none; cursor: pointer;
            transition: all .15s ease;
            margin-top: 2px;
        }
        .pf-toast .close:hover { color: rgb(24 24 27); background: rgb(244 244 245); }
        .dark .pf-toast .close:hover { color: #fff; background: rgb(39 39 42); }

        /* Auto-dismiss progress bar */
        .pf-toast .bar {
            position: absolute; left: 0; right: 0; bottom: 0; height: 2px;
            background: rgb(244 244 245);
            overflow: hidden;
        }
        .dark .pf-toast .bar { background: rgb(39 39 42); }
        .pf-toast .bar > i {
            display: block; height: 100%; width: 100%;
            transform-origin: left center;
            animation: pf-toast-bar 6s linear forwards;
        }
        .pf-toast.success .bar > i { background: rgb(16 185 129); }
        .pf-toast.error .bar > i   { background: rgb(239 68 68); }
        .pf-toast.warning .bar > i { background: rgb(245 158 11); }
        .pf-toast.info .bar > i    { background: rgb(59 130 246); }
        @keyframes pf-toast-bar { from { transform: scaleX(1); } to { transform: scaleX(0); } }
    </style>

    @php
        $accent = $platform->color_primary ?: '#52525b';
        $hasApi = $platform->settings['api_available'] ?? false;
    @endphp

    <div class="pf-page">

        {{-- ─────────────── Back link ─────────────── --}}
        <div class="reveal d1 mb-5">
            <a href="{{ route('platforms.accounts.index', $platform) }}" wire:navigate class="back-link">
                <flux:icon name="chevron-left" class="w-3.5 h-3.5" />
                {{ $platform->display_name }} accounts
            </a>
        </div>

        {{-- ─────────────── Hero ─────────────── --}}
        <header class="reveal d1 mb-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 mb-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background: {{ $accent }};"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full" style="background: {{ $accent }};"></span>
                    </span>
                    Account · {{ $platform->display_name }}
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-[30px] sm:text-[36px] font-semibold tracking-[-0.035em] leading-[1.1] text-zinc-900 dark:text-white">
                        {{ $account->name }}
                    </h1>
                    <span class="stat-pill {{ $account->is_active ? 'active' : 'idle' }}">
                        <span class="ring"></span>{{ $account->is_active ? 'Active' : 'Idle' }}
                    </span>
                    @if($account->isTikTokShop())
                        <span class="stat-pill {{ $this->hasOAuthCredentials() ? 'info' : 'warn' }}">
                            @if($this->hasOAuthCredentials())
                                <flux:icon name="bolt" class="w-3 h-3" /> API Linked
                            @else
                                <flux:icon name="link-slash" class="w-3 h-3" /> Not Linked
                            @endif
                        </span>
                    @endif
                </div>
                <p class="mt-2 text-[14px] text-zinc-500 dark:text-zinc-400 leading-relaxed">
                    {{ $platform->display_name }} account details, sync controls and connected data.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('platforms.accounts.credentials', [$platform, $account]) }}" wire:navigate class="btn btn-ghost">
                    <flux:icon name="key" class="w-4 h-4" /> Credentials
                </a>
                <a href="{{ route('platforms.accounts.edit', [$platform, $account]) }}" wire:navigate class="btn btn-secondary">
                    <flux:icon name="pencil" class="w-4 h-4" /> Edit
                </a>
                <button
                    type="button"
                    wire:click="toggleStatus"
                    wire:confirm="Are you sure you want to {{ $account->is_active ? 'deactivate' : 'activate' }} this account?"
                    class="btn {{ $account->is_active ? 'btn-secondary' : 'btn-primary' }}"
                >
                    @if($account->is_active)
                        <flux:icon name="pause" class="w-4 h-4" /> Deactivate
                    @else
                        <flux:icon name="play" class="w-4 h-4" /> Activate
                    @endif
                </button>
            </div>
        </header>

        {{-- ─────────────── Platform info banner ─────────────── --}}
        <div class="reveal d2 mb-6 pf-surface pf-banner pl-5 pr-4 py-4 flex items-center gap-4" style="--accent: {{ $accent }};">
            @if($platform->logo_url)
                <img src="{{ $platform->logo_url }}" alt="" class="pf-logo object-cover" style="background:{{ $accent }};">
            @else
                <div class="pf-logo" style="background:{{ $accent }};">
                    {{ strtoupper(substr($platform->display_name ?? $platform->name, 0, 2)) }}
                </div>
            @endif
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-[15px] font-semibold tracking-[-0.01em] text-zinc-900 dark:text-white">{{ $platform->display_name }}</h2>
                    <span class="stat-pill {{ $platform->is_active ? 'active' : 'idle' }}">
                        <span class="ring"></span>{{ $platform->is_active ? 'Active' : 'Idle' }}
                    </span>
                    <span class="stat-pill {{ $hasApi ? 'info' : 'warn' }}">
                        @if($hasApi)
                            <flux:icon name="bolt" class="w-3 h-3" /> API Available
                        @else
                            <flux:icon name="cursor-arrow-rays" class="w-3 h-3" /> Manual Only
                        @endif
                    </span>
                </div>
                <div class="mt-1 text-[12.5px] text-zinc-500 dark:text-zinc-400 capitalize">
                    {{ str_replace('_', ' ', $platform->type) }} platform
                </div>
            </div>
            <a href="{{ route('platforms.show', $platform) }}" wire:navigate class="btn btn-ghost btn-sm">
                <flux:icon name="arrow-top-right-on-square" class="w-3.5 h-3.5" /> Open
            </a>
        </div>

        {{-- ─────────────── Tab navigation ─────────────── --}}
        @if($account->isTikTokShop())
            <div class="reveal d3 mb-6 overflow-x-auto -mx-1 px-1">
                <div class="pf-tabs">
                    <button type="button" wire:click="setTab('overview')"    class="pf-tab" aria-pressed="{{ $activeTab === 'overview' ? 'true' : 'false' }}">Overview</button>
                    <button type="button" wire:click="setTab('connections')" class="pf-tab" aria-pressed="{{ $activeTab === 'connections' ? 'true' : 'false' }}">Connections</button>
                    <button type="button" wire:click="setTab('orders')"      class="pf-tab" aria-pressed="{{ $activeTab === 'orders' ? 'true' : 'false' }}">Orders <span class="count">{{ number_format($syncStats['total_orders'] ?? 0) }}</span></button>
                    <button type="button" wire:click="setTab('products')"   class="pf-tab" aria-pressed="{{ $activeTab === 'products' ? 'true' : 'false' }}">
                        Products
                        @if(($syncStats['pending_products'] ?? 0) > 0)
                            <span class="count warn">{{ number_format($syncStats['pending_products']) }} pending</span>
                        @else
                            <span class="count">{{ number_format($syncStats['linked_products'] ?? 0) }}</span>
                        @endif
                    </button>
                    <button type="button" wire:click="setTab('analytics')"  class="pf-tab" aria-pressed="{{ $activeTab === 'analytics' ? 'true' : 'false' }}">Analytics <span class="count">{{ number_format($syncStats['analytics_snapshots'] ?? 0) }}</span></button>
                    <button type="button" wire:click="setTab('affiliates')" class="pf-tab" aria-pressed="{{ $activeTab === 'affiliates' ? 'true' : 'false' }}">Affiliates <span class="count">{{ number_format($syncStats['creators'] ?? 0) }}</span></button>
                    <button type="button" wire:click="setTab('finance')"    class="pf-tab" aria-pressed="{{ $activeTab === 'finance' ? 'true' : 'false' }}">Finance <span class="count">{{ number_format($syncStats['finance_statements'] ?? 0) }}</span></button>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════ Overview --}}
        @if($activeTab === 'overview')
            <div class="reveal d4 grid grid-cols-1 lg:grid-cols-3 gap-5">
                {{-- Main column --}}
                <div class="lg:col-span-2 space-y-5">
                    {{-- Account Information --}}
                    <section class="pf-surface p-6">
                        <h3 class="section-h mb-5 flex items-center gap-2">
                            <flux:icon name="identification" class="w-4 h-4 text-zinc-400" />
                            Account Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                            <div class="field-block">
                                <span class="field-label">Account Name</span>
                                <span class="field-value">{{ $account->name }}</span>
                            </div>
                            <div class="field-block">
                                <span class="field-label">Status</span>
                                <span>
                                    <span class="stat-pill {{ $account->is_active ? 'active' : 'idle' }}">
                                        <span class="ring"></span>{{ $account->is_active ? 'Active' : 'Idle' }}
                                    </span>
                                </span>
                            </div>
                            @if($account->account_id)
                                <div class="field-block">
                                    <span class="field-label">Seller ID</span>
                                    <span class="field-value mono">{{ $account->account_id }}</span>
                                </div>
                            @endif
                            @if($account->shop_id)
                                <div class="field-block">
                                    <span class="field-label">Shop ID</span>
                                    <span class="field-value mono">{{ $account->shop_id }}</span>
                                </div>
                            @endif
                            @if($account->country_code)
                                <div class="field-block">
                                    <span class="field-label">Country</span>
                                    <span class="field-value">{{ $account->country_code }}</span>
                                </div>
                            @endif
                            @if($account->currency)
                                <div class="field-block">
                                    <span class="field-label">Currency</span>
                                    <span class="field-value">{{ $account->currency }}</span>
                                </div>
                            @endif
                        </div>
                    </section>

                    {{-- Sync Information --}}
                    <section class="pf-surface p-6">
                        <h3 class="section-h mb-5 flex items-center gap-2">
                            <flux:icon name="arrow-path" class="w-4 h-4 text-zinc-400" />
                            Sync Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                            <div class="field-block">
                                <span class="field-label">Auto Sync Orders</span>
                                <span>
                                    @if($account->auto_sync_orders)
                                        <span class="stat-pill active"><span class="ring"></span>Enabled</span>
                                    @else
                                        <span class="stat-pill warn"><span class="ring"></span>Manual Only</span>
                                    @endif
                                </span>
                            </div>
                            <div class="field-block">
                                <span class="field-label">Connected At</span>
                                <span class="field-value @if(!$account->connected_at) muted @endif">
                                    {{ $account->connected_at ? $account->connected_at->format('M j, Y \a\t g:i A') : 'Not connected' }}
                                </span>
                            </div>
                            <div class="field-block">
                                <span class="field-label">Last Order Sync</span>
                                <span class="field-value @if(!$account->last_order_sync_at) muted @endif">
                                    {{ $account->last_order_sync_at ? $account->last_order_sync_at->diffForHumans() : 'Never synced' }}
                                </span>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Sidebar --}}
                <aside class="space-y-5">
                    {{-- Quick Actions --}}
                    <section class="pf-surface p-5">
                        <h3 class="section-h mb-4 flex items-center gap-2">
                            <flux:icon name="bolt" class="w-4 h-4 text-zinc-400" />
                            Quick Actions
                        </h3>

                        @if($account->isTikTokShop() && !$this->hasOAuthCredentials())
                            <div class="pf-alert warn mb-4">
                                <flux:icon name="exclamation-triangle" class="w-4 h-4 mt-0.5 shrink-0" />
                                <div>
                                    <p class="text-[12.5px] font-semibold mb-1">API Not Connected</p>
                                    <p class="text-[12px] leading-relaxed mb-3">Connect this account to TikTok Shop API to enable automatic order sync.</p>
                                    <a href="{{ route('tiktok.connect', ['link_account' => $account->id]) }}" class="btn btn-brand btn-sm w-full">
                                        <flux:icon name="link" class="w-3.5 h-3.5" />
                                        Link to TikTok Shop
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div class="space-y-2">
                            @if($account->isTikTokShop())
                                <button
                                    type="button"
                                    wire:click="syncOrdersNow"
                                    wire:loading.attr="disabled"
                                    @disabled($isSyncing || !$account->is_active)
                                    class="btn btn-primary w-full"
                                >
                                    <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" />
                                    <span wire:loading.remove>Quick Sync · Last 7 Days</span>
                                    <span wire:loading>Syncing…</span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="openSyncModal"
                                    @disabled(!$account->is_active)
                                    class="btn btn-secondary w-full"
                                >
                                    <flux:icon name="calendar" class="w-4 h-4" />
                                    Sync with Date Range
                                </button>

                                <button
                                    type="button"
                                    wire:click="refreshTokens"
                                    wire:loading.attr="disabled"
                                    class="btn btn-secondary w-full"
                                >
                                    <flux:icon name="key" class="w-4 h-4" />
                                    Refresh API Tokens
                                </button>

                                @if($this->hasOAuthCredentials())
                                    <button
                                        type="button"
                                        wire:click="disconnectTikTokShop"
                                        wire:confirm="Are you sure you want to disconnect this TikTok Shop? This will deactivate the API credentials and allow you to link this account to a different shop."
                                        wire:loading.attr="disabled"
                                        class="btn btn-danger-ghost w-full"
                                    >
                                        <flux:icon name="link-slash" class="w-4 h-4" />
                                        Disconnect TikTok Shop
                                    </button>
                                @endif
                            @endif

                            <a href="{{ route('platforms.accounts.credentials', [$platform, $account]) }}" wire:navigate class="btn btn-ghost w-full">
                                <flux:icon name="key" class="w-4 h-4" />
                                Manage API Credentials
                            </a>
                        </div>
                    </section>

                    {{-- Account Stats --}}
                    <section class="pf-surface p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="section-h flex items-center gap-2">
                                <flux:icon name="chart-bar" class="w-4 h-4 text-zinc-400" />
                                Account Stats
                            </h3>
                            <button type="button" wire:click="refreshStats" wire:loading.attr="disabled" class="btn btn-ghost btn-icon btn-sm" title="Refresh">
                                <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshStats" />
                            </button>
                        </div>

                        <div>
                            <div class="stat-row">
                                <span class="lbl">Total Orders</span>
                                <span class="val">{{ number_format($syncStats['total_orders'] ?? 0) }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="lbl">Pending</span>
                                <span><span class="stat-pill warn"><span class="ring"></span>{{ number_format($syncStats['pending_orders'] ?? 0) }}</span></span>
                            </div>
                            <div class="stat-row">
                                <span class="lbl">Confirmed</span>
                                <span><span class="stat-pill info"><span class="ring"></span>{{ number_format($syncStats['confirmed_orders'] ?? 0) }}</span></span>
                            </div>
                            <div class="stat-row">
                                <span class="lbl">Shipped</span>
                                <span><span class="stat-pill purple"><span class="ring"></span>{{ number_format($syncStats['shipped_orders'] ?? 0) }}</span></span>
                            </div>
                            <div class="stat-row">
                                <span class="lbl">Completed</span>
                                <span><span class="stat-pill active"><span class="ring"></span>{{ number_format($syncStats['completed_orders'] ?? 0) }}</span></span>
                            </div>
                            <div class="flex items-center justify-between pt-3 mt-2 border-t border-zinc-200 dark:border-zinc-700">
                                <span class="text-[12.5px] font-semibold text-zinc-700 dark:text-zinc-200">Total Revenue</span>
                                <span class="font-mono text-[14px] font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $account->currency ?? 'MYR' }} {{ number_format($syncStats['total_revenue'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </section>

                    @if($account->isTikTokShop() && $lastSyncResult)
                        <section class="pf-surface p-5">
                            <h3 class="section-h mb-4 flex items-center gap-2">
                                <flux:icon name="check-circle" class="w-4 h-4 text-emerald-500" />
                                Last Sync Result
                            </h3>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="sync-stat neutral">
                                    <div class="lbl">Synced</div>
                                    <div class="val">{{ $lastSyncResult['synced'] ?? 0 }}</div>
                                </div>
                                <div class="sync-stat success">
                                    <div class="lbl">New</div>
                                    <div class="val">{{ $lastSyncResult['created'] ?? 0 }}</div>
                                </div>
                                <div class="sync-stat warn">
                                    <div class="lbl">Updated</div>
                                    <div class="val">{{ $lastSyncResult['updated'] ?? 0 }}</div>
                                </div>
                                @if(($lastSyncResult['failed'] ?? 0) > 0)
                                    <div class="sync-stat danger">
                                        <div class="lbl">Failed</div>
                                        <div class="val">{{ $lastSyncResult['failed'] }}</div>
                                    </div>
                                @endif
                            </div>
                            @if($account->last_order_sync_at)
                                <p class="mt-3 text-[11.5px] text-zinc-500 dark:text-zinc-400">
                                    Last sync {{ $account->last_order_sync_at->diffForHumans() }}
                                </p>
                            @endif
                        </section>
                    @endif

                    {{-- Danger Zone --}}
                    <section class="pf-surface p-5" style="border-color: rgb(254 202 202);">
                        <h3 class="section-h mb-3 text-red-600 dark:text-red-400 flex items-center gap-2">
                            <flux:icon name="exclamation-triangle" class="w-4 h-4" />
                            Danger Zone
                        </h3>
                        <p class="text-[12px] text-zinc-500 dark:text-zinc-400 mb-3 leading-relaxed">
                            Deleting this account is permanent and removes all associated data.
                        </p>
                        <button
                            type="button"
                            wire:click="deleteAccount"
                            wire:confirm="Are you sure you want to delete this account? This action cannot be undone and will remove all associated data."
                            class="btn btn-danger w-full btn-sm"
                        >
                            <flux:icon name="trash" class="w-4 h-4" />
                            Delete Account
                        </button>
                    </section>
                </aside>
            </div>

        {{-- ═══════════════════════════════════════════════════ Connections --}}
        @elseif($activeTab === 'connections')
            <div class="reveal d4 space-y-5">
                <section class="pf-surface overflow-hidden">
                    <div class="px-6 py-5 border-b border-zinc-100 dark:border-zinc-800 flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">App Connections</flux:heading>
                            <flux:text class="mt-1">TikTok Shop categorizes apps. Each category needs its own OAuth connection to grant the relevant scopes.</flux:text>
                        </div>
                        <flux:button variant="outline" size="sm" icon="key" :href="route('platforms.apps.index', $platform)" wire:navigate>
                            Manage Apps
                        </flux:button>
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($apps as $app)
                            @php
                                $cred = $credentialsByAppId[$app->id] ?? null;
                                $isConnected = $cred && $cred->is_active && (! $cred->expires_at || ! $cred->expires_at->isPast());
                            @endphp
                            <div wire:key="app-{{ $app->id }}" class="px-6 py-4 flex items-center justify-between gap-3 flex-wrap">
                                <div>
                                    <div class="font-medium text-[14px] text-zinc-900 dark:text-zinc-100">{{ $app->name }}</div>
                                    <div class="text-[12px] text-zinc-500 dark:text-zinc-400">Category: {{ $app->category }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($isConnected)
                                        <flux:badge color="green">Connected</flux:badge>
                                        <flux:button size="sm" variant="outline"
                                            href="{{ route('tiktok.connect', ['app' => $app->slug, 'link_account' => $account->id]) }}">
                                            Reconnect
                                        </flux:button>
                                        <form method="POST" action="{{ route('tiktok.disconnect', $account->id) }}?app={{ $app->slug }}">
                                            @csrf
                                            <flux:button size="sm" variant="ghost" type="submit">Disconnect</flux:button>
                                        </form>
                                    @else
                                        <flux:badge color="amber">Not connected</flux:badge>
                                        <flux:button size="sm" variant="primary"
                                            href="{{ route('tiktok.connect', ['app' => $app->slug, 'link_account' => $account->id]) }}">
                                            Connect
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                No apps registered yet.
                                <a href="{{ route('platforms.apps.index', $platform) }}" class="text-blue-600 hover:underline">Register apps →</a>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

        {{-- ═══════════════════════════════════════════════════ Orders --}}
        @elseif($activeTab === 'orders')
            <div class="reveal d4 space-y-5">
                {{-- Filter bar --}}
                <div class="pf-surface p-4 flex flex-col md:flex-row md:items-center gap-3">
                    <div class="flex-1 relative">
                        <flux:icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="orderSearch"
                            placeholder="Search by order ID, number, customer…"
                            class="pf-search"
                        />
                    </div>
                    <select wire:model.live="orderStatus" class="pf-select md:w-44">
                        <option value="">All status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button type="button" wire:click="refreshStats" wire:loading.attr="disabled" class="btn btn-ghost btn-icon" title="Refresh">
                        <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshStats" />
                    </button>
                    <button type="button" wire:click="openSyncModal" @disabled(!$account->is_active) class="btn btn-primary">
                        <flux:icon name="arrow-path" class="w-4 h-4" />
                        Sync Orders
                    </button>
                </div>

                {{-- Last sync banner --}}
                @if($lastSyncResult)
                    <div class="pf-surface p-5">
                        <div class="flex items-center justify-between gap-4 flex-wrap">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl grid place-items-center bg-emerald-50 dark:bg-emerald-900/20">
                                    <flux:icon name="check-circle" class="w-4.5 h-4.5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <div class="text-[13px] font-semibold text-zinc-900 dark:text-white">Last Sync Completed</div>
                                    @if($account->last_order_sync_at)
                                        <div class="text-[12px] text-zinc-500 dark:text-zinc-400">{{ $account->last_order_sync_at->format('M j, Y \a\t g:i A') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 ml-auto w-full sm:w-auto">
                                <div class="sync-stat neutral">
                                    <div class="lbl">Synced</div>
                                    <div class="val">{{ $lastSyncResult['synced'] ?? 0 }}</div>
                                </div>
                                <div class="sync-stat success">
                                    <div class="lbl">New</div>
                                    <div class="val">{{ $lastSyncResult['created'] ?? 0 }}</div>
                                </div>
                                <div class="sync-stat warn">
                                    <div class="lbl">Updated</div>
                                    <div class="val">{{ $lastSyncResult['updated'] ?? 0 }}</div>
                                </div>
                                @if(($lastSyncResult['failed'] ?? 0) > 0)
                                    <div class="sync-stat danger">
                                        <div class="lbl">Failed</div>
                                        <div class="val">{{ $lastSyncResult['failed'] }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if(!empty($lastSyncResult['errors']))
                            <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                <p class="text-[11.5px] font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider mb-2">Sync Errors</p>
                                <div class="text-[12px] text-red-600 dark:text-red-400 max-h-20 overflow-y-auto space-y-1">
                                    @foreach(array_slice($lastSyncResult['errors'], 0, 3) as $error)
                                        @php
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
                                        <div>· {{ $shortError }}</div>
                                    @endforeach
                                    @if(count($lastSyncResult['errors']) > 3)
                                        <div class="text-zinc-500">… and {{ count($lastSyncResult['errors']) - 3 }} more errors</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Orders table --}}
                <div class="pf-surface overflow-hidden">
                    @if($this->orders->isEmpty())
                        <div class="empty-state">
                            <div class="icon"><flux:icon name="inbox" class="w-5 h-5" /></div>
                            <h3>No orders found</h3>
                            <p>Orders will appear here once synced from TikTok.</p>
                            @if($account->isTikTokShop() && $account->is_active)
                                <button type="button" wire:click="openSyncModal" class="btn btn-secondary btn-sm mt-4">
                                    <flux:icon name="arrow-path" class="w-3.5 h-3.5" /> Sync Orders from TikTok
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="pf-table">
                                <thead>
                                    <tr>
                                        <th class="cursor-pointer" wire:click="sortBy('platform_order_id')">
                                            <span class="inline-flex items-center gap-1">Order
                                                @if($orderSort === 'platform_order_id')
                                                    <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                                @endif
                                            </span>
                                        </th>
                                        <th>Customer</th>
                                        <th class="cursor-pointer" wire:click="sortBy('status')">
                                            <span class="inline-flex items-center gap-1">Status
                                                @if($orderSort === 'status')
                                                    <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                                @endif
                                            </span>
                                        </th>
                                        <th class="cursor-pointer text-right" wire:click="sortBy('total_amount')">
                                            <span class="inline-flex items-center gap-1 justify-end w-full">Total
                                                @if($orderSort === 'total_amount')
                                                    <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                                @endif
                                            </span>
                                        </th>
                                        <th class="cursor-pointer" wire:click="sortBy('order_date')">
                                            <span class="inline-flex items-center gap-1">Date
                                                @if($orderSort === 'order_date')
                                                    <flux:icon name="{{ $orderSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                                @endif
                                            </span>
                                        </th>
                                        <th class="text-right">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->orders as $order)
                                        <tr wire:key="order-{{ $order->id }}">
                                            <td>
                                                <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="text-[13px] font-semibold text-zinc-900 dark:text-white hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                                                    {{ $order->platform_order_number ?: $order->platform_order_id }}
                                                </a>
                                                @if($order->tracking_id)
                                                    <div class="text-[11.5px] text-zinc-500 dark:text-zinc-400 mono">Track: {{ $order->tracking_id }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="text-[13px] text-zinc-900 dark:text-zinc-100">{{ $order->customer_name ?: 'N/A' }}</div>
                                                @if($order->buyer_username)
                                                    <div class="text-[11.5px] text-zinc-500 dark:text-zinc-400">@{{ $order->buyer_username }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="stat-pill {{ $this->getStatusPillClass($order->status) }}">
                                                    <span class="ring"></span>{{ ucfirst($order->status) }}
                                                </span>
                                            </td>
                                            <td class="text-right mono font-semibold text-zinc-900 dark:text-white">
                                                {{ $order->currency ?? 'MYR' }} {{ number_format($order->total_amount, 2) }}
                                            </td>
                                            <td class="text-zinc-500 dark:text-zinc-400">
                                                @if($order->order_date)
                                                    <div class="text-[12.5px]">{{ $order->order_date->format('M j, Y') }}</div>
                                                    <div class="text-[11.5px] text-zinc-400">{{ $order->order_date->format('g:i A') }}</div>
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('platforms.orders.show', [$platform, $order]) }}" wire:navigate class="btn btn-ghost btn-icon btn-sm">
                                                    <flux:icon name="eye" class="w-4 h-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($this->orders->hasPages())
                            <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
                                {{ $this->orders->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>

        {{-- ═══════════════════════════════════════════════════ Products --}}
        @elseif($activeTab === 'products')
            <div class="reveal d4 space-y-5">
                {{-- Header --}}
                <div class="pf-surface p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h3 class="section-h text-[15px]">Product Synchronization</h3>
                        <p class="mt-1 text-[12.5px] text-zinc-500 dark:text-zinc-400">Import products from TikTok Shop and link them to your internal catalog.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($account->last_product_sync_at)
                            <span class="text-[12px] text-zinc-500 dark:text-zinc-400">Last synced {{ $account->last_product_sync_at->diffForHumans() }}</span>
                        @endif
                        <button
                            type="button"
                            wire:click="syncProductsNow"
                            wire:loading.attr="disabled"
                            wire:target="syncProductsNow"
                            @disabled(!$account->is_active)
                            class="btn btn-primary"
                        >
                            <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" wire:target="syncProductsNow" />
                            <span wire:loading.remove wire:target="syncProductsNow">Sync Products</span>
                            <span wire:loading wire:target="syncProductsNow">Syncing…</span>
                        </button>
                    </div>
                </div>

                {{-- Stat cards --}}
                @php
                    $lastProductSync = $account->metadata['last_product_sync_result'] ?? null;
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="kpi">
                        <div class="lbl">Linked Products</div>
                        <div class="val emerald">{{ number_format($syncStats['linked_products'] ?? 0) }}</div>
                    </div>
                    <div class="kpi">
                        <div class="lbl">Pending Review</div>
                        <div class="val">{{ number_format($syncStats['pending_products'] ?? 0) }}</div>
                    </div>
                    @if($lastProductSync)
                        <div class="kpi">
                            <div class="lbl">Auto-Linked</div>
                            <div class="val blue">{{ number_format($lastProductSync['auto_linked'] ?? 0) }}</div>
                        </div>
                        <div class="kpi">
                            <div class="lbl">Last Sync Total</div>
                            <div class="val">{{ number_format($lastProductSync['total'] ?? 0) }}</div>
                        </div>
                    @endif
                </div>

                {{-- Pending products alert --}}
                @if(($syncStats['pending_products'] ?? 0) > 0)
                    <div class="pf-alert warn">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 mt-0.5 shrink-0" />
                        <div class="flex-1 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p class="text-[13px] font-semibold">{{ $syncStats['pending_products'] }} products need review</p>
                                <p class="text-[12px] mt-0.5 leading-relaxed">These TikTok products couldn't be auto-matched and need manual linking.</p>
                            </div>
                            <a href="{{ route('platforms.accounts.pending-products', [$platform, $account]) }}" wire:navigate class="btn btn-primary btn-sm">
                                <flux:icon name="eye" class="w-3.5 h-3.5" /> Review
                            </a>
                        </div>
                    </div>
                @endif

                {{-- How it works --}}
                <section class="pf-surface p-6">
                    <h3 class="section-h mb-4 flex items-center gap-2">
                        <flux:icon name="information-circle" class="w-4 h-4 text-zinc-400" />
                        How Product Sync Works
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="step-card">
                            <div class="num">STEP 01</div>
                            <h4>Import</h4>
                            <p>Products are fetched from your TikTok Shop.</p>
                        </div>
                        <div class="step-card">
                            <div class="num">STEP 02</div>
                            <h4>Smart Match</h4>
                            <p>Products are auto-matched by SKU, barcode, or name.</p>
                        </div>
                        <div class="step-card">
                            <div class="num">STEP 03</div>
                            <h4>Link</h4>
                            <p>Review unmatched products and link or create new ones.</p>
                        </div>
                    </div>
                </section>

                {{-- Quick actions --}}
                <section class="pf-surface p-6">
                    <h3 class="section-h mb-4 flex items-center gap-2">
                        <flux:icon name="bolt" class="w-4 h-4 text-zinc-400" />
                        Quick Actions
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <a href="{{ route('platforms.accounts.pending-products', [$platform, $account]) }}" wire:navigate class="step-card hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors no-underline">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg grid place-items-center bg-amber-50 dark:bg-amber-900/20 shrink-0">
                                    <flux:icon name="clock" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <h4 class="!mt-0">Review Pending Products</h4>
                                    <p>Link or create products from TikTok imports</p>
                                </div>
                            </div>
                        </a>
                        <a href="{{ route('platforms.sku-mappings.index', ['platform_account' => $account->id]) }}" wire:navigate class="step-card hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors no-underline">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg grid place-items-center bg-blue-50 dark:bg-blue-900/20 shrink-0">
                                    <flux:icon name="document-text" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h4 class="!mt-0">Manage SKU Mappings</h4>
                                    <p>View and edit product link configurations</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </section>
            </div>

        {{-- ═══════════════════════════════════════════════════ Analytics --}}
        @elseif($activeTab === 'analytics')
            <div class="reveal d4 space-y-5">
                <section class="pf-surface p-6">
                    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                        <h3 class="section-h text-[15px] flex items-center gap-2">
                            <flux:icon name="chart-bar" class="w-4 h-4 text-zinc-400" />
                            Shop Performance
                        </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-[12px] text-zinc-500 dark:text-zinc-400">
                                Last sync: {{ $account->last_analytics_sync_at ? $account->last_analytics_sync_at->diffForHumans() : 'Never' }}
                            </span>
                            <button type="button" wire:click="syncAnalyticsNow" wire:loading.attr="disabled" wire:target="syncAnalyticsNow" class="btn btn-secondary btn-sm">
                                <flux:icon name="arrow-path" class="w-3.5 h-3.5" wire:loading.class="animate-spin" wire:target="syncAnalyticsNow" />
                                <span wire:loading.remove wire:target="syncAnalyticsNow">Sync Now</span>
                                <span wire:loading wire:target="syncAnalyticsNow">Queuing…</span>
                            </button>
                        </div>
                    </div>

                    @php
                        $latestSnapshot = \App\Models\TiktokShopPerformanceSnapshot::where('platform_account_id', $account->id)
                            ->latest('fetched_at')->first();
                    @endphp

                    @if($latestSnapshot)
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                            <div class="kpi">
                                <div class="lbl">Orders</div>
                                <div class="val">{{ number_format($latestSnapshot->total_orders) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="lbl">GMV</div>
                                <div class="val emerald">RM {{ number_format($latestSnapshot->total_gmv, 2) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="lbl">Buyers</div>
                                <div class="val">{{ number_format($latestSnapshot->total_buyers) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="lbl">Video Views</div>
                                <div class="val">{{ number_format($latestSnapshot->total_video_views) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="lbl">Impressions</div>
                                <div class="val">{{ number_format($latestSnapshot->total_product_impressions) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="lbl">Conversion</div>
                                <div class="val blue">{{ number_format($latestSnapshot->conversion_rate, 2) }}%</div>
                            </div>
                        </div>
                    @else
                        @php
                            $analyticsCred = ($credentialsByAppId ?? collect())
                                ->first(fn ($c) => optional($c->platformApp)->category === 'analytics_reporting');
                        @endphp
                        @if(! $analyticsCred)
                            <div class="empty-state">
                                <div class="icon"><flux:icon name="chart-bar" class="w-5 h-5" /></div>
                                <h3>Connect Analytics & Reporting</h3>
                                <p class="mb-4">Analytics requires a separate TikTok app connection.</p>
                                <flux:button variant="primary" wire:click="setTab('connections')">
                                    Connect Analytics & Reporting
                                </flux:button>
                            </div>
                        @else
                            <div class="empty-state">
                                <div class="icon"><flux:icon name="chart-bar" class="w-5 h-5" /></div>
                                <h3>No analytics data yet</h3>
                                <p>Run <code class="bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-[11px]">php artisan tiktok:sync-analytics</code> to pull shop performance data.</p>
                            </div>
                        @endif
                    @endif
                </section>

                @php
                    $interval = $latestSnapshot?->raw_response['performance']['intervals'][0] ?? null;
                    $byType = function (?array $list, string $type) {
                        foreach ($list ?? [] as $row) {
                            if (($row['type'] ?? null) === $type) {
                                return $row;
                            }
                        }
                        return null;
                    };
                    $totalGmv = (float) ($interval['gmv']['amount'] ?? 0);
                    $channels = $interval ? [
                        [
                            'key' => 'LIVE',
                            'label' => 'TikTok LIVE',
                            'icon' => 'video-camera',
                            'tone' => 'rose',
                            'gmv' => (float) ($byType($interval['gmv_breakdowns'] ?? null, 'LIVE')['amount'] ?? 0),
                            'buyers' => (int) ($byType($interval['buyer_breakdowns'] ?? null, 'LIVE')['amount'] ?? 0),
                            'impressions' => (int) ($byType($interval['product_impression_breakdowns'] ?? null, 'LIVE')['amount'] ?? 0),
                            'page_views' => (int) ($byType($interval['product_page_view_breakdowns'] ?? null, 'LIVE')['amount'] ?? 0),
                        ],
                        [
                            'key' => 'VIDEO',
                            'label' => 'Short Video',
                            'icon' => 'film',
                            'tone' => 'sky',
                            'gmv' => (float) ($byType($interval['gmv_breakdowns'] ?? null, 'VIDEO')['amount'] ?? 0),
                            'buyers' => (int) ($byType($interval['buyer_breakdowns'] ?? null, 'VIDEO')['amount'] ?? 0),
                            'impressions' => (int) ($byType($interval['product_impression_breakdowns'] ?? null, 'VIDEO')['amount'] ?? 0),
                            'page_views' => (int) ($byType($interval['product_page_view_breakdowns'] ?? null, 'VIDEO')['amount'] ?? 0),
                        ],
                        [
                            'key' => 'PRODUCT_CARD',
                            'label' => 'Product Card',
                            'icon' => 'shopping-bag',
                            'tone' => 'amber',
                            'gmv' => (float) ($byType($interval['gmv_breakdowns'] ?? null, 'PRODUCT_CARD')['amount'] ?? 0),
                            'buyers' => (int) ($byType($interval['buyer_breakdowns'] ?? null, 'PRODUCT_CARD')['amount'] ?? 0),
                            'impressions' => (int) ($byType($interval['product_impression_breakdowns'] ?? null, 'PRODUCT_CARD')['amount'] ?? 0),
                            'page_views' => (int) ($byType($interval['product_page_view_breakdowns'] ?? null, 'PRODUCT_CARD')['amount'] ?? 0),
                        ],
                    ] : [];
                    $toneClasses = [
                        'rose'  => ['accent' => 'text-rose-600 dark:text-rose-400',  'bar' => 'bg-rose-500',  'dot' => 'bg-rose-500'],
                        'sky'   => ['accent' => 'text-sky-600 dark:text-sky-400',    'bar' => 'bg-sky-500',   'dot' => 'bg-sky-500'],
                        'amber' => ['accent' => 'text-amber-600 dark:text-amber-400','bar' => 'bg-amber-500', 'dot' => 'bg-amber-500'],
                    ];
                @endphp

                @if($interval && $totalGmv > 0)
                    <section class="pf-surface p-6">
                        <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                            <div>
                                <h3 class="section-h text-[15px] flex items-center gap-2">
                                    <flux:icon name="signal" class="w-4 h-4 text-zinc-400" />
                                    Channel Breakdown
                                </h3>
                                <p class="text-[12px] text-zinc-500 dark:text-zinc-400 mt-1">
                                    Where sales come from — LIVE streams vs. short videos vs. product cards.
                                </p>
                            </div>
                            <span class="text-[11.5px] text-zinc-400">
                                Window: {{ $interval['start_date'] ?? '' }} → {{ $interval['end_date'] ?? '' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            @foreach($channels as $ch)
                                @php
                                    $tone = $toneClasses[$ch['tone']];
                                    $share = $totalGmv > 0 ? ($ch['gmv'] / $totalGmv) * 100 : 0;
                                @endphp
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/40 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2 h-2 rounded-full {{ $tone['dot'] }}"></span>
                                            <flux:icon :name="$ch['icon']" class="w-4 h-4 text-zinc-400" />
                                            <span class="text-[12.5px] font-semibold text-zinc-700 dark:text-zinc-200">
                                                {{ $ch['label'] }}
                                            </span>
                                        </div>
                                        <span class="text-[11.5px] mono {{ $tone['accent'] }}">
                                            {{ number_format($share, 1) }}%
                                        </span>
                                    </div>

                                    <div class="text-[22px] font-bold {{ $tone['accent'] }} leading-none mb-1">
                                        RM {{ number_format($ch['gmv'], 2) }}
                                    </div>
                                    <div class="text-[11px] uppercase tracking-wider text-zinc-400 mb-3">GMV</div>

                                    <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden mb-4">
                                        <div class="h-full {{ $tone['bar'] }}" style="width: {{ min($share, 100) }}%"></div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2 text-center">
                                        <div>
                                            <div class="text-[13px] font-semibold mono text-zinc-800 dark:text-zinc-100">{{ number_format($ch['buyers']) }}</div>
                                            <div class="text-[10.5px] uppercase tracking-wider text-zinc-400 mt-0.5">Buyers</div>
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-semibold mono text-zinc-800 dark:text-zinc-100">{{ number_format($ch['page_views']) }}</div>
                                            <div class="text-[10.5px] uppercase tracking-wider text-zinc-400 mt-0.5">Views</div>
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-semibold mono text-zinc-800 dark:text-zinc-100">{{ number_format($ch['impressions']) }}</div>
                                            <div class="text-[10.5px] uppercase tracking-wider text-zinc-400 mt-0.5">Impr.</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="pf-surface overflow-hidden">
                    <div class="p-6 pb-4">
                        <h3 class="section-h text-[15px] flex items-center gap-2">
                            <flux:icon name="cube" class="w-4 h-4 text-zinc-400" />
                            Product Performance
                        </h3>
                    </div>

                    @php
                        $productPerformance = \App\Models\TiktokProductPerformance::where('platform_account_id', $account->id)
                            ->latest('fetched_at')->limit(20)->get();
                    @endphp

                    @if($productPerformance->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="pf-table">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th class="text-right">Impressions</th>
                                        <th class="text-right">Clicks</th>
                                        <th class="text-right">Orders</th>
                                        <th class="text-right">GMV</th>
                                        <th class="text-right">Conversion</th>
                                        <th class="text-right">Fetched</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($productPerformance as $pp)
                                        <tr>
                                            <td class="mono text-[11.5px]">{{ $pp->tiktok_product_id }}</td>
                                            <td class="text-right mono">{{ number_format($pp->impressions) }}</td>
                                            <td class="text-right mono">{{ number_format($pp->clicks) }}</td>
                                            <td class="text-right mono">{{ number_format($pp->orders) }}</td>
                                            <td class="text-right mono font-semibold text-emerald-600 dark:text-emerald-400">RM {{ number_format($pp->gmv, 2) }}</td>
                                            <td class="text-right mono">{{ number_format($pp->conversion_rate, 2) }}%</td>
                                            <td class="text-right text-[11.5px] text-zinc-400">{{ $pp->fetched_at->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state pt-2 pb-12">
                            <p>No product performance data yet.</p>
                        </div>
                    @endif
                </section>
            </div>

        {{-- ═══════════════════════════════════════════════════ Affiliates --}}
        @elseif($activeTab === 'affiliates')
            <div class="reveal d4 space-y-5">
                <section class="pf-surface overflow-hidden">
                    <div class="p-5 flex items-center justify-between flex-wrap gap-3 border-b border-zinc-100 dark:border-zinc-800">
                        <h3 class="section-h text-[15px] flex items-center gap-2">
                            <flux:icon name="users" class="w-4 h-4 text-zinc-400" />
                            Affiliate Creators
                        </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-[12px] text-zinc-500 dark:text-zinc-400">
                                Last sync: {{ $account->last_affiliate_sync_at ? $account->last_affiliate_sync_at->diffForHumans() : 'Never' }}
                            </span>
                            <button type="button" wire:click="syncAffiliatesNow" wire:loading.attr="disabled" wire:target="syncAffiliatesNow" class="btn btn-secondary btn-sm">
                                <flux:icon name="arrow-path" class="w-3.5 h-3.5" wire:loading.class="animate-spin" wire:target="syncAffiliatesNow" />
                                <span wire:loading.remove wire:target="syncAffiliatesNow">Sync Now</span>
                                <span wire:loading wire:target="syncAffiliatesNow">Queuing…</span>
                            </button>
                        </div>
                    </div>

                    @php
                        $creators = \App\Models\TiktokCreator::where('platform_account_id', $account->id)
                            ->orderByDesc('total_gmv')->limit(20)->get();
                    @endphp

                    @if($creators->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="pf-table">
                                <thead>
                                    <tr>
                                        <th class="w-12">#</th>
                                        <th>Creator</th>
                                        <th class="text-right">Followers</th>
                                        <th class="text-right">GMV</th>
                                        <th class="text-right">Orders</th>
                                        <th class="text-right">Commission</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($creators as $idx => $creator)
                                        <tr>
                                            <td class="text-zinc-400 mono">{{ $idx + 1 }}</td>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    @if($creator->avatar_url)
                                                        <img src="{{ $creator->avatar_url }}" class="h-8 w-8 rounded-full object-cover" alt="">
                                                    @else
                                                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white" style="background: linear-gradient(135deg, rgb(99 102 241), rgb(168 85 247));">
                                                            {{ strtoupper(substr($creator->display_name ?? '?', 0, 1)) }}
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $creator->display_name ?? 'Unknown' }}</div>
                                                        @if($creator->handle)
                                                            <div class="text-[11.5px] text-zinc-400">{{ $creator->handle }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-right mono text-zinc-600 dark:text-zinc-300">{{ number_format($creator->follower_count) }}</td>
                                            <td class="text-right mono font-semibold text-emerald-600 dark:text-emerald-400">RM {{ number_format($creator->total_gmv, 2) }}</td>
                                            <td class="text-right mono text-zinc-600 dark:text-zinc-300">{{ number_format($creator->total_orders) }}</td>
                                            <td class="text-right mono text-amber-600 dark:text-amber-400">RM {{ number_format($creator->total_commission, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="icon"><flux:icon name="users" class="w-5 h-5" /></div>
                            <h3>No affiliate creators yet</h3>
                            <p>Run <code class="bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-[11px]">php artisan tiktok:sync-affiliates</code> to pull creator data.</p>
                        </div>
                    @endif
                </section>

                <section class="pf-surface overflow-hidden">
                    <div class="p-5 border-b border-zinc-100 dark:border-zinc-800">
                        <h3 class="section-h text-[15px] flex items-center gap-2">
                            <flux:icon name="shopping-bag" class="w-4 h-4 text-zinc-400" />
                            Recent Affiliate Orders
                        </h3>
                    </div>

                    @php
                        $affiliateOrders = \App\Models\TiktokAffiliateOrder::where('platform_account_id', $account->id)
                            ->with('creator:id,display_name,handle,avatar_url')
                            ->latest('order_created_at')->limit(20)->get();
                    @endphp

                    @if($affiliateOrders->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="pf-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Creator</th>
                                        <th class="text-right">Amount</th>
                                        <th class="text-right">Commission</th>
                                        <th>Status</th>
                                        <th class="text-right">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($affiliateOrders as $ao)
                                        <tr>
                                            <td class="mono text-[11.5px]">{{ Str::limit($ao->tiktok_order_id, 16) }}</td>
                                            <td>
                                                <div class="text-[13px] font-medium text-zinc-800 dark:text-zinc-100">{{ $ao->creator?->display_name ?? 'Unknown' }}</div>
                                            </td>
                                            <td class="text-right mono text-emerald-600 dark:text-emerald-400">RM {{ number_format($ao->total_amount, 2) }}</td>
                                            <td class="text-right mono text-amber-600 dark:text-amber-400">RM {{ number_format($ao->commission_amount, 2) }}</td>
                                            <td>
                                                <span class="stat-pill {{ $ao->status === 'completed' ? 'active' : ($ao->status === 'cancelled' ? 'danger' : 'idle') }}">
                                                    <span class="ring"></span>{{ ucfirst($ao->status ?? 'unknown') }}
                                                </span>
                                            </td>
                                            <td class="text-right text-[11.5px] text-zinc-500">{{ $ao->order_created_at?->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state pt-2 pb-12">
                            <p>No affiliate orders yet.</p>
                        </div>
                    @endif
                </section>
            </div>

        {{-- ═══════════════════════════════════════════════════ Finance --}}
        @elseif($activeTab === 'finance')
            <div class="reveal d4 space-y-5">
                <section class="pf-surface overflow-hidden">
                    <div class="p-5 flex items-center justify-between flex-wrap gap-3 border-b border-zinc-100 dark:border-zinc-800">
                        <h3 class="section-h text-[15px] flex items-center gap-2">
                            <flux:icon name="banknotes" class="w-4 h-4 text-zinc-400" />
                            Finance Statements
                        </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-[12px] text-zinc-500 dark:text-zinc-400">
                                Last sync: {{ $account->last_finance_sync_at ? $account->last_finance_sync_at->diffForHumans() : 'Never' }}
                            </span>
                            <button type="button" wire:click="syncFinanceNow" wire:loading.attr="disabled" wire:target="syncFinanceNow" class="btn btn-secondary btn-sm">
                                <flux:icon name="arrow-path" class="w-3.5 h-3.5" wire:loading.class="animate-spin" wire:target="syncFinanceNow" />
                                <span wire:loading.remove wire:target="syncFinanceNow">Sync Now</span>
                                <span wire:loading wire:target="syncFinanceNow">Queuing…</span>
                            </button>
                        </div>
                    </div>

                    @php
                        $statements = \App\Models\TiktokFinanceStatement::where('platform_account_id', $account->id)
                            ->latest('statement_time')->limit(20)->get();
                    @endphp

                    @if($statements->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="pf-table">
                                <thead>
                                    <tr>
                                        <th>Statement ID</th>
                                        <th>Type</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-right">Revenue</th>
                                        <th class="text-right">Fees</th>
                                        <th>Status</th>
                                        <th class="text-right">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($statements as $stmt)
                                        <tr>
                                            <td class="mono text-[11.5px]">{{ Str::limit($stmt->tiktok_statement_id, 16) }}</td>
                                            <td>
                                                <span class="stat-pill idle">{{ $stmt->statement_type ?? 'N/A' }}</span>
                                            </td>
                                            <td class="text-right mono font-semibold text-zinc-900 dark:text-white">RM {{ number_format($stmt->total_amount, 2) }}</td>
                                            <td class="text-right mono text-emerald-600 dark:text-emerald-400">RM {{ number_format($stmt->order_amount, 2) }}</td>
                                            <td class="text-right mono text-red-500 dark:text-red-400">RM {{ number_format($stmt->platform_fee, 2) }}</td>
                                            <td>
                                                <span class="stat-pill {{ $stmt->status === 'paid' ? 'active' : ($stmt->status === 'pending' ? 'warn' : 'idle') }}">
                                                    <span class="ring"></span>{{ ucfirst($stmt->status ?? 'unknown') }}
                                                </span>
                                            </td>
                                            <td class="text-right text-[11.5px] text-zinc-500">{{ $stmt->statement_time?->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="icon"><flux:icon name="banknotes" class="w-5 h-5" /></div>
                            <h3>No finance data yet</h3>
                            <p>Run <code class="bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-[11px]">php artisan tiktok:sync-finance</code> to pull financial statements.</p>
                        </div>
                    @endif
                </section>

                <section class="pf-surface overflow-hidden">
                    <div class="p-5 border-b border-zinc-100 dark:border-zinc-800">
                        <h3 class="section-h text-[15px] flex items-center gap-2">
                            <flux:icon name="arrow-path-rounded-square" class="w-4 h-4 text-zinc-400" />
                            Recent Transactions
                        </h3>
                    </div>

                    @php
                        $transactions = \App\Models\TiktokFinanceTransaction::where('platform_account_id', $account->id)
                            ->latest('created_at')->limit(20)->get();
                    @endphp

                    @if($transactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="pf-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Type</th>
                                        <th class="text-right">Amount</th>
                                        <th class="text-right">Commission</th>
                                        <th class="text-right">Shipping</th>
                                        <th class="text-right">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transactions as $tx)
                                        <tr>
                                            <td class="mono text-[11.5px]">{{ Str::limit($tx->tiktok_order_id ?? '-', 16) }}</td>
                                            <td>
                                                <span class="stat-pill idle">{{ $tx->transaction_type ?? 'N/A' }}</span>
                                            </td>
                                            <td class="text-right mono text-zinc-900 dark:text-white">RM {{ number_format($tx->order_amount, 2) }}</td>
                                            <td class="text-right mono text-amber-600 dark:text-amber-400">RM {{ number_format($tx->affiliate_commission, 2) }}</td>
                                            <td class="text-right mono text-zinc-500">RM {{ number_format($tx->shipping_fee, 2) }}</td>
                                            <td class="text-right text-[11.5px] text-zinc-500">{{ ($tx->order_created_at ?? $tx->created_at)->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state pt-2 pb-12">
                            <p>No transactions yet.</p>
                        </div>
                    @endif
                </section>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════ Manual Sync Modal --}}
        <flux:modal wire:model="showSyncModal" class="max-w-md">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-9 h-9 rounded-lg grid place-items-center bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="calendar" class="w-4.5 h-4.5 text-zinc-700 dark:text-zinc-300" />
                    </div>
                    <h3 class="text-[16px] font-semibold tracking-[-0.01em] text-zinc-900 dark:text-white">Manual Order Sync</h3>
                </div>
                <p class="text-[13px] text-zinc-500 dark:text-zinc-400 mb-5">Select a date range to sync orders from TikTok Shop.</p>

                <div class="space-y-4">
                    <div class="field-block">
                        <label class="field-label">From Date</label>
                        <input type="date" wire:model="syncFromDate" class="pf-search" style="padding-left: 12px;">
                    </div>
                    <div class="field-block">
                        <label class="field-label">To Date</label>
                        <input type="date" wire:model="syncToDate" max="{{ now()->format('Y-m-d') }}" class="pf-search" style="padding-left: 12px;">
                    </div>

                    <div class="pf-alert info">
                        <flux:icon name="information-circle" class="w-4 h-4 mt-0.5 shrink-0" />
                        <p class="text-[12px] leading-relaxed">Syncing runs in the background. Large date ranges may take longer to complete.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeSyncModal" class="btn btn-ghost">Cancel</button>
                    <button type="button" wire:click="syncWithDateRange" wire:loading.attr="disabled" class="btn btn-primary">
                        <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" />
                        <span wire:loading.remove>Start Sync</span>
                        <span wire:loading>Starting…</span>
                    </button>
                </div>
            </div>
        </flux:modal>

        {{-- ═══════════════════════════════════════════════════ Sync Progress Modal --}}
        @if($showProgressModal)
        <flux:modal wire:model="showProgressModal" class="max-w-lg" :dismissible="false">
            <div class="p-6" @if($syncProgress && ($syncProgress['status'] ?? '') === 'syncing') wire:poll.1s="checkSyncProgress" @endif>
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 mb-4">
                        <flux:icon name="arrow-path" class="w-6 h-6 text-emerald-600 dark:text-emerald-400 animate-spin" />
                    </div>
                    <h3 class="text-[16px] font-semibold tracking-[-0.01em] text-zinc-900 dark:text-white">Syncing Orders</h3>
                    <p class="mt-1.5 text-[13px] text-zinc-500 dark:text-zinc-400">Please wait while we sync your TikTok orders…</p>
                </div>

                @if($syncProgress)
                    <div class="mb-5">
                        <div class="flex justify-between text-[12px] mb-2">
                            <span class="text-zinc-500 dark:text-zinc-400">Progress</span>
                            <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $syncProgress['percentage'] ?? 0 }}%</span>
                        </div>
                        <div class="pf-progress">
                            <i style="width: {{ $syncProgress['percentage'] ?? 0 }}%"></i>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2 mb-5">
                        <div class="sync-stat neutral">
                            <div class="lbl">Of {{ $syncProgress['total'] ?? 0 }}</div>
                            <div class="val">{{ $syncProgress['processed'] ?? 0 }}</div>
                        </div>
                        <div class="sync-stat success">
                            <div class="lbl">New</div>
                            <div class="val">{{ $syncProgress['created'] ?? 0 }}</div>
                        </div>
                        <div class="sync-stat warn">
                            <div class="lbl">Updated</div>
                            <div class="val">{{ $syncProgress['updated'] ?? 0 }}</div>
                        </div>
                        <div class="sync-stat danger">
                            <div class="lbl">Failed</div>
                            <div class="val">{{ $syncProgress['failed'] ?? 0 }}</div>
                        </div>
                    </div>

                    @if(($syncProgress['status'] ?? '') === 'syncing' && ($syncProgress['current_order'] ?? null))
                        <div class="text-center text-[12px] text-zinc-500 dark:text-zinc-400 mb-4 inline-flex items-center justify-center gap-2 w-full">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                            Processing order: <span class="font-mono">{{ Str::limit($syncProgress['current_order'], 20) }}</span>
                        </div>
                    @endif

                    @if(($syncProgress['status'] ?? '') === 'completed')
                        <div class="text-center">
                            <span class="inline-flex items-center gap-2 text-[13px] font-medium text-emerald-600 dark:text-emerald-400">
                                <flux:icon name="check-circle" class="w-4 h-4" />
                                Sync completed successfully
                            </span>
                        </div>
                    @endif
                @else
                    <div class="text-center py-6">
                        <div class="inline-flex items-center gap-2 text-[12px] text-zinc-500">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse" style="animation-delay: 0.2s"></span>
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse" style="animation-delay: 0.4s"></span>
                            <span class="ml-2">Fetching orders from TikTok…</span>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex justify-center">
                    <button type="button" wire:click="closeProgressModal" class="btn btn-ghost btn-sm">Run in Background</button>
                </div>
            </div>
        </flux:modal>
        @endif

        {{-- ═══════════════════════════════════════════════════ Toast --}}
        <div
            class="pf-toast-host"
            x-data="{
                show: false,
                message: '',
                type: 'success',
                key: 0,
                timeout: null,
                trigger(detail) {
                    // Livewire 3 passes positional array params as event.detail = [payload],
                    // named params as event.detail = payload, and direct CustomEvent dispatches
                    // can give either. Normalise to a single payload object.
                    let payload = detail;
                    if (Array.isArray(payload)) payload = payload[0] || {};
                    if (payload && typeof payload === 'object' && 'params' in payload && Array.isArray(payload.params)) payload = payload.params[0] || {};

                    this.message = payload?.message ?? '';
                    this.type = payload?.type ?? 'success';
                    if (!this.message) return; // Nothing to show
                    this.key++;
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 6000);
                }
            }"
            x-on:notify.window="trigger($event.detail)"
        >
            <div
                x-show="show"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-3 scale-[.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2"
                :class="'pf-toast ' + type"
                style="display: none;"
            >
                <div class="ic">
                    {{-- success --}}
                    <svg x-show="type === 'success'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    {{-- error --}}
                    <svg x-show="type === 'error'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    {{-- warning --}}
                    <svg x-show="type === 'warning'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M5.25 19.5h13.5a1.5 1.5 0 0 0 1.299-2.25l-6.75-11.7a1.5 1.5 0 0 0-2.598 0l-6.75 11.7A1.5 1.5 0 0 0 5.25 19.5Z" />
                    </svg>
                    {{-- info --}}
                    <svg x-show="type === 'info'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h.75v5.25M12 7.5h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <span x-text="message" class="msg"></span>
                <button type="button" @click="show = false" class="close" aria-label="Dismiss">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                {{-- Auto-dismiss progress bar (re-keys to restart animation) --}}
                <div class="bar"><i :key="key"></i></div>
        </div>

        {{-- Alpine polling for sync completion detection --}}
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
</div>
