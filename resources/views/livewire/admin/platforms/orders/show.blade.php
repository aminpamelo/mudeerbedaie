<?php

use Livewire\Volt\Component;
use App\Models\Platform;
use App\Models\PlatformOrder;

new class extends Component {
    public Platform $platform;
    public PlatformOrder $order;

    public function mount(Platform $platform, PlatformOrder $order)
    {
        $this->platform = $platform;
        $this->order = $order;

        // Ensure the order belongs to this platform
        if ($order->platform_id !== $platform->id) {
            abort(404);
        }
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

    public function getStatusIcon($status)
    {
        return match($status) {
            'pending' => 'clock',
            'confirmed' => 'check-circle',
            'processing' => 'cog',
            'shipped' => 'truck',
            'delivered' => 'home',
            'completed' => 'check-badge',
            'cancelled' => 'x-circle',
            'refunded' => 'arrow-uturn-left',
            default => 'question-mark-circle',
        };
    }

    public function getStatusOptions()
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
                        <flux:button variant="ghost" size="sm" :href="route('platforms.show', $platform)" wire:navigate class="ml-4">
                            {{ $platform->display_name }}
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.orders.index', $platform)" wire:navigate class="ml-4">
                            Orders
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">{{ $order->platform_order_id }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Order {{ $order->platform_order_id }}</flux:heading>
            <flux:text class="mt-2">Order details from {{ $platform->display_name }}</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:badge size="lg" :color="$this->getStatusColor($order->status)">
                <div class="flex items-center">
                    <flux:icon name="{{ $this->getStatusIcon($order->status) }}" class="w-4 h-4 mr-1" />
                    {{ $this->getStatusOptions()[$order->status] ?? $order->status }}
                </div>
            </flux:badge>
        </div>
    </div>

    {{-- Order Summary Card --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="p-6" style="background: linear-gradient(135deg, {{ $platform->color_primary ?? '#6b7280' }}15 0%, {{ $platform->color_secondary ?? '#9ca3af' }}15 100%);">
            <div class="flex items-center space-x-6">
                @if($platform->logo_url)
                    <img src="{{ $platform->logo_url }}" alt="{{ $platform->name }}" class="w-16 h-16 rounded-lg">
                @else
                    <div class="w-16 h-16 rounded-lg flex items-center justify-center text-white text-2xl font-bold"
                         style="background: {{ $platform->color_primary ?? '#6b7280' }}">
                        {{ substr($platform->name, 0, 1) }}
                    </div>
                @endif
                <div class="flex-1">
                    <div class="flex items-center space-x-4 mb-2">
                        <flux:heading size="lg">{{ $order->platform_order_id }}</flux:heading>
                        <flux:badge size="sm" :color="$this->getStatusColor($order->status)">
                            {{ $this->getStatusOptions()[$order->status] ?? $order->status }}
                        </flux:badge>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <flux:text size="sm" class="text-zinc-600">Order Date</flux:text>
                            @if($order->order_date)
                                <flux:text class="font-medium">{{ $order->order_date->format('M j, Y g:i A') }}</flux:text>
                            @else
                                <flux:text class="font-medium text-zinc-500">No date</flux:text>
                            @endif
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-600">Total Amount</flux:text>
                            <flux:text class="font-medium">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-600">Items Count</flux:text>
                            <flux:text class="font-medium">{{ $order->items_count }} items</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-600">Platform Account</flux:text>
                            <flux:text class="font-medium">{{ $order->platformAccount->name }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Order Details --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Order Information</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Platform Order ID</flux:text>
                        <flux:text class="font-medium">{{ $order->platform_order_id }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Status</flux:text>
                        <flux:badge size="sm" :color="$this->getStatusColor($order->status)">
                            {{ $this->getStatusOptions()[$order->status] ?? $order->status }}
                        </flux:badge>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Order Date</flux:text>
                        @if($order->order_date)
                            <flux:text>{{ $order->order_date->format('M j, Y g:i A') }}</flux:text>
                        @else
                            <flux:text class="text-zinc-500">No date</flux:text>
                        @endif
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Currency</flux:text>
                        <flux:text>{{ $order->currency }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Total Amount</flux:text>
                        <flux:text class="font-medium text-lg">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Platform Fees</flux:text>
                        <flux:text>{{ $order->currency }} {{ number_format($order->platform_fees, 2) }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Items Count</flux:text>
                        <flux:text>{{ $order->items_count }} items</flux:text>
                    </div>

                    @if($order->tracking_number)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Tracking Number</flux:text>
                        <flux:text class="font-mono">{{ $order->tracking_number }}</flux:text>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Customer Information --}}
            @if($order->customer_name || $order->customer_email || $order->shipping_address)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Customer Information</flux:heading>

                <div class="space-y-4">
                    @if($order->customer_name)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Customer Name</flux:text>
                        <flux:text>{{ $order->customer_name }}</flux:text>
                    </div>
                    @endif

                    @if($order->customer_email)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Email Address</flux:text>
                        <flux:text>{{ $order->customer_email }}</flux:text>
                    </div>
                    @endif

                    @if($order->shipping_address)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Shipping Address</flux:text>
                        <flux:text>{{ $order->shipping_address }}</flux:text>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Raw Import Data --}}
            @if($order->metadata && isset($order->metadata['raw_data']))
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Raw Import Data</flux:heading>

                <div class="bg-zinc-50 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm text-zinc-700">{{ json_encode($order->metadata['raw_data'], JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                <div class="space-y-3">
                    <flux:button variant="outline" size="sm" class="w-full" disabled>
                        <div class="flex items-center justify-center">
                            <flux:icon name="pencil" class="w-4 h-4 mr-2" />
                            Edit Order
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" disabled>
                        <div class="flex items-center justify-center">
                            <flux:icon name="document-duplicate" class="w-4 h-4 mr-2" />
                            Duplicate Order
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" disabled>
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                            Sync with Platform
                        </div>
                    </flux:button>
                </div>

                <flux:text size="xs" class="text-zinc-500 mt-3">
                    Order editing features coming soon with API integration
                </flux:text>
            </div>

            {{-- Platform Account Info --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Platform Account</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Account Name</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $order->platformAccount->name }}</flux:text>
                    </div>

                    @if($order->platformAccount->account_id)
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Account ID</flux:text>
                        <flux:text size="sm" class="font-mono">{{ $order->platformAccount->account_id }}</flux:text>
                    </div>
                    @endif

                    @if($order->platformAccount->seller_center_id)
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Seller Center ID</flux:text>
                        <flux:text size="sm" class="font-mono">{{ $order->platformAccount->seller_center_id }}</flux:text>
                    </div>
                    @endif

                    <div class="pt-3 border-t">
                        <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.show', [$platform, $order->platformAccount])" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="eye" class="w-4 h-4 mr-2" />
                                View Account
                            </div>
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Import Information --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Import Information</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Imported At</flux:text>
                        @if($order->imported_at)
                            <flux:text size="sm">{{ $order->imported_at->format('M j, Y') }}</flux:text>
                        @else
                            <flux:text size="sm" class="text-zinc-500">No date</flux:text>
                        @endif
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Import Time</flux:text>
                        @if($order->imported_at)
                            <flux:text size="sm">{{ $order->imported_at->format('g:i A') }}</flux:text>
                        @else
                            <flux:text size="sm" class="text-zinc-500">No time</flux:text>
                        @endif
                    </div>

                    @if($order->metadata && isset($order->metadata['import_notes']) && $order->metadata['import_notes'])
                    <div class="pt-3 border-t">
                        <flux:text size="sm" class="text-zinc-600 mb-1">Import Notes</flux:text>
                        <flux:text size="sm">{{ $order->metadata['import_notes'] }}</flux:text>
                    </div>
                    @endif

                    <div class="pt-3 border-t">
                        <flux:text size="sm" class="text-zinc-600">Import Method</flux:text>
                        <flux:text size="sm">Manual CSV Upload</flux:text>
                    </div>
                </div>
            </div>

            {{-- Order Timeline --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Order Timeline</flux:heading>

                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Order Created</flux:text>
                            @if($order->order_date)
                                <flux:text size="sm" class="text-zinc-600">{{ $order->order_date->format('M j, Y g:i A') }}</flux:text>
                            @else
                                <flux:text size="sm" class="text-zinc-500">No date</flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Imported to System</flux:text>
                            @if($order->imported_at)
                                <flux:text size="sm" class="text-zinc-600">{{ $order->imported_at->format('M j, Y g:i A') }}</flux:text>
                            @else
                                <flux:text size="sm" class="text-zinc-500">No date</flux:text>
                            @endif
                        </div>
                    </div>

                    @if($order->created_at != $order->updated_at)
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-zinc-400 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Last Updated</flux:text>
                            @if($order->updated_at)
                                <flux:text size="sm" class="text-zinc-600">{{ $order->updated_at->format('M j, Y g:i A') }}</flux:text>
                            @else
                                <flux:text size="sm" class="text-zinc-500">No date</flux:text>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>