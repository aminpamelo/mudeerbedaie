<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCart;
use App\Models\Warehouse;
use Livewire\Volt\Component;

new class extends Component
{
    public Product $product;
    public ?ProductVariant $selectedVariant = null;
    public int $quantity = 1;
    public ?int $selectedWarehouseId = null;
    public array $selectedAttributes = [];
    public bool $isProcessing = false;

    public function mount(Product $product): void
    {
        $this->product = $product->load(['variants', 'attributeTemplates', 'stocks']);

        // Set default warehouse
        $defaultWarehouse = Warehouse::where('is_default', true)->first();
        $this->selectedWarehouseId = $defaultWarehouse?->id;

        // If product has variants, initialize attributes
        if ($this->product->type === 'variable' && $this->product->variants->isNotEmpty()) {
            $this->initializeVariantSelection();
        }
    }

    public function initializeVariantSelection(): void
    {
        // Initialize selected attributes based on first variant
        $firstVariant = $this->product->variants->first();
        if ($firstVariant && $firstVariant->attributes) {
            foreach ($firstVariant->attributes as $key => $value) {
                $this->selectedAttributes[$key] = $value;
            }
            $this->updateSelectedVariant();
        }
    }

    public function updatedSelectedAttributes(): void
    {
        $this->updateSelectedVariant();
    }

    public function updateSelectedVariant(): void
    {
        if (empty($this->selectedAttributes)) {
            $this->selectedVariant = null;
            return;
        }

        // Find variant that matches selected attributes
        $this->selectedVariant = $this->product->variants->first(function ($variant) {
            if (!$variant->attributes) return false;

            foreach ($this->selectedAttributes as $key => $value) {
                if (!isset($variant->attributes[$key]) || $variant->attributes[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    public function addToCart(): void
    {
        $this->isProcessing = true;

        try {
            // Validate quantity
            if ($this->quantity <= 0) {
                $this->dispatch('cart-error', message: 'Please enter a valid quantity');
                return;
            }

            // Get or create cart
            $cart = $this->getOrCreateCart();

            // Check stock availability
            if ($this->product->type === 'variable') {
                if (!$this->selectedVariant) {
                    $this->dispatch('cart-error', message: 'Please select product options');
                    return;
                }

                if (!$this->selectedVariant->checkStockAvailability($this->quantity, $this->selectedWarehouseId)) {
                    $this->dispatch('cart-error', message: 'Insufficient stock for selected variant');
                    return;
                }
            } else {
                if (!$this->product->checkStockAvailability($this->quantity, $this->selectedWarehouseId)) {
                    $this->dispatch('cart-error', message: 'Insufficient stock');
                    return;
                }
            }

            // Get warehouse
            $warehouse = Warehouse::find($this->selectedWarehouseId);

            // Add item to cart
            $cart->addItem(
                product: $this->product,
                variant: $this->selectedVariant,
                quantity: $this->quantity,
                warehouse: $warehouse
            );

            // Reset quantity and dispatch success event
            $this->quantity = 1;
            $this->dispatch('cart-updated');
            $this->dispatch('product-added-to-cart', message: 'Product added to cart successfully!');

        } catch (\Exception $e) {
            $this->dispatch('cart-error', message: 'Failed to add product to cart: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function getOrCreateCart(): ProductCart
    {
        if (auth()->check()) {
            return ProductCart::firstOrCreate(
                ['user_id' => auth()->id()],
                [
                    'currency' => 'MYR',
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                ]
            );
        } else {
            return ProductCart::firstOrCreate(
                ['session_id' => session()->getId()],
                [
                    'currency' => 'MYR',
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                ]
            );
        }
    }

    public function getCurrentPrice(): float
    {
        if ($this->product->type === 'variable' && $this->selectedVariant) {
            return $this->selectedVariant->price;
        }

        return $this->product->base_price;
    }

    public function getAvailableStock(): int
    {
        if ($this->product->type === 'variable' && $this->selectedVariant) {
            return $this->selectedVariant->getStockQuantity($this->selectedWarehouseId);
        }

        return $this->product->getStockQuantity($this->selectedWarehouseId);
    }

    public function canAddToCart(): bool
    {
        if ($this->product->type === 'variable' && !$this->selectedVariant) {
            return false;
        }

        return $this->getAvailableStock() > 0;
    }

    public function getAttributeOptions(string $attributeKey): array
    {
        return $this->product->variants
            ->pluck('attributes')
            ->filter()
            ->pluck($attributeKey)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}; ?>

<div class="space-y-4">
    @if($product->type === 'variable' && $product->variants->isNotEmpty())
        <!-- Variable Product Options -->
        <div class="space-y-4">
            @foreach($product->attributeTemplates as $template)
                @php
                    $options = $this->getAttributeOptions($template->name);
                @endphp

                @if(!empty($options))
                    <div>
                        <flux:text class="font-medium mb-2">{{ $template->name }}</flux:text>

                        @if($template->type === 'select')
                            <flux:select wire:model.live="selectedAttributes.{{ $template->name }}" placeholder="Select {{ strtolower($template->name) }}">
                                @foreach($options as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </flux:select>
                        @elseif($template->type === 'color')
                            <div class="flex space-x-2">
                                @foreach($options as $color)
                                    <button
                                        type="button"
                                        wire:click="$set('selectedAttributes.{{ $template->name }}', '{{ $color }}')"
                                        class="w-8 h-8 rounded-full border-2 {{ isset($selectedAttributes[$template->name]) && $selectedAttributes[$template->name] === $color ? 'border-gray-800' : 'border-gray-300' }}"
                                        style="background-color: {{ $color }}"
                                        title="{{ $color }}"
                                    ></button>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($options as $option)
                                    <flux:button
                                        variant="{{ isset($selectedAttributes[$template->name]) && $selectedAttributes[$template->name] === $option ? 'primary' : 'outline' }}"
                                        size="sm"
                                        wire:click="$set('selectedAttributes.{{ $template->name }}', '{{ $option }}')"
                                    >
                                        {{ $option }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        @if($selectedVariant)
            <!-- Selected Variant Info -->
            <div class="p-3 bg-gray-50 rounded-lg">
                <flux:text size="sm" class="text-gray-600">Selected: {{ $selectedVariant->name }}</flux:text>
                <flux:text size="sm" class="text-gray-600">SKU: {{ $selectedVariant->sku }}</flux:text>
                <flux:text class="font-semibold text-green-600">MYR {{ number_format($this->getCurrentPrice(), 2) }}</flux:text>
            </div>
        @endif
    @else
        <!-- Simple Product Info -->
        <div class="p-3 bg-gray-50 rounded-lg">
            <flux:text class="font-semibold text-green-600">MYR {{ number_format($this->getCurrentPrice(), 2) }}</flux:text>
            <flux:text size="sm" class="text-gray-600">SKU: {{ $product->sku }}</flux:text>
        </div>
    @endif

    <!-- Warehouse Selection (if multiple warehouses) -->
    @if(Warehouse::count() > 1)
        <div>
            <flux:text class="font-medium mb-2">Warehouse</flux:text>
            <flux:select wire:model.live="selectedWarehouseId">
                @foreach(Warehouse::all() as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </flux:select>
        </div>
    @endif

    <!-- Stock Information -->
    <div class="text-sm">
        @if($this->getAvailableStock() > 0)
            <flux:text class="text-green-600">{{ $this->getAvailableStock() }} in stock</flux:text>
        @else
            <flux:text class="text-red-600">Out of stock</flux:text>
        @endif
    </div>

    <!-- Quantity and Add to Cart -->
    @if($this->canAddToCart())
        <div class="flex items-center space-x-4">
            <!-- Quantity Selector -->
            <div class="flex items-center space-x-2">
                <flux:text class="font-medium">Qty:</flux:text>
                <div class="flex items-center space-x-1">
                    <flux:button
                        variant="outline"
                        size="sm"
                        wire:click="$set('quantity', {{ max(1, $this->quantity - 1) }})"
                        class="w-8 h-8 p-0"
                    >
                        <flux:icon name="minus" class="w-4 h-4" />
                    </flux:button>

                    <flux:input
                        type="number"
                        wire:model.live="quantity"
                        min="1"
                        max="{{ $this->getAvailableStock() }}"
                        class="w-16 text-center"
                    />

                    <flux:button
                        variant="outline"
                        size="sm"
                        wire:click="$set('quantity', {{ min($this->getAvailableStock(), $this->quantity + 1) }})"
                        class="w-8 h-8 p-0"
                    >
                        <flux:icon name="plus" class="w-4 h-4" />
                    </flux:button>
                </div>
            </div>

            <!-- Add to Cart Button -->
            <flux:button
                variant="primary"
                wire:click="addToCart"
                wire:loading.attr="disabled"
                :disabled="$isProcessing"
                class="flex-1"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="shopping-cart" class="w-4 h-4 mr-2" />
                    <span wire:loading.remove wire:target="addToCart">Add to Cart</span>
                    <span wire:loading wire:target="addToCart">Adding...</span>
                </div>
            </flux:button>
        </div>
    @else
        <flux:button variant="ghost" disabled class="w-full">
            Out of Stock
        </flux:button>
    @endif
</div>

<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('cart-error', (event) => {
            alert(event.message);
        });

        Livewire.on('product-added-to-cart', (event) => {
            // You can replace this with toast notifications
            alert(event.message);
        });
    });
</script>