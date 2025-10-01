<?php

use App\Models\ProductCart;
use Livewire\Volt\Component;

new class extends Component
{
    public ?ProductCart $cart = null;
    public int $itemCount = 0;
    public float $cartTotal = 0;

    public function mount(): void
    {
        $this->loadCart();
    }

    public function loadCart(): void
    {
        // Get cart for current user/session
        if (auth()->check()) {
            $this->cart = ProductCart::where('user_id', auth()->id())
                ->with('items')
                ->first();
        } else {
            $this->cart = ProductCart::where('session_id', session()->getId())
                ->with('items')
                ->first();
        }

        if ($this->cart) {
            $this->itemCount = $this->cart->items->sum('quantity');
            $this->cartTotal = $this->cart->total_amount;
        } else {
            $this->itemCount = 0;
            $this->cartTotal = 0;
        }
    }

    protected function getListeners(): array
    {
        return [
            'cart-updated' => 'loadCart',
            'product-added-to-cart' => 'loadCart',
        ];
    }
}; ?>

<div class="relative">
    <flux:button variant="ghost" href="{{ route('cart') }}" class="relative p-2">
        <flux:icon name="shopping-cart" class="w-6 h-6" />

        @if($itemCount > 0)
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold">
                {{ $itemCount > 99 ? '99+' : $itemCount }}
            </span>
        @endif
    </flux:button>

    @if($itemCount > 0)
        <!-- Cart Dropdown (Optional - can be implemented later) -->
        <div class="hidden" x-data="{ open: false }" @click.away="open = false">
            <div x-show="open" x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border z-50">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <flux:heading size="sm">Cart ({{ $itemCount }} items)</flux:heading>
                        <flux:button variant="ghost" size="sm" href="{{ route('cart') }}">
                            View Cart
                        </flux:button>
                    </div>

                    @if($cart && $cart->items->isNotEmpty())
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            @foreach($cart->items->take(3) as $item)
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                                        <flux:icon name="photo" class="w-6 h-6 text-gray-400" />
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <flux:text size="sm" class="font-medium truncate">
                                            {{ $item->getDisplayName() }}
                                        </flux:text>
                                        <flux:text size="xs" class="text-gray-500">
                                            Qty: {{ $item->quantity }} × MYR {{ number_format($item->unit_price, 2) }}
                                        </flux:text>
                                    </div>

                                    <flux:text size="sm" class="font-semibold">
                                        MYR {{ number_format($item->total_price, 2) }}
                                    </flux:text>
                                </div>
                            @endforeach

                            @if($cart->items->count() > 3)
                                <flux:text size="xs" class="text-gray-500 text-center">
                                    +{{ $cart->items->count() - 3 }} more items
                                </flux:text>
                            @endif
                        </div>

                        <div class="border-t pt-3 mt-3">
                            <div class="flex justify-between items-center mb-3">
                                <flux:text class="font-semibold">Total:</flux:text>
                                <flux:text class="font-semibold text-lg">MYR {{ number_format($cartTotal, 2) }}</flux:text>
                            </div>

                            <div class="space-y-2">
                                <flux:button variant="primary" size="sm" href="{{ route('cart') }}" class="w-full">
                                    View Cart
                                </flux:button>
                                <flux:button variant="outline" size="sm" href="{{ route('checkout') }}" class="w-full">
                                    Checkout
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>