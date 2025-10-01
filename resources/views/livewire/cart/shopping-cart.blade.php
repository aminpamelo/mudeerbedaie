<?php

use App\Models\ProductCart;
use App\Models\ProductCartItem;
use Livewire\Volt\Component;

new class extends Component
{
    public ?ProductCart $cart = null;
    public array $quantities = [];

    public function mount(): void
    {
        $this->loadCart();
    }

    public function loadCart(): void
    {
        // Get or create cart for current user/session
        if (auth()->check()) {
            $this->cart = ProductCart::where('user_id', auth()->id())
                ->with(['items.product', 'items.variant', 'items.warehouse'])
                ->first();
        } else {
            $this->cart = ProductCart::where('session_id', session()->getId())
                ->with(['items.product', 'items.variant', 'items.warehouse'])
                ->first();
        }

        if ($this->cart) {
            // Initialize quantities array
            $this->quantities = $this->cart->items->pluck('quantity', 'id')->toArray();
        }
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($itemId);
            return;
        }

        $item = ProductCartItem::find($itemId);
        if ($item && $item->cart_id === $this->cart->id) {
            // Check stock availability
            if (!$item->variant && !$item->product->checkStockAvailability($quantity, $item->warehouse_id)) {
                $this->dispatch('cart-error', message: 'Insufficient stock for ' . $item->getDisplayName());
                return;
            }

            if ($item->variant && !$item->variant->checkStockAvailability($quantity, $item->warehouse_id)) {
                $this->dispatch('cart-error', message: 'Insufficient stock for ' . $item->getDisplayName());
                return;
            }

            $item->updateQuantity($quantity);
            $this->quantities[$itemId] = $quantity;
            $this->loadCart(); // Refresh cart totals
            $this->dispatch('cart-updated');
        }
    }

    public function removeItem(int $itemId): void
    {
        $item = ProductCartItem::find($itemId);
        if ($item && $item->cart_id === $this->cart->id) {
            $this->cart->removeItem($item);
            unset($this->quantities[$itemId]);
            $this->loadCart();
            $this->dispatch('cart-updated');
        }
    }

    public function clearCart(): void
    {
        if ($this->cart) {
            $this->cart->clear();
            $this->quantities = [];
            $this->loadCart();
            $this->dispatch('cart-updated');
        }
    }

    public function getItemTotal(ProductCartItem $item): string
    {
        return number_format($item->total_price, 2);
    }

    public function getCartSubtotal(): string
    {
        return $this->cart ? number_format($this->cart->subtotal, 2) : '0.00';
    }

    public function getCartTax(): string
    {
        return $this->cart ? number_format($this->cart->tax_amount, 2) : '0.00';
    }

    public function getCartTotal(): string
    {
        return $this->cart ? number_format($this->cart->total_amount, 2) : '0.00';
    }
}; ?>

