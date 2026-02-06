<?php

use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Product $product;
    public $activeTab = 'product-details';

    public function mount(Product $product): void
    {
        $this->product = $product->load(['category', 'stockLevels.warehouse', 'variants', 'media']);
    }

    public function setActiveTab($tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function with(): array
    {
        $data = [];

        if ($this->activeTab === 'stock-movements') {
            $data['stockMovements'] = StockMovement::query()
                ->with(['warehouse', 'productVariant', 'createdBy'])
                ->where('product_id', $this->product->id)
                ->latest()
                ->paginate(10);
        }

        return $data;
    }

    public function delete(): void
    {
        $this->product->delete();

        session()->flash('success', 'Product deleted successfully.');

        $this->redirect(route('products.index'));
    }

    public function toggleStatus(): void
    {
        $newStatus = match($this->product->status) {
            'active' => 'inactive',
            'inactive' => 'active',
            'draft' => 'active',
            default => 'inactive',
        };

        $this->product->update(['status' => $newStatus]);

        session()->flash('success', 'Product status updated successfully.');
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $product->name }}</flux:heading>
            <flux:text class="mt-2">Product details and inventory information</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:button variant="outline" href="{{ route('products.index') }}" icon="arrow-left">
                Back to Products
            </flux:button>
            <flux:button href="{{ route('products.edit', $product) }}" icon="pencil">
                Edit Product
            </flux:button>
        </div>
    </div>

    <!-- Product Status Badge -->
    <div class="mb-6">
        <flux:badge :variant="match($product->status) {
            'active' => 'success',
            'inactive' => 'gray',
            'draft' => 'warning',
            default => 'gray'
        }" size="lg">
            {{ ucfirst($product->status) }}
        </flux:badge>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Product Information with Tabs -->
        <div class="lg:col-span-2">
            <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 dark:border-zinc-700">
                    <nav class="-mb-px flex space-x-8 px-6 py-4" aria-label="Tabs">
                        <button
                            wire:click="setActiveTab('product-details')"
                            class="flex items-center whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium transition-colors duration-200 {{ $activeTab === 'product-details' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                        >
                            <flux:icon name="cube" class="mr-2 h-4 w-4" />
                            Product Details
                        </button>
                        <button
                            wire:click="setActiveTab('stock-levels')"
                            class="flex items-center whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium transition-colors duration-200 {{ $activeTab === 'stock-levels' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                        >
                            <flux:icon name="squares-2x2" class="mr-2 h-4 w-4" />
                            Stock Levels ({{ $product->stockLevels->count() }})
                        </button>
                        <button
                            wire:click="setActiveTab('stock-movements')"
                            class="flex items-center whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium transition-colors duration-200 {{ $activeTab === 'stock-movements' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                        >
                            <flux:icon name="arrow-path" class="mr-2 h-4 w-4" />
                            Stock Movements
                        </button>
                        <button
                            wire:click="setActiveTab('images')"
                            class="flex items-center whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium transition-colors duration-200 {{ $activeTab === 'images' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                        >
                            <flux:icon name="photo" class="mr-2 h-4 w-4" />
                            Images ({{ $product->media->count() }})
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    @if($activeTab === 'product-details')
                        <!-- Product Details Tab -->
                        <div class="space-y-6">
                            <!-- Basic Information -->
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <flux:heading size="lg">Product Information</flux:heading>
                                    <flux:button wire:click="toggleStatus" variant="outline" size="sm">
                                        {{ $product->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                </div>

                                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">SKU</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                            <code class="text-sm">{{ $product->sku }}</code>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                            @if($product->category)
                                                <flux:badge variant="outline" size="sm">{{ $product->category->name }}</flux:badge>
                                            @else
                                                <span class="text-gray-400">Uncategorized</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                            <flux:badge variant="outline" size="sm">{{ ucfirst($product->type) }}</flux:badge>
                                        </dd>
                                    </div>
                                    @if($product->barcode)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Barcode</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                                <code class="text-sm">{{ $product->barcode }}</code>
                                            </dd>
                                        </div>
                                    @endif
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->created_at->format('M j, Y g:i A') }}</dd>
                                    </div>
                                </dl>

                                @if($product->short_description)
                                    <div class="mt-6">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Short Description</dt>
                                        <dd class="mt-2 text-sm text-gray-900 dark:text-gray-100">{{ $product->short_description }}</dd>
                                    </div>
                                @endif

                                @if($product->description)
                                    <div class="mt-6">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                        <dd class="mt-2 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $product->description }}</dd>
                                    </div>
                                @endif
                            </div>

                            <!-- Pricing Information -->
                            <div class="border-t border-gray-200 dark:border-zinc-700 pt-6">
                                <flux:heading size="lg" class="mb-4">Pricing</flux:heading>
                                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Selling Price</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $product->formatted_price }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cost Price</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900">RM {{ number_format($product->cost_price, 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Profit Margin</dt>
                                        <dd class="mt-1 text-lg font-semibold {{ $product->base_price > $product->cost_price ? 'text-green-600' : 'text-red-600' }}">
                                            RM {{ number_format($product->base_price - $product->cost_price, 2) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Profit %</dt>
                                        <dd class="mt-1 text-lg font-semibold {{ $product->base_price > $product->cost_price ? 'text-green-600' : 'text-red-600' }}">
                                            @if($product->cost_price > 0)
                                                {{ number_format((($product->base_price - $product->cost_price) / $product->cost_price) * 100, 1) }}%
                                            @else
                                                N/A
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                    @elseif($activeTab === 'stock-levels')
                        <!-- Stock Levels Tab -->
                        @if($product->stockLevels->count() > 0)
                            <div class="space-y-4">
                                <flux:heading size="lg" class="mb-4">Stock Levels by Warehouse</flux:heading>
                                @foreach($product->stockLevels as $stockLevel)
                                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-700 pb-4 last:border-b-0 last:pb-0">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $stockLevel->warehouse->name }}</div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        {{ $stockLevel->warehouse->address['city'] ?? '' }}
                                                        @if($stockLevel->warehouse->address['city'] && $stockLevel->warehouse->code)
                                                            • Code: {{ $stockLevel->warehouse->code }}
                                                        @elseif($stockLevel->warehouse->code)
                                                            Code: {{ $stockLevel->warehouse->code }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-lg font-semibold {{ $stockLevel->quantity <= 0 ? 'text-red-600' : ($stockLevel->quantity <= $product->min_quantity ? 'text-yellow-600' : 'text-gray-900') }}">
                                                        {{ number_format($stockLevel->quantity) }} units
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        Available: {{ number_format($stockLevel->quantity - $stockLevel->reserved_quantity) }}
                                                        @if($stockLevel->reserved_quantity > 0)
                                                            • Reserved: {{ number_format($stockLevel->reserved_quantity) }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @if($stockLevel->last_movement_at)
                                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                    Last updated: {{ $stockLevel->last_movement_at->format('M j, Y g:i A') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="squares-2x2" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No stock levels</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This product doesn't have stock levels in any warehouse yet.</p>
                            </div>
                        @endif

                    @elseif($activeTab === 'stock-movements')
                        <!-- Stock Movements Tab -->
                        @if(isset($stockMovements) && $stockMovements->count() > 0)
                            <div class="space-y-4">
                                <flux:heading size="lg" class="mb-4">Stock Movements History</flux:heading>
                                @foreach($stockMovements as $movement)
                                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-700 pb-4 last:border-b-0 last:pb-0">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $movement->warehouse->name }}</div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        {{ $movement->created_at->format('M j, Y g:i A') }}
                                                        @if($movement->productVariant)
                                                            • Variant: {{ $movement->productVariant->name }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $movement->createdBy?->name ?? 'System' }}</div>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between">
                                                <div class="flex items-center space-x-4">
                                                    <flux:badge
                                                        :variant="match($movement->type) {
                                                            'in' => 'success',
                                                            'out' => 'danger',
                                                            'adjustment' => 'warning',
                                                            'transfer' => 'info',
                                                            default => 'outline'
                                                        }"
                                                        size="sm"
                                                    >
                                                        {{ $movement->formatted_type }}
                                                    </flux:badge>

                                                    @php $reference = $movement->formatted_reference @endphp
                                                    @if($reference['label'] !== '-')
                                                        @if($reference['clickable'] && $reference['url'])
                                                            <a href="{{ $reference['url'] }}" wire:navigate class="inline-block hover:opacity-80 transition-opacity">
                                                                <flux:badge
                                                                    :variant="$reference['variant']"
                                                                    size="sm"
                                                                    :icon="$reference['icon']"
                                                                >
                                                                    {{ $reference['label'] }}
                                                                </flux:badge>
                                                            </a>
                                                        @else
                                                            <flux:badge
                                                                :variant="$reference['variant']"
                                                                size="sm"
                                                                :icon="$reference['icon']"
                                                            >
                                                                {{ $reference['label'] }}
                                                            </flux:badge>
                                                        @endif
                                                    @endif
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-medium {{ $movement->quantity >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ $movement->display_quantity }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ number_format($movement->quantity_before) }} → {{ number_format($movement->quantity_after) }}
                                                    </div>
                                                </div>
                                            </div>
                                            @if($movement->notes)
                                                <div class="mt-2 text-xs text-gray-600 italic">
                                                    {{ $movement->notes }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                <!-- Pagination -->
                                <div class="mt-6">
                                    {{ $stockMovements->links() }}
                                </div>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="arrow-path" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No stock movements</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No inventory transactions have been recorded for this product yet.</p>
                            </div>
                        @endif

                    @elseif($activeTab === 'images')
                        <!-- Images Tab -->
                        @if($product->media->count() > 0)
                            <div class="space-y-6">
                                <div class="flex items-center justify-between">
                                    <flux:heading size="lg" class="mb-4">Product Images</flux:heading>
                                    <flux:button href="{{ route('products.edit', $product) }}" variant="outline" size="sm" icon="pencil">
                                        Manage Images
                                    </flux:button>
                                </div>

                                <!-- Image Grid -->
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                    @foreach($product->media->sortBy('sort_order') as $index => $image)
                                        <div class="relative group rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden" wire:key="product-image-{{ $image->id }}">
                                            <img src="{{ $image->url }}" alt="{{ $image->alt_text ?? $product->name }}" class="w-full h-48 object-cover">

                                            <!-- Image overlay on hover -->
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-all duration-200 flex items-center justify-center">
                                                <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                    <flux:button
                                                        onclick="window.open('{{ $image->url }}', '_blank')"
                                                        size="sm"
                                                        variant="primary"
                                                        icon="magnifying-glass-plus"
                                                    >
                                                        View Full Size
                                                    </flux:button>
                                                </div>
                                            </div>

                                            <!-- Primary image badge -->
                                            @if($image->is_primary)
                                                <div class="absolute top-2 left-2">
                                                    <flux:badge variant="success" size="sm" icon="star">
                                                        Primary
                                                    </flux:badge>
                                                </div>
                                            @endif

                                            <!-- Image info -->
                                            <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-75 text-white text-xs p-2">
                                                <div class="flex items-center justify-between">
                                                    <span>{{ $image->formatted_file_size }}</span>
                                                    <span>{{ pathinfo($image->file_name, PATHINFO_EXTENSION) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Primary Image Large Preview -->
                                @php $primaryImage = $product->primaryImage @endphp
                                @if($primaryImage)
                                    <div class="border-t border-gray-200 dark:border-zinc-700 pt-6">
                                        <flux:heading size="md" class="mb-4">Primary Image</flux:heading>
                                        <div class="max-w-md">
                                            <div class="relative rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
                                                <img src="{{ $primaryImage->url }}" alt="{{ $primaryImage->alt_text ?? $product->name }}" class="w-full h-64 object-cover">

                                                <!-- Image details -->
                                                <div class="p-4 bg-gray-50 dark:bg-zinc-700/50">
                                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                                        <div>
                                                            <dt class="font-medium text-gray-500">File Name</dt>
                                                            <dd class="text-gray-900">{{ $primaryImage->file_name }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500">File Size</dt>
                                                            <dd class="text-gray-900">{{ $primaryImage->formatted_file_size }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500">Type</dt>
                                                            <dd class="text-gray-900">{{ $primaryImage->mime_type }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500">Uploaded</dt>
                                                            <dd class="text-gray-900">{{ $primaryImage->created_at->format('M j, Y') }}</dd>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="photo" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No images</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This product doesn't have any images yet.</p>
                                <div class="mt-6">
                                    <flux:button href="{{ route('products.edit', $product) }}" variant="primary" icon="plus">
                                        Add Images
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar Information -->
        <div class="space-y-6">
            <!-- Product Image -->
            @if($product->primaryImage)
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <flux:heading size="lg" class="mb-4">Product Image</flux:heading>
                    <div class="relative rounded-lg overflow-hidden">
                        <img src="{{ $product->primaryImage->url }}" alt="{{ $product->name }}" class="w-full h-48 object-cover">
                        <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-25 transition-all duration-200 flex items-center justify-center">
                            <div class="opacity-0 hover:opacity-100 transition-opacity duration-200">
                                <flux:button
                                    wire:click="setActiveTab('images')"
                                    size="sm"
                                    variant="primary"
                                    icon="eye"
                                >
                                    View All
                                </flux:button>
                            </div>
                        </div>
                    </div>
                    @if($product->media->count() > 1)
                        <div class="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                            +{{ $product->media->count() - 1 }} more image{{ $product->media->count() > 2 ? 's' : '' }}
                        </div>
                    @endif
                </div>
            @endif

            <!-- Quick Stats -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Quick Stats</flux:heading>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Stock</span>
                        <span class="font-semibold text-gray-900">{{ $product->total_stock }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Available Stock</span>
                        <span class="font-semibold text-gray-900">{{ $product->available_stock }}</span>
                    </div>
                    @if($product->track_quantity)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Min Quantity</span>
                            <span class="font-semibold {{ $product->total_stock <= $product->min_quantity ? 'text-red-600' : 'text-gray-900' }}">
                                {{ $product->min_quantity }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Inventory Settings -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Inventory Settings</flux:heading>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Track Quantity</span>
                        <flux:badge :variant="$product->track_quantity ? 'success' : 'gray'" size="sm">
                            {{ $product->track_quantity ? 'Yes' : 'No' }}
                        </flux:badge>
                    </div>
                    @if($product->track_quantity && $product->total_stock <= $product->min_quantity)
                        <div class="rounded-md bg-red-50 p-3">
                            <div class="flex">
                                <flux:icon name="exclamation-triangle" class="h-5 w-5 text-red-400" />
                                <div class="ml-2">
                                    <h3 class="text-sm font-medium text-red-800">Low Stock Alert</h3>
                                    <p class="text-sm text-red-700 mt-1">
                                        Stock is at or below the minimum quantity threshold.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Actions</flux:heading>
                <div class="space-y-3">
                    <flux:button href="{{ route('products.edit', $product) }}" variant="primary" class="w-full" icon="pencil">
                        Edit Product
                    </flux:button>
                    <flux:button wire:click="toggleStatus" variant="outline" class="w-full">
                        {{ $product->status === 'active' ? 'Deactivate' : 'Activate' }} Product
                    </flux:button>
                    <flux:button
                        wire:click="delete"
                        wire:confirm="Are you sure you want to delete this product?"
                        variant="outline"
                        class="w-full text-red-600 border-red-200 hover:bg-red-50"
                        icon="trash"
                    >
                        Delete Product
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</div>