<?php

use Livewire\Volt\Component;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformOrder;
use App\Models\PlatformApiCredential;
use Carbon\Carbon;

new class extends Component {
    public $stats = [];
    public $recentOrders = [];
    public $platformSummaries = [];
    public $recentImports = [];
    public $systemAlerts = [];

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $this->loadStats();
        $this->loadPlatformSummaries();
        $this->loadRecentOrders();
        $this->loadRecentImports();
        $this->loadSystemAlerts();
    }

    public function loadStats()
    {
        $this->stats = [
            'total_platforms' => Platform::count(),
            'active_platforms' => Platform::where('is_active', true)->count(),
            'total_accounts' => PlatformAccount::count(),
            'active_accounts' => PlatformAccount::where('is_active', true)->count(),
            'total_orders' => PlatformOrder::count(),
            'orders_this_month' => PlatformOrder::whereMonth('platform_created_at', now()->month)->count(),
            'total_revenue' => PlatformOrder::sum('total_amount'),
            'revenue_this_month' => PlatformOrder::whereMonth('platform_created_at', now()->month)->sum('total_amount'),
            'total_credentials' => PlatformApiCredential::count(),
            'active_credentials' => PlatformApiCredential::where('is_active', true)->count(),
        ];
    }

    public function loadPlatformSummaries()
    {
        $this->platformSummaries = Platform::with(['accounts' => function($query) {
            $query->where('is_active', true);
        }])->get()->map(function($platform) {
            $accountsCount = $platform->accounts->count();
            $ordersCount = $platform->orders()->count();
            $revenue = $platform->orders()->sum('total_amount');
            $lastImport = $platform->orders()->latest('created_at')->first();

            return [
                'platform' => $platform,
                'accounts_count' => $accountsCount,
                'orders_count' => $ordersCount,
                'revenue' => $revenue,
                'last_import' => $lastImport,
                'status' => $this->getPlatformStatus($platform, $accountsCount),
            ];
        })->toArray();
    }

    public function loadRecentOrders()
    {
        $this->recentOrders = PlatformOrder::with(['platform', 'platformAccount'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function loadRecentImports()
    {
        // Group recent orders by import batch (same created_at time)
        $imports = PlatformOrder::selectRaw('
            platform_id,
            platform_account_id,
            created_at as imported_at,
            COUNT(*) as orders_count,
            SUM(total_amount) as total_value,
            MIN(created_at) as created_at
        ')
        ->with(['platform', 'platformAccount'])
        ->whereNotNull('created_at')
        ->groupBy(['platform_id', 'platform_account_id', 'created_at'])
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

        $this->recentImports = $imports;
    }

    public function loadSystemAlerts()
    {
        $alerts = [];

        // Check for platforms without accounts
        $platformsWithoutAccounts = Platform::whereDoesntHave('accounts')->where('is_active', true)->count();
        if ($platformsWithoutAccounts > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Platforms Missing Accounts',
                'message' => "{$platformsWithoutAccounts} active platforms don't have any accounts configured.",
                'action' => 'platforms.index',
                'action_text' => 'Configure Accounts'
            ];
        }

        // Check for expired credentials
        $expiredCredentials = PlatformApiCredential::where('expires_at', '<', now())->where('is_active', true)->count();
        if ($expiredCredentials > 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Expired Credentials',
                'message' => "{$expiredCredentials} API credentials have expired and need to be renewed.",
                'action' => 'platforms.index',
                'action_text' => 'Review Credentials'
            ];
        }

        // Check for credentials expiring soon
        $expiringSoon = PlatformApiCredential::whereBetween('expires_at', [now(), now()->addDays(7)])->where('is_active', true)->count();
        if ($expiringSoon > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Credentials Expiring Soon',
                'message' => "{$expiringSoon} API credentials will expire within 7 days.",
                'action' => 'platforms.index',
                'action_text' => 'Review Credentials'
            ];
        }

        // Check for inactive accounts
        $inactiveAccounts = PlatformAccount::where('is_active', false)->count();
        if ($inactiveAccounts > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Inactive Accounts',
                'message' => "{$inactiveAccounts} platform accounts are currently inactive.",
                'action' => 'platforms.index',
                'action_text' => 'Review Accounts'
            ];
        }

        $this->systemAlerts = $alerts;
    }

    public function getPlatformStatus($platform, $accountsCount)
    {
        if (!$platform->is_active) {
            return ['status' => 'inactive', 'color' => 'red', 'text' => 'Inactive'];
        }

        if ($accountsCount === 0) {
            return ['status' => 'setup_needed', 'color' => 'amber', 'text' => 'Setup Needed'];
        }

        return ['status' => 'active', 'color' => 'green', 'text' => 'Active'];
    }

    public function getStatusColor($status)
    {
        return match($status) {
            'pending' => 'amber',
            'confirmed' => 'blue',
            'processing' => 'purple',
            'shipped' => 'indigo',
            'delivered' => 'green',
            'completed' => 'green',
            'cancelled' => 'red',
            'refunded' => 'red',
            default => 'zinc',
        };
    }

    public function getOrderStatusOptions()
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'completed' => 'Completed',
        ];
    }

    public function refreshDashboard()
    {
        $this->loadDashboardData();
        $this->dispatch('dashboard-refreshed', ['message' => 'Dashboard data refreshed successfully']);
    }
}; ?>

