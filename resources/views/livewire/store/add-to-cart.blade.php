<?php

use App\Models\Product;
use App\Models\ProductCart;
use App\Models\Warehouse;
use Livewire\Volt\Component;

new class extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    /**
     * Quick-add a single unit of the product to the cart (session for guests,
     * user-scoped when logged in), then nudge the header count + a toast.
     */
    public function add(): void
    {
        $cart = $this->resolveCart();
        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();

        $cart->addItem(product: $this->product, quantity: 1, warehouse: $warehouse);

        $this->dispatch('cart-updated');
        $this->dispatch('cart-notify', message: __('store.added_to_cart', ['name' => $this->product->name]));
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

<button
    type="button"
    wire:click="add"
    wire:loading.attr="disabled"
    wire:target="add"
    aria-label="{{ __('store.add_to_cart') }}: {{ $product->name }}"
    class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:opacity-70"
>
    <flux:icon name="shopping-cart" class="h-4 w-4" wire:loading.remove wire:target="add" />
    <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" wire:loading wire:target="add" />
    <span wire:loading.remove wire:target="add">{{ __('store.add_to_cart') }}</span>
    <span wire:loading wire:target="add">{{ __('store.adding') }}</span>
</button>
