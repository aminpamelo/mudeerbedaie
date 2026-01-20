<?php

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Volt\Component;

new class extends Component {
    public string $platform_id = '';
    public string $platform_account_id = '';
    public string $product_id = '';
    public string $product_variant_id = '';
    public string $platform_sku = '';
    public string $platform_product_name = '';
    public string $platform_variation_name = '';
    public string $match_priority = 'medium';
    public bool $is_active = true;
    public string $notes = '';

    public array $availableAccounts = [];
    public array $availableVariants = [];

    public function mount()
    {
        //
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

        // Check for duplicate mappings
        $existingMapping = PlatformSkuMapping::where('platform_id', $this->platform_id)
            ->when($this->platform_account_id, fn($q) => $q->where('platform_account_id', $this->platform_account_id))
            ->where('platform_sku', $this->platform_sku)
            ->first();

        if ($existingMapping) {
            $this->addError('platform_sku', 'A mapping for this platform SKU already exists.');
            return;
        }

        PlatformSkuMapping::create([
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

        session()->flash('success', 'SKU mapping created successfully!');

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

<div>
    <div class="mb-6">
        <div class="flex items-center space-x-4 mb-4">
            <flux:button variant="outline" :href="route('platforms.sku-mappings.index')" wire:navigate>
                <flux:icon name="chevron-left" class="w-4 h-4 mr-2" />
                Back to SKU Mappings
            </flux:button>
        </div>

        <flux:heading size="xl">Create SKU Mapping</flux:heading>
        <flux:text class="mt-2">Link a platform SKU to a product or variant in your inventory</flux:text>
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

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4">
            <flux:button variant="outline" :href="route('platforms.sku-mappings.index')" wire:navigate>
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                Create Mapping
            </flux:button>
        </div>
    </form>

    <!-- Help Section -->
    <div class="mt-8 rounded-lg border border-blue-200 bg-blue-50 p-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <flux:icon name="information-circle" class="h-5 w-5 text-blue-400" />
            </div>
            <div class="ml-3">
                <flux:heading size="sm" class="text-blue-800">SKU Mapping Tips</flux:heading>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Platform SKU:</strong> The unique identifier used on the external platform</li>
                        <li><strong>Match Priority:</strong> Higher priority mappings are preferred during automatic matching</li>
                        <li><strong>Product vs Variant:</strong> Map to a variant if you need specific size/color matching</li>
                        <li><strong>Global vs Account:</strong> Global mappings apply to all accounts, account-specific mappings override global ones</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>