{{-- Accessible quantity stepper for a cart line. Expects $item (ProductCartItem). --}}
<div class="inline-flex items-center rounded-xl border border-zinc-200 bg-white p-0.5 shadow-sm">
    <button type="button"
            wire:click="updateQuantity({{ $item->id }}, {{ max(1, $item->quantity - 1) }})"
            class="grid h-9 w-9 place-items-center rounded-lg text-zinc-600 transition-colors hover:bg-violet-50 hover:text-violet-700 disabled:opacity-40"
            @disabled($item->quantity <= 1)
            aria-label="{{ __('store.qty_decrease') }}">
        <flux:icon name="minus" class="h-4 w-4" />
    </button>

    <input type="number" min="1"
           wire:model.live.debounce.600ms="quantities.{{ $item->id }}"
           wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
           class="h-9 w-12 border-0 bg-transparent p-0 text-center text-sm font-bold text-zinc-900 tabular-nums focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
           aria-label="{{ __('store.quantity') }}" />

    <button type="button"
            wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
            class="grid h-9 w-9 place-items-center rounded-lg text-zinc-600 transition-colors hover:bg-violet-50 hover:text-violet-700"
            aria-label="{{ __('store.qty_increase') }}">
        <flux:icon name="plus" class="h-4 w-4" />
    </button>
</div>