<div class="max-w-4xl mx-auto py-8">
    <div class="mb-6">
        <flux:heading size="xl">Shopping Cart</flux:heading>
        <flux:text class="mt-2">Review your items and proceed to checkout</flux:text>
    </div>

    @if($cart && !$cart->isEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-6">
                        <div class="space-y-6">
                            @foreach($cart->items as $item)
                                <div class="flex items-center space-x-4 pb-6 border-b last:border-b-0 last:pb-0" wire:key="cart-item-{{ $item->id }}">
                                    <!-- Product Image Placeholder -->
                                    <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <flux:icon name="photo" class="w-8 h-8 text-gray-400" />
                                    </div>

                                    <!-- Product Details -->
                                    <div class="flex-1">
                                        <flux:heading size="sm">{{ $item->getDisplayName() }}</flux:heading>
                                        <flux:text size="sm" class="text-gray-600">SKU: {{ $item->getSku() }}</flux:text>
                                        @if($item->variant)
                                            <flux:text size="sm" class="text-gray-600">Variant: {{ $item->variant->name }}</flux:text>
                                        @endif
                                        @if($item->warehouse)
                                            <flux:text size="sm" class="text-gray-600">Warehouse: {{ $item->warehouse->name }}</flux:text>
                                        @endif
                                        <flux:text size="sm" class="font-semibold text-primary-600">MYR {{ number_format($item->unit_price, 2) }}</flux:text>
                                    </div>

                                    <!-- Quantity Controls -->
                                    <div class="flex items-center space-x-2">
                                        <flux:button
                                            variant="outline"
                                            size="sm"
                                            wire:click="updateQuantity({{ $item->id }}, {{ max(1, $item->quantity - 1) }})"
                                            class="w-8 h-8 p-0"
                                        >
                                            <flux:icon name="minus" class="w-4 h-4" />
                                        </flux:button>

                                        <flux:input
                                            type="number"
                                            wire:model.live.debounce.500ms="quantities.{{ $item->id }}"
                                            wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                            min="1"
                                            class="w-16 text-center"
                                        />

                                        <flux:button
                                            variant="outline"
                                            size="sm"
                                            wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                            class="w-8 h-8 p-0"
                                        >
                                            <flux:icon name="plus" class="w-4 h-4" />
                                        </flux:button>
                                    </div>

                                    <!-- Item Total -->
                                    <div class="text-right min-w-[80px]">
                                        <flux:text class="font-semibold">MYR {{ $this->getItemTotal($item) }}</flux:text>
                                    </div>

                                    <!-- Remove Button -->
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="removeItem({{ $item->id }})"
                                        class="text-red-600 hover:text-red-700"
                                    >
                                        <flux:icon name="trash" class="w-4 h-4" />
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>

                        <!-- Cart Actions -->
                        <div class="mt-6 pt-6 border-t flex justify-between">
                            <flux:button variant="outline" wire:click="clearCart">
                                <flux:icon name="trash" class="w-4 h-4 mr-2" />
                                Clear Cart
                            </flux:button>

                            <flux:button variant="outline" href="{{ route('products.index') }}">
                                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                                Continue Shopping
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
                    <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <flux:text>Subtotal ({{ $cart->items->count() }} items)</flux:text>
                            <flux:text>MYR {{ $this->getCartSubtotal() }}</flux:text>
                        </div>

                        <div class="flex justify-between">
                            <flux:text>Tax (GST 6%)</flux:text>
                            <flux:text>MYR {{ $this->getCartTax() }}</flux:text>
                        </div>

                        <div class="border-t pt-3">
                            <div class="flex justify-between">
                                <flux:text class="font-semibold text-lg">Total</flux:text>
                                <flux:text class="font-semibold text-lg">MYR {{ $this->getCartTotal() }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <flux:button variant="primary" class="w-full" href="{{ route('checkout') }}">
                            Proceed to Checkout
                        </flux:button>
                    </div>

                    <!-- Security Badge -->
                    <div class="mt-4 text-center">
                        <flux:text size="sm" class="text-gray-500 flex items-center justify-center">
                            <flux:icon name="shield-check" class="w-4 h-4 mr-1" />
                            Secure Checkout
                        </flux:text>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Empty Cart -->
        <div class="text-center py-12">
            <div class="mx-auto w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                <flux:icon name="shopping-cart" class="w-16 h-16 text-gray-400" />
            </div>

            <flux:heading size="lg" class="mb-4">Your cart is empty</flux:heading>
            <flux:text class="text-gray-600 mb-6">Start shopping to add items to your cart</flux:text>

            <flux:button variant="primary" href="{{ route('products.index') }}">
                Browse Products
            </flux:button>
        </div>
    @endif
</div>

<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('cart-error', (event) => {
            alert(event.message);
        });

        Livewire.on('cart-updated', () => {
            // You can add toast notifications here
            console.log('Cart updated');
        });
    });
</script>