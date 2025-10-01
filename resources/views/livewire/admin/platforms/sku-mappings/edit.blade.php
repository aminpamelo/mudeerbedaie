<?php

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Volt\Component;

new class extends Component {
    public PlatformSkuMapping $mapping;

    public string $platform_id;
    public string $platform_account_id = '';
    public string $product_id;
    public string $product_variant_id = '';
    public string $platform_sku;
    public string $platform_product_name = '';
    public string $platform_variation_name = '';
    public string $match_priority;
    public bool $is_active;
    public string $notes = '';

    public array $availableAccounts = [];
    public array $availableVariants = [];

    public function mount(PlatformSkuMapping $mapping)
    {
        $this->mapping = $mapping;
        $this->platform_id = (string) $mapping->platform_id;
        $this->platform_account_id = (string) ($mapping->platform_account_id ?? '');
        $this->product_id = (string) $mapping->product_id;
        $this->product_variant_id = (string) ($mapping->product_variant_id ?? '');
        $this->platform_sku = $mapping->platform_sku;
        $this->platform_product_name = $mapping->platform_product_name ?? '';
        $this->platform_variation_name = $mapping->platform_variation_name ?? '';
        $this->match_priority = $mapping->match_priority;
        $this->is_active = $mapping->is_active;
        $this->notes = $mapping->notes ?? '';

        $this->loadPlatformAccounts();
        $this->loadProductVariants();
    }

    public function updatedPlatformId()
    {
        $this->platform_account_id = '';
        $this->loadPlatformAccounts();
    }

    public function updatedProductId()
    {
        $this->product_variant_id = '';
        $this->loadProductVariants();
    }

    public function loadPlatformAccounts()
    {
        if ($this->platform_id) {
            $this->availableAccounts = PlatformAccount::where('platform_id', $this->platform_id)->get()->toArray();
        } else {
            $this->availableAccounts = [];
        }
    }

    public function loadProductVariants()
    {
        if ($this->product_id) {
            $this->availableVariants = ProductVariant::where('product_id', $this->product_id)->get()->toArray();
        } else {
            $this->availableVariants = [];
        }
    }

    public function save()
    {
        $this->validate([
            'platform_id' => 'required|exists:platforms,id',
            'platform_sku' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'platform_product_name' => 'nullable|string|max:255',
            'platform_variation_name' => 'nullable|string|max:255',
            'match_priority' => 'required|in:low,medium,high',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check for duplicate mappings (excluding current mapping)
        $existingMapping = PlatformSkuMapping::where('platform_id', $this->platform_id)
            ->when($this->platform_account_id, fn($q) => $q->where('platform_account_id', $this->platform_account_id))
            ->where('platform_sku', $this->platform_sku)
            ->where('id', '!=', $this->mapping->id)
            ->first();

        if ($existingMapping) {
            $this->addError('platform_sku', 'A mapping for this platform SKU already exists.');
            return;
        }

        $this->mapping->update([
            'platform_id' => $this->platform_id,
            'platform_account_id' => $this->platform_account_id ?: null,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id ?: null,
            'platform_sku' => $this->platform_sku,
            'platform_product_name' => $this->platform_product_name,
            'platform_variation_name' => $this->platform_variation_name,
            'match_priority' => $this->match_priority,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
        ]);

        session()->flash('success', 'SKU mapping updated successfully!');

        return $this->redirect(route('platforms.sku-mappings.index'), navigate: true);
    }

    public function delete()
    {
        $this->mapping->delete();

        session()->flash('success', 'SKU mapping deleted successfully!');

        return $this->redirect(route('platforms.sku-mappings.index'), navigate: true);
    }

    public function getPlatformsProperty()
    {
        return Platform::all();
    }

    public function getProductsProperty()
    {
        return Product::all();
    }

    public function with(): array
    {
        return [
            'platforms' => $this->platforms,
            'products' => $this->products,
            'availableAccounts' => $this->availableAccounts,
            'availableVariants' => $this->availableVariants,
        ];
    }
}; ?>

<x-admin.layout title="Edit SKU Mapping">
    <div class="mb-6">
        <div class="flex items-center space-x-4 mb-4">
            <flux:button variant="outline" :href="route('platforms.sku-mappings.index')" wire:navigate>
                <flux:icon name="chevron-left" class="w-4 h-4 mr-2" />
                Back to SKU Mappings
            </flux:button>
        </div>

        <flux:heading size="xl">Edit SKU Mapping</flux:heading>
        <flux:text class="mt-2">Update the platform SKU mapping configuration</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        <!-- Platform Information -->
        <div class="rounded-lg border border-zinc-200 bg-white p-6">
            <flux:heading size="lg" class="mb-4">Platform Information</flux:heading>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Platform</flux:label>
                    <flux:select wire:model.live="platform_id" placeholder="Select platform">
                        @foreach($platforms as $platform)
                            <flux:select.option value="{{ $platform->id }}">{{ $platform->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="platform_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Platform Account (Optional)</flux:label>
                    <flux:select wire:model.live="platform_account_id" placeholder="Select account">
                        <flux:select.option value="">Global mapping</flux:select.option>
                        @foreach($availableAccounts as $account)
                            <flux:select.option value="{{ $account['id'] }}">{{ $account['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="platform_account_id" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-4">
                <flux:field>
                    <flux:label>Platform SKU</flux:label>
                    <flux:input wire:model="platform_sku" placeholder="e.g., TIKTOK-PROD-001" />
                    <flux:error name="platform_sku" />
                </flux:field>

                <flux:field>
                    <flux:label>Match Priority</flux:label>
                    <flux:select wire:model="match_priority">
                        <flux:select.option value="low">Low</flux:select.option>
                        <flux:select.option value="medium">Medium</flux:select.option>
                        <flux:select.option value="high">High</flux:select.option>
                    </flux:select>
                    <flux:error name="match_priority" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-4">
                <flux:field>
                    <flux:label>Platform Product Name (Optional)</flux:label>
                    <flux:input wire:model="platform_product_name" placeholder="Product name on platform" />
                    <flux:error name="platform_product_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Platform Variation Name (Optional)</flux:label>
                    <flux:input wire:model="platform_variation_name" placeholder="Variation name on platform" />
                    <flux:error name="platform_variation_name" />
                </flux:field>
            </div>
        </div>

        <!-- Product Information -->
        <div class="rounded-lg border border-zinc-200 bg-white p-6">
            <flux:heading size="lg" class="mb-4">Product Information</flux:heading>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Product</flux:label>
                    <flux:select wire:model.live="product_id" placeholder="Select product">
                        @foreach($products as $product)
                            <flux:select.option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="product_id" />
                </flux:field>

                @if(count($availableVariants) > 0)
                    <flux:field>
                        <flux:label>Product Variant (Optional)</flux:label>
                        <flux:select wire:model="product_variant_id" placeholder="Select variant">
                            <flux:select.option value="">No specific variant</flux:select.option>
                            @foreach($availableVariants as $variant)
                                <flux:select.option value="{{ $variant['id'] }}">
                                    {{ $variant['name'] }}
                                    @if($variant['sku'])
                                        ({{ $variant['sku'] }})
                                    @endif
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="product_variant_id" />
                    </flux:field>
                @endif
            </div>
        </div>

        <!-- Additional Settings -->
        <div class="rounded-lg border border-zinc-200 bg-white p-6">
            <flux:heading size="lg" class="mb-4">Additional Settings</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:checkbox wire:model="is_active">
                        Active mapping
                    </flux:checkbox>
                    <flux:text size="sm" class="text-zinc-500">
                        Inactive mappings will not be used for automatic matching
                    </flux:text>
                </flux:field>

                <flux:field>
                    <flux:label>Notes (Optional)</flux:label>
                    <flux:textarea
                        wire:model="notes"
                        placeholder="Add any notes about this mapping..."
                        rows="3"
                    />
                    <flux:error name="notes" />
                </flux:field>
            </div>
        </div>

        <!-- Usage Statistics -->
        <div class="rounded-lg border border-zinc-200 bg-white p-6">
            <flux:heading size="lg" class="mb-4">Usage Statistics</flux:heading>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="text-center">
                    <div class="text-2xl font-bold text-zinc-900">{{ number_format($mapping->usage_count) }}</div>
                    <div class="text-sm text-zinc-500">Times Used</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-zinc-900">
                        {{ $mapping->last_used_at ? $mapping->last_used_at->diffForHumans() : 'Never' }}
                    </div>
                    <div class="text-sm text-zinc-500">Last Used</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-zinc-900">
                        {{ $mapping->created_at->diffForHumans() }}
                    </div>
                    <div class="text-sm text-zinc-500">Created</div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-between">
            <flux:button
                variant="danger"
                wire:click="delete"
                wire:confirm="Are you sure you want to delete this SKU mapping? This action cannot be undone."
            >
                <flux:icon name="trash" class="w-4 h-4 mr-2" />
                Delete Mapping
            </flux:button>

            <div class="flex items-center space-x-4">
                <flux:button variant="outline" :href="route('platforms.sku-mappings.index')" wire:navigate>
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Update Mapping
                </flux:button>
            </div>
        </div>
    </form>
</x-admin.layout>