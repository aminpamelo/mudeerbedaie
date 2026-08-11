<?php

use App\Models\Product;
use App\Models\ProductCart;
use App\Models\Warehouse;
use Livewire\Volt\Component;

new class extends Component
{
    public Product $product;

    public int $quantity = 1;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function increment(): void
    {
        $this->quantity = min($this->quantity + 1, 99);
    }

    public function decrement(): void
    {
        $this->quantity = max($this->quantity - 1, 1);
    }

    public function updatedQuantity(): void
    {
        $this->quantity = max(1, min((int) $this->quantity, 99));
    }

    /**
     * Add the chosen quantity to the cart (session for guests, user-scoped when
     * logged in), then nudge the header count + a toast.
     */
    public function add(): void
    {
        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();

        $this->resolveCart()->addItem(product: $this->product, quantity: $this->quantity, warehouse: $warehouse);

        $this->dispatch('cart-updated');
        $this->dispatch('cart-notify', message: __('store.added_to_cart', ['name' => $this->product->name]));
    }

    public function buyNow(): void
    {
        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();

        $this->resolveCart()->addItem(product: $this->product, quantity: $this->quantity, warehouse: $warehouse);

        $this->dispatch('cart-updated');
        $this->redirectRoute('checkout');
    }

    private function resolveCart(): ProductCart
    {
        $attributes = auth()->check()
            ? ['user_id' => auth()->id()]
            : ['session_id' => session()->getId()];

        return ProductCart::firstOrCreate($attributes, [
            'currency' => 'MYR',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ]);
    }
}; ?>

<div class="space-y-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        {{-- Quantity stepper --}}
        <div class="inline-flex items-center rounded-xl border border-zinc-200 bg-white">
            <button type="button" wire:click="decrement" wire:loading.attr="disabled"
                    class="grid h-11 w-11 place-items-center rounded-l-xl text-zinc-500 transition-colors hover:bg-zinc-50 hover:text-violet-700 disabled:opacity-40"
                    aria-label="{{ __('store.qty_decrease') }}">
                <flux:icon name="minus" class="h-4 w-4" />
            </button>
            <input type="number" min="1" max="99" wire:model.blur="quantity"
                   class="h-11 w-14 border-0 bg-transparent text-center text-sm font-semibold tabular-nums text-zinc-900 focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                   aria-label="{{ __('store.quantity') }}" />
            <button type="button" wire:click="increment" wire:loading.attr="disabled"
                    class="grid h-11 w-11 place-items-center rounded-r-xl text-zinc-500 transition-colors hover:bg-zinc-50 hover:text-violet-700 disabled:opacity-40"
                    aria-label="{{ __('store.qty_increase') }}">
                <flux:icon name="plus" class="h-4 w-4" />
            </button>
        </div>

        {{-- Add to cart --}}
        <button type="button" wire:click="add" wire:loading.attr="disabled" wire:target="add"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 border-violet-600 bg-white px-6 py-3 text-sm font-semibold text-violet-700 transition-colors hover:bg-violet-50 disabled:opacity-70">
            <flux:icon name="shopping-cart" class="h-4 w-4" wire:loading.remove wire:target="add" />
            <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" wire:loading wire:target="add" />
            <span wire:loading.remove wire:target="add">{{ __('store.add_to_cart') }}</span>
            <span wire:loading wire:target="add">{{ __('store.adding') }}</span>
        </button>
    </div>

    {{-- Buy Now — add to cart + go straight to checkout --}}
    <button type="button" wire:click="buyNow" wire:loading.attr="disabled" wire:target="buyNow"
            class="store-grad store-grad-hover flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-500/20 disabled:opacity-70">
        <flux:icon name="bolt" class="h-4 w-4" wire:loading.remove wire:target="buyNow" />
        <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" wire:loading wire:target="buyNow" />
        <span wire:loading.remove wire:target="buyNow">Beli Sekarang</span>
        <span wire:loading wire:target="buyNow">Memproses...</span>
    </button>
</div>
