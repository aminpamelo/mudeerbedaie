<?php

use App\Models\Platform;
use App\Models\ProductOrder;
use Livewire\Volt\Component;

new class extends Component
{
    public Platform $platform;

    public ProductOrder $order;

    public function mount(Platform $platform, ProductOrder $order)
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
        return match ($status) {
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
        return match ($status) {
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
                            <flux:text class="font-medium">{{ $order->items->count() }} items</flux:text>
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
                        <flux:text size="sm" class="text-zinc-600 mb-1">Shipping Cost</flux:text>
                        <flux:text>{{ $order->currency }} {{ number_format($order->shipping_cost ?? 0, 2) }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Items Count</flux:text>
                        <flux:text>{{ $order->items->count() }} items</flux:text>
                    </div>

                    @if($order->tracking_id)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Tracking ID</flux:text>
                        <flux:text class="font-mono">{{ $order->tracking_id }}</flux:text>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Customer Information --}}
            @if($order->customer_name || $order->guest_email || $order->customer_phone || $order->shipping_address)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Customer Information</flux:heading>

                <div class="space-y-4">
                    @if($order->customer_name)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Customer Name</flux:text>
                        <flux:text>{{ $order->customer_name }}</flux:text>
                    </div>
                    @endif

                    @if($order->buyer_username)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Buyer Username</flux:text>
                        <flux:text>@{{ $order->buyer_username }}</flux:text>
                    </div>
                    @endif

                    @if($order->customer_phone)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Phone</flux:text>
                        <flux:text>{{ $order->customer_phone }}</flux:text>
                    </div>
                    @endif

                    @if($order->guest_email)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Email Address</flux:text>
                        <flux:text>{{ $order->guest_email }}</flux:text>
                    </div>
                    @endif

                    @if($order->shipping_address)
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Shipping Address</flux:text>
                        @if(is_array($order->shipping_address))
                            <flux:text>
                                {{ $order->shipping_address['full_address'] ?? '' }}
                                @if(isset($order->shipping_address['city']))
                                    {{ $order->shipping_address['city'] }}
                                @endif
                                @if(isset($order->shipping_address['state']))
                                    {{ $order->shipping_address['state'] }}
                                @endif
                                @if(isset($order->shipping_address['zipcode']))
                                    {{ $order->shipping_address['zipcode'] }}
                                @endif
                            </flux:text>
                        @else
                            <flux:text>{{ $order->shipping_address }}</flux:text>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Order Items --}}
            @if($order->items->count() > 0)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Order Items</flux:heading>

                <div class="space-y-4">
                    @foreach($order->items as $item)
                    <div class="flex items-start justify-between p-4 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg">
                        <div class="flex-1">
                            <flux:text class="font-medium">{{ $item->product_name }}</flux:text>
                            @if($item->variant_name)
                                <flux:text size="sm" class="text-zinc-500">{{ $item->variant_name }}</flux:text>
                            @endif
                            @if($item->sku)
                                <flux:text size="xs" class="text-zinc-400 font-mono">SKU: {{ $item->sku }}</flux:text>
                            @endif
                        </div>
                        <div class="text-right">
                            <flux:text class="font-medium">{{ $order->currency }} {{ number_format($item->unit_price, 2) }}</flux:text>
                            <flux:text size="sm" class="text-zinc-500">× {{ $item->quantity_ordered }}</flux:text>
                            <flux:text class="font-medium text-green-600">{{ $order->currency }} {{ number_format($item->total_price, 2) }}</flux:text>
                        </div>
                    </div>
                    @endforeach

                    {{-- Order Summary --}}
                    <div class="border-t border-gray-200 dark:border-zinc-600 pt-4 mt-4">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">Subtotal</flux:text>
                                <flux:text size="sm">{{ $order->currency }} {{ number_format($order->subtotal ?? $order->total_amount, 2) }}</flux:text>
                            </div>
                            @if($order->shipping_cost)
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">Shipping</flux:text>
                                <flux:text size="sm">{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</flux:text>
                            </div>
                            @endif
                            @if($order->discount_amount)
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-600">Discount</flux:text>
                                <flux:text size="sm" class="text-red-500">-{{ $order->currency }} {{ number_format($order->discount_amount, 2) }}</flux:text>
                            </div>
                            @endif
                            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-zinc-600">
                                <flux:text class="font-medium">Total</flux:text>
                                <flux:text class="font-bold text-lg">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</flux:text>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Raw Platform Data --}}
            @if($order->platform_data)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Raw Platform Data</flux:heading>

                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm text-zinc-700 dark:text-zinc-300">{{ json_encode($order->platform_data, JSON_PRETTY_PRINT) }}</pre>
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

            {{-- Sync Information --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Sync Information</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Synced At</flux:text>
                        <flux:text size="sm">{{ $order->created_at->format('M j, Y') }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Sync Time</flux:text>
                        <flux:text size="sm">{{ $order->created_at->format('g:i A') }}</flux:text>
                    </div>

                    @if($order->source)
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Source</flux:text>
                        <flux:text size="sm">{{ ucfirst(str_replace('_', ' ', $order->source)) }}</flux:text>
                    </div>
                    @endif

                    @if($order->metadata && isset($order->metadata['import_notes']) && $order->metadata['import_notes'])
                    <div class="pt-3 border-t">
                        <flux:text size="sm" class="text-zinc-600 mb-1">Notes</flux:text>
                        <flux:text size="sm">{{ $order->metadata['import_notes'] }}</flux:text>
                    </div>
                    @endif
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

                    @if($order->paid_time)
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-emerald-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Payment Confirmed</flux:text>
                            <flux:text size="sm" class="text-zinc-600">{{ $order->paid_time->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                    @endif

                    @if($order->shipped_at)
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-purple-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Shipped</flux:text>
                            <flux:text size="sm" class="text-zinc-600">{{ $order->shipped_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                    @endif

                    @if($order->delivered_at)
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Delivered</flux:text>
                            <flux:text size="sm" class="text-zinc-600">{{ $order->delivered_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                    @endif

                    @if($order->cancelled_at)
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Cancelled</flux:text>
                            <flux:text size="sm" class="text-zinc-600">{{ $order->cancelled_at->format('M j, Y g:i A') }}</flux:text>
                            @if($order->cancel_reason)
                                <flux:text size="xs" class="text-zinc-500">{{ $order->cancel_reason }}</flux:text>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-cyan-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Synced to System</flux:text>
                            <flux:text size="sm" class="text-zinc-600">{{ $order->created_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>

                    @if($order->created_at->ne($order->updated_at))
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-2 h-2 bg-zinc-400 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Last Updated</flux:text>
                            <flux:text size="sm" class="text-zinc-600">{{ $order->updated_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>