<div>
    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Platform Integration Dashboard</flux:heading>
            <flux:text class="mt-2">Central command center for managing your e-commerce platform integrations</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" wire:click="refreshDashboard">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                    Refresh
                </div>
            </flux:button>
            <flux:button variant="primary" :href="route('platforms.create')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    Add Platform
                </div>
            </flux:button>
        </div>
    </div>

    {{-- System Alerts --}}
    @if(count($systemAlerts) > 0)
    <div class="mb-6 space-y-3">
        @foreach($systemAlerts as $alert)
            <div class="rounded-lg border p-4 @if($alert['type'] === 'error') bg-red-50 border-red-200 @elseif($alert['type'] === 'warning') bg-amber-50 border-amber-200 @else bg-blue-50 border-blue-200 @endif">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            @if($alert['type'] === 'error')
                                <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-600" />
                            @elseif($alert['type'] === 'warning')
                                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-600" />
                            @else
                                <flux:icon name="information-circle" class="w-5 h-5 text-blue-600" />
                            @endif
                        </div>
                        <div class="ml-3">
                            <flux:text class="font-medium @if($alert['type'] === 'error') text-red-800 @elseif($alert['type'] === 'warning') text-amber-800 @else text-blue-800 @endif">
                                {{ $alert['title'] }}
                            </flux:text>
                            <flux:text size="sm" class="@if($alert['type'] === 'error') text-red-700 @elseif($alert['type'] === 'warning') text-amber-700 @else text-blue-700 @endif">
                                {{ $alert['message'] }}
                            </flux:text>
                        </div>
                    </div>
                    @if(isset($alert['action']))
                        <flux:button variant="outline" size="sm" :href="route($alert['action'])" wire:navigate>
                            {{ $alert['action_text'] }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Key Metrics --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="squares-2x2" class="w-4 h-4 text-blue-600" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600">Active Platforms</flux:text>
                    <flux:text class="font-semibold">{{ $stats['active_platforms'] }} / {{ $stats['total_platforms'] }}</flux:text>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="building-storefront" class="w-4 h-4 text-green-600" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600">Active Accounts</flux:text>
                    <flux:text class="font-semibold">{{ $stats['active_accounts'] }} / {{ $stats['total_accounts'] }}</flux:text>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="shopping-bag" class="w-4 h-4 text-purple-600" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600">Total Orders</flux:text>
                    <flux:text class="font-semibold">{{ number_format($stats['total_orders']) }}</flux:text>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="currency-dollar" class="w-4 h-4 text-amber-600" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600">Total Revenue</flux:text>
                    <flux:text class="font-semibold">${{ number_format($stats['total_revenue'], 2) }}</flux:text>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Platform Overview --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Platform Summaries --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Platform Overview</flux:heading>
                        <flux:button variant="outline" size="sm" :href="route('platforms.index')" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                View All
                            </div>
                        </flux:button>
                    </div>
                </div>

                @if(count($platformSummaries) > 0)
                    <div class="p-6 space-y-4">
                        @foreach($platformSummaries as $summary)
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        @if($summary['platform']->logo_url)
                                            <img src="{{ $summary['platform']->logo_url }}" alt="{{ $summary['platform']->name }}" class="w-10 h-10 rounded-lg">
                                        @else
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg font-bold"
                                                 style="background: {{ $summary['platform']->color_primary ?? '#6b7280' }}">
                                                {{ substr($summary['platform']->name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <flux:text class="font-medium">{{ $summary['platform']->display_name }}</flux:text>
                                            <div class="flex items-center space-x-2 mt-1">
                                                <flux:badge size="sm" :color="$summary['status']['color']">
                                                    {{ $summary['status']['text'] }}
                                                </flux:badge>
                                                <flux:text size="sm" class="text-zinc-600">{{ $summary['accounts_count'] }} accounts</flux:text>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <flux:text class="font-medium">${{ number_format($summary['revenue'], 2) }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-600">{{ number_format($summary['orders_count']) }} orders</flux:text>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between mt-4 pt-4 border-t">
                                    <div class="flex items-center space-x-4">
                                        @if($summary['last_import'])
                                            <flux:text size="sm" class="text-zinc-600">
                                                Last import: {{ $summary['last_import']->imported_at->diffForHumans() }}
                                            </flux:text>
                                        @else
                                            <flux:text size="sm" class="text-zinc-500 italic">No imports yet</flux:text>
                                        @endif
                                    </div>

                                    <div class="flex space-x-2">
                                        <flux:button variant="ghost" size="sm" :href="route('platforms.orders.index', $summary['platform'])" wire:navigate>
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="shopping-bag" class="w-4 h-4 mr-1" />
                                                Orders
                                            </div>
                                        </flux:button>
                                        <flux:button variant="ghost" size="sm" :href="route('platforms.show', $summary['platform'])" wire:navigate>
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                                View
                                            </div>
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-12 text-center">
                        <div class="mx-auto w-12 h-12 bg-zinc-100 rounded-lg flex items-center justify-center mb-4">
                            <flux:icon name="squares-2x2" class="w-6 h-6 text-zinc-400" />
                        </div>
                        <flux:heading size="lg" class="mb-2">No Platforms</flux:heading>
                        <flux:text class="text-zinc-600 mb-4">Get started by adding your first e-commerce platform.</flux:text>
                        <flux:button variant="primary" :href="route('platforms.create')" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                                Add Your First Platform
                            </div>
                        </flux:button>
                    </div>
                @endif
            </div>

            {{-- Recent Orders --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="p-6 border-b border-gray-200">
                    <flux:heading size="lg">Recent Orders</flux:heading>
                </div>

                @if($recentOrders->count() > 0)
                    <div class="p-6">
                        <div class="space-y-3">
                            @foreach($recentOrders as $order)
                                <div class="flex items-center justify-between p-3 border rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold"
                                             style="background: {{ $order->platform->color_primary ?? '#6b7280' }}">
                                            {{ substr($order->platform->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <flux:text class="font-medium">{{ $order->platform_order_id }}</flux:text>
                                            <flux:text size="sm" class="text-zinc-600">{{ $order->platform->display_name }}</flux:text>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-3">
                                        <flux:badge size="sm" :color="$this->getStatusColor($order->status)">
                                            {{ $this->getOrderStatusOptions()[$order->status] ?? $order->status }}
                                        </flux:badge>
                                        <flux:text class="font-medium">${{ number_format($order->total_amount, 2) }}</flux:text>
                                        <flux:button variant="ghost" size="sm" :href="route('platforms.orders.show', [$order->platform, $order])" wire:navigate>
                                            <flux:icon name="eye" class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="p-12 text-center">
                        <flux:icon name="shopping-bag" class="w-12 h-12 text-zinc-400 mx-auto mb-4" />
                        <flux:text class="text-zinc-600">No orders imported yet</flux:text>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                <div class="space-y-3">
                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.create')" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-2" />
                            Add Platform
                        </div>
                    </flux:button>

                    @if(count($platformSummaries) > 0)
                        @php $firstActivePlatform = collect($platformSummaries)->firstWhere('status.status', 'active')['platform'] ?? null; @endphp
                        @if($firstActivePlatform)
                            <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.create', $firstActivePlatform)" wire:navigate>
                                <div class="flex items-center justify-center">
                                    <flux:icon name="building-storefront" class="w-4 h-4 mr-2" />
                                    Add Account
                                </div>
                            </flux:button>

                            <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.orders.import', $firstActivePlatform)" wire:navigate>
                                <div class="flex items-center justify-center">
                                    <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-2" />
                                    Import Orders
                                </div>
                            </flux:button>
                        @endif
                    @endif

                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.index')" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="squares-2x2" class="w-4 h-4 mr-2" />
                            View All Platforms
                        </div>
                    </flux:button>
                </div>
            </div>

            {{-- Recent Import Activity --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Recent Imports</flux:heading>

                @if($recentImports->count() > 0)
                    <div class="space-y-3">
                        @foreach($recentImports as $import)
                            <div class="border rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:text size="sm" class="font-medium">{{ $import->platform->display_name }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-600">{{ $import->orders_count }} orders</flux:text>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text size="sm" class="text-zinc-600">{{ $import->platformAccount->name }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-600">${{ number_format($import->total_value, 2) }}</flux:text>
                                </div>
                                <flux:text size="xs" class="text-zinc-500 mt-1">{{ $import->imported_at->diffForHumans() }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text size="sm" class="text-zinc-600">No recent imports</flux:text>
                @endif
            </div>

            {{-- System Health --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">System Health</flux:heading>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:text size="sm" class="text-zinc-600">Platform Status</flux:text>
                        <flux:badge size="sm" :color="$stats['active_platforms'] > 0 ? 'green' : 'red'">
                            {{ $stats['active_platforms'] > 0 ? 'Operational' : 'Setup Required' }}
                        </flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <flux:text size="sm" class="text-zinc-600">API Credentials</flux:text>
                        <flux:badge size="sm" :color="$stats['active_credentials'] > 0 ? 'green' : 'amber'">
                            {{ $stats['active_credentials'] }} Active
                        </flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <flux:text size="sm" class="text-zinc-600">This Month</flux:text>
                        <flux:text size="sm" class="font-medium">${{ number_format($stats['revenue_this_month'], 2) }}</flux:text>
                    </div>

                    <div class="flex items-center justify-between">
                        <flux:text size="sm" class="text-zinc-600">Orders This Month</flux:text>
                        <flux:text size="sm" class="font-medium">{{ number_format($stats['orders_this_month']) }}</flux:text>
                    </div>
                </div>
            </div>

            {{-- Future API Features --}}
            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg border border-indigo-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="text-indigo-900">Future API Features</flux:heading>
                    <flux:badge size="sm" color="purple">Roadmap</flux:badge>
                </div>

                <flux:text size="sm" class="text-indigo-800 mb-4">
                    Powerful automation features planned for future releases:
                </flux:text>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <flux:icon name="arrow-path" class="w-4 h-4 text-indigo-600" />
                            <flux:text size="sm" class="text-indigo-800">Auto Order Sync</flux:text>
                        </div>
                        <flux:badge size="sm" color="amber">Coming Soon</flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <flux:icon name="cube" class="w-4 h-4 text-indigo-600" />
                            <flux:text size="sm" class="text-indigo-800">Product Sync</flux:text>
                        </div>
                        <flux:badge size="sm" color="amber">Coming Soon</flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <flux:icon name="globe-alt" class="w-4 h-4 text-indigo-600" />
                            <flux:text size="sm" class="text-indigo-800">Webhooks</flux:text>
                        </div>
                        <flux:badge size="sm" color="amber">Coming Soon</flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <flux:icon name="chart-bar" class="w-4 h-4 text-indigo-600" />
                            <flux:text size="sm" class="text-indigo-800">Real-time Analytics</flux:text>
                        </div>
                        <flux:badge size="sm" color="amber">Coming Soon</flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <flux:icon name="bolt" class="w-4 h-4 text-indigo-600" />
                            <flux:text size="sm" class="text-indigo-800">Auto Inventory</flux:text>
                        </div>
                        <flux:badge size="sm" color="amber">Coming Soon</flux:badge>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-indigo-200">
                    <flux:text size="xs" class="text-indigo-700">
                        💡 Currently in manual mode. API automation will enhance efficiency significantly.
                    </flux:text>
                </div>
            </div>
        </div>
    </div>
</div>