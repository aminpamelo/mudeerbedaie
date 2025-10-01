<?php

use App\Models\PlatformSkuMapping;
use App\Models\PlatformOrderItem;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public PlatformSkuMapping $mapping;
    public string $activeTab = 'details';

    public function mount(PlatformSkuMapping $mapping)
    {
        $this->mapping = $mapping->load([
            'platform',
            'platformAccount',
            'product.category',
            'productVariant',
            'platformOrderItems.platformOrder'
        ]);
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function toggleStatus()
    {
        $this->mapping->update(['is_active' => !$this->mapping->is_active]);
        $this->mapping->refresh();

        $this->dispatch('mapping-updated',
            message: $this->mapping->is_active ? 'SKU mapping activated' : 'SKU mapping deactivated'
        );
    }

    public function getOrderItemsProperty()
    {
        return $this->mapping->platformOrderItems()
            ->with(['platformOrder.platform', 'platformOrder.platformAccount'])
            ->latest()
            ->paginate(10);
    }

    public function with(): array
    {
        return [
            'orderItems' => $this->orderItems,
        ];
    }
}; ?>

<x-admin.layout title="SKU Mapping Details">
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-4">
                <flux:button variant="outline" :href="route('platforms.sku-mappings.index')" wire:navigate>
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-2" />
                    Back to SKU Mappings
                </flux:button>
            </div>

            <div class="flex items-center space-x-2">
                <flux:button
                    variant="outline"
                    wire:click="toggleStatus"
                >
                    <flux:icon name="{{ $mapping->is_active ? 'pause' : 'play' }}" class="w-4 h-4 mr-2" />
                    {{ $mapping->is_active ? 'Deactivate' : 'Activate' }}
                </flux:button>

                <flux:button variant="primary" :href="route('platforms.sku-mappings.edit', $mapping)" wire:navigate>
                    <flux:icon name="pencil" class="w-4 h-4 mr-2" />
                    Edit Mapping
                </flux:button>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <flux:heading size="xl">{{ $mapping->platform_sku }}</flux:heading>
            <flux:badge variant="{{ $mapping->is_active ? 'green' : 'gray' }}">
                {{ $mapping->is_active ? 'Active' : 'Inactive' }}
            </flux:badge>
        </div>

        @if($mapping->platform_product_name)
            <flux:text class="mt-2">{{ $mapping->platform_product_name }}</flux:text>
        @endif
    </div>

    <!-- Tab Navigation -->
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <button
                wire:click="setActiveTab('details')"
                class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'details' ? 'border-blue-500 text-blue-600' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
            >
                Details
            </button>
            <button
                wire:click="setActiveTab('usage')"
                class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'usage' ? 'border-blue-500 text-blue-600' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
            >
                Usage History
            </button>
        </nav>
    </div>

    @if($activeTab === 'details')
        <!-- Mapping Details -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Platform Information -->
            <div class="rounded-lg border border-zinc-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Platform Information</flux:heading>

                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Platform</dt>
                        <dd class="mt-1 flex items-center">
                            @if($mapping->platform->logo_url)
                                <img class="h-6 w-6 rounded mr-2" src="{{ $mapping->platform->logo_url }}" alt="{{ $mapping->platform->name }}">
                            @endif
                            <span class="text-sm text-zinc-900">{{ $mapping->platform->name }}</span>
                        </dd>
                    </div>

                    @if($mapping->platformAccount)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Platform Account</dt>
                            <dd class="mt-1 text-sm text-zinc-900">{{ $mapping->platformAccount->name }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Platform SKU</dt>
                        <dd class="mt-1 text-sm font-mono text-zinc-900 bg-zinc-50 px-2 py-1 rounded">{{ $mapping->platform_sku }}</dd>
                    </div>

                    @if($mapping->platform_product_name)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Platform Product Name</dt>
                            <dd class="mt-1 text-sm text-zinc-900">{{ $mapping->platform_product_name }}</dd>
                        </div>
                    @endif

                    @if($mapping->platform_variation_name)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Platform Variation Name</dt>
                            <dd class="mt-1 text-sm text-zinc-900">{{ $mapping->platform_variation_name }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Match Priority</dt>
                        <dd class="mt-1">
                            <flux:badge variant="{{ match($mapping->match_priority) {
                                'high' => 'green',
                                'medium' => 'yellow',
                                'low' => 'gray',
                                default => 'gray'
                            } }}">
                                {{ ucfirst($mapping->match_priority) }}
                            </flux:badge>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Product Information -->
            <div class="rounded-lg border border-zinc-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Product Information</flux:heading>

                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Product</dt>
                        <dd class="mt-1">
                            <div class="text-sm font-medium text-zinc-900">{{ $mapping->product->name }}</div>
                            <div class="text-sm text-zinc-500">SKU: {{ $mapping->product->sku }}</div>
                            @if($mapping->product->category)
                                <div class="text-xs text-zinc-400">Category: {{ $mapping->product->category->name }}</div>
                            @endif
                        </dd>
                    </div>

                    @if($mapping->productVariant)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Product Variant</dt>
                            <dd class="mt-1">
                                <div class="text-sm font-medium text-zinc-900">{{ $mapping->productVariant->name }}</div>
                                @if($mapping->productVariant->sku)
                                    <div class="text-sm text-zinc-500">SKU: {{ $mapping->productVariant->sku }}</div>
                                @endif
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Product Type</dt>
                        <dd class="mt-1 text-sm text-zinc-900">{{ ucfirst($mapping->product->type) }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Base Price</dt>
                        <dd class="mt-1 text-sm text-zinc-900">{{ $mapping->product->formatted_price }}</dd>
                    </div>

                    @if($mapping->productVariant && $mapping->productVariant->price)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Variant Price</dt>
                            <dd class="mt-1 text-sm text-zinc-900">RM {{ number_format($mapping->productVariant->price, 2) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Usage Statistics -->
            <div class="rounded-lg border border-zinc-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Usage Statistics</flux:heading>

                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Total Usage</dt>
                        <dd class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($mapping->usage_count) }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Last Used</dt>
                        <dd class="mt-1 text-sm text-zinc-900">
                            {{ $mapping->last_used_at ? $mapping->last_used_at->diffForHumans() : 'Never used' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Created</dt>
                        <dd class="mt-1 text-sm text-zinc-900">{{ $mapping->created_at->diffForHumans() }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Last Updated</dt>
                        <dd class="mt-1 text-sm text-zinc-900">{{ $mapping->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Additional Information -->
            <div class="rounded-lg border border-zinc-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Additional Information</flux:heading>

                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500">Status</dt>
                        <dd class="mt-1">
                            <flux:badge variant="{{ $mapping->is_active ? 'green' : 'gray' }}">
                                {{ $mapping->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </dd>
                    </div>

                    @if($mapping->notes)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Notes</dt>
                            <dd class="mt-1 text-sm text-zinc-900 whitespace-pre-wrap">{{ $mapping->notes }}</dd>
                        </div>
                    @endif

                    @if($mapping->metadata)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Metadata</dt>
                            <dd class="mt-1 text-xs font-mono text-zinc-600 bg-zinc-50 p-2 rounded">
                                {{ json_encode($mapping->metadata, JSON_PRETTY_PRINT) }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    @endif

    @if($activeTab === 'usage')
        <!-- Usage History -->
        <div class="rounded-lg border border-zinc-200 bg-white">
            <div class="px-6 py-4 border-b border-zinc-200">
                <flux:heading size="lg">Usage History</flux:heading>
                <flux:text class="mt-1">Recent orders that used this SKU mapping</flux:text>
            </div>

            @if($orderItems->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200">
                        <thead class="bg-zinc-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-zinc-200">
                            @foreach($orderItems as $item)
                                <tr class="hover:bg-zinc-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-zinc-900">
                                            {{ $item->platformOrder->display_order_id }}
                                        </div>
                                        @if($item->platformOrder->reference_number)
                                            <div class="text-xs text-zinc-500">{{ $item->platformOrder->reference_number }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            @if($item->platformOrder->platform->logo_url)
                                                <img class="h-6 w-6 rounded mr-2" src="{{ $item->platformOrder->platform->logo_url }}" alt="{{ $item->platformOrder->platform->name }}">
                                            @endif
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900">{{ $item->platformOrder->platform->name }}</div>
                                                @if($item->platformOrder->platformAccount)
                                                    <div class="text-xs text-zinc-500">{{ $item->platformOrder->platformAccount->name }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-zinc-900">{{ $item->platformOrder->customer_name }}</div>
                                        @if($item->platformOrder->buyer_username)
                                            <div class="text-xs text-zinc-500">@{{ $item->platformOrder->buyer_username }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-900">
                                        {{ number_format($item->quantity) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-900">
                                        {{ $item->platformOrder->currency }} {{ number_format($item->unit_price * $item->quantity, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500">
                                        {{ $item->platformOrder->platform_created_at->format('M j, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <flux:button
                                            variant="outline"
                                            size="sm"
                                            :href="route('platforms.orders.show', [$item->platformOrder->platform, $item->platformOrder])"
                                            wire:navigate
                                        >
                                            <flux:icon name="eye" class="w-4 h-4" />
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($orderItems->hasPages())
                    <div class="px-6 py-4 border-t border-zinc-200">
                        {{ $orderItems->links() }}
                    </div>
                @endif
            @else
                <div class="px-6 py-12 text-center">
                    <flux:icon name="document-text" class="mx-auto h-12 w-12 text-zinc-400" />
                    <div class="mt-4">
                        <flux:heading size="lg" class="text-zinc-900">No usage history</flux:heading>
                        <flux:text class="mt-2 text-zinc-500">
                            This SKU mapping hasn't been used in any orders yet.
                        </flux:text>
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-admin.layout>