<?php

use App\Models\Package;
use App\Models\ProductCart;
use Livewire\Volt\Component;

new class extends Component
{
    public Package $package;

    public int $quantity = 1;

    public bool $compact = false;

    public function mount(Package $package, bool $compact = false): void
    {
        $this->package = $package;
        $this->compact = $compact;
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

    public function add(): void
    {
        $this->resolveCart()->addPackage($this->package, $this->compact ? 1 : $this->quantity);

        $this->dispatch('cart-updated');
        $this->dispatch('cart-notify', message: __('store.added_to_cart', ['name' => $this->package->name]));
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

<div>
    @if($compact)
        {{-- Card quick-add: single button, no stepper --}}
        <button type="button" wire:click="add" wire:loading.attr="disabled" wire:target="add"
                class="store-grad store-grad-hover flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70">
            <flux:icon name="shopping-cart" class="h-4 w-4" wire:loading.remove wire:target="add" />
            <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" wire:loading wire:target="add" />
            <span wire:loading.remove wire:target="add">{{ __('store.add_to_cart') }}</span>
            <span wire:loading wire:target="add">{{ __('store.adding') }}</span>
        </button>
    @else
        {{-- Detail page: quantity stepper + add --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
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

            <button type="button" wire:click="add" wire:loading.attr="disabled" wire:target="add"
                    class="store-grad store-grad-hover flex flex-1 items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold text-white disabled:opacity-70">
                <flux:icon name="shopping-cart" class="h-4 w-4" wire:loading.remove wire:target="add" />
                <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" wire:loading wire:target="add" />
                <span wire:loading.remove wire:target="add">{{ __('store.add_to_cart') }}</span>
                <span wire:loading wire:target="add">{{ __('store.adding') }}</span>
            </button>
        </div>
    @endif
</div>
