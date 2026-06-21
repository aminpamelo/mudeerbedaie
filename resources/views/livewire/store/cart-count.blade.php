<?php

use App\Models\ProductCart;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->loadCount();
    }

    #[On('cart-updated')]
    public function loadCount(): void
    {
        $cart = auth()->check()
            ? ProductCart::where('user_id', auth()->id())->first()
            : ProductCart::where('session_id', session()->getId())->first();

        $this->count = $cart ? (int) $cart->items()->sum('quantity') : 0;
    }
}; ?>

<a
    href="{{ route('cart') }}"
    class="relative grid h-9 w-9 place-items-center rounded-lg text-zinc-700 transition-colors hover:bg-emerald-50 hover:text-emerald-700"
    aria-label="{{ __('store.nav_cart') }}"
>
    <flux:icon name="shopping-cart" class="h-5 w-5" />
    @if($count > 0)
        <span class="absolute -right-1 -top-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-emerald-600 px-1 text-[10px] font-bold text-white tabular-nums">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</a>
