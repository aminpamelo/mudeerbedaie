<?php

use App\Models\ProductCart;
use App\Models\ProductCartItem;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.store')] class extends Component
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
            // Stock checks apply to products only; packages are a flat bundle line.
            if ($item->isProduct()) {
                if (! $item->variant && $item->product && ! $item->product->checkStockAvailability($quantity, $item->warehouse_id)) {
                    $this->dispatch('cart-error', message: __('store.cart_insufficient_stock', ['name' => $item->getDisplayName()]));

                    return;
                }

                if ($item->variant && ! $item->variant->checkStockAvailability($quantity, $item->warehouse_id)) {
                    $this->dispatch('cart-error', message: __('store.cart_insufficient_stock', ['name' => $item->getDisplayName()]));

                    return;
                }
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

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8"
     x-data="{ err: '', errT: null }"
     x-on:cart-error.window="err = $event.detail.message; clearTimeout(errT); errT = setTimeout(() => err = '', 4000)">

    {{-- Heading --}}
    <div class="mb-8 flex items-center gap-3">
        <span class="store-grad grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-white shadow-lg shadow-fuchsia-500/25">
            <flux:icon name="shopping-cart" class="h-6 w-6" />
        </span>
        <div>
            <h1 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.cart_title') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('store.cart_subtitle') }}</p>
        </div>
    </div>

    {{-- Inline error toast (stock issues) --}}
    <div x-show="err" x-cloak x-transition.opacity
         class="mb-4 flex items-center gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
        <flux:icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-rose-500" />
        <span x-text="err"></span>
    </div>

    @if($cart && !$cart->isEmpty())
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
            {{-- ===================== CART ITEMS ===================== --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
                    <div class="divide-y divide-zinc-100">
                        @foreach($cart->items as $item)
                            @php $img = $item->getImageUrl(); @endphp
                            <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:gap-5 sm:p-5" wire:key="cart-item-{{ $item->id }}">
                                {{-- Thumbnail --}}
                                <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-violet-50 to-fuchsia-50 ring-1 ring-zinc-900/5">
                                    @if($img)
                                        <img src="{{ $img }}" alt="{{ $item->getDisplayName() }}" loading="lazy" class="h-full w-full object-cover"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
                                        <span class="hidden h-full w-full items-center justify-center" style="display:none;">
                                            <flux:icon name="photo" class="h-8 w-8 text-violet-300" />
                                        </span>
                                    @else
                                        <span class="flex h-full w-full items-center justify-center">
                                            <flux:icon name="{{ $item->isCourse() ? 'academic-cap' : ($item->isPackage() ? 'gift' : 'photo') }}" class="h-8 w-8 text-violet-300" />
                                        </span>
                                    @endif
                                </div>

                                {{-- Details --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start gap-2">
                                        <h3 class="font-display text-base font-bold leading-snug text-zinc-900">{{ $item->getDisplayName() }}</h3>
                                        @if($item->isPackage())
                                            <span class="mt-0.5 shrink-0 rounded-full bg-fuchsia-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-fuchsia-700">{{ __('store.nav_packages') }}</span>
                                        @elseif($item->isCourse())
                                            <span class="mt-0.5 shrink-0 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700">{{ __('store.lms_badge') }}</span>
                                        @endif
                                    </div>
                                    @if($item->variant)
                                        <p class="mt-0.5 text-xs text-zinc-500">{{ $item->variant->name }}</p>
                                    @endif
                                    <p class="mt-1.5 text-sm font-semibold text-violet-700">
                                        MYR {{ number_format($item->unit_price, 2) }}
                                        <span class="font-normal text-zinc-400">/ {{ __('store.cart_unit_each') }}</span>
                                    </p>

                                    {{-- Mobile: qty + total row --}}
                                    <div class="mt-3 flex items-center justify-between sm:hidden">
                                        @include('livewire.cart.partials.qty-stepper', ['item' => $item])
                                        <span class="font-display text-base font-extrabold text-zinc-900">MYR {{ $this->getItemTotal($item) }}</span>
                                    </div>
                                </div>

                                {{-- Desktop: quantity stepper --}}
                                <div class="hidden sm:block">
                                    @include('livewire.cart.partials.qty-stepper', ['item' => $item])
                                </div>

                                {{-- Desktop: line total --}}
                                <div class="hidden min-w-[96px] text-right sm:block">
                                    <div class="font-display text-lg font-extrabold text-zinc-900">MYR {{ $this->getItemTotal($item) }}</div>
                                </div>

                                {{-- Remove --}}
                                <button type="button" wire:click="removeItem({{ $item->id }})"
                                        class="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-lg text-zinc-300 transition-colors hover:bg-rose-50 hover:text-rose-600 sm:static sm:h-9 sm:w-9"
                                        aria-label="{{ __('store.cart_remove') }}">
                                    <flux:icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-between gap-3 border-t border-zinc-100 bg-zinc-50/60 px-4 py-4 sm:px-5">
                        <button type="button" wire:click="clearCart"
                                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-zinc-500 transition-colors hover:bg-white hover:text-rose-600">
                            <flux:icon name="trash" class="h-4 w-4" />
                            {{ __('store.cart_clear') }}
                        </button>

                        <a href="{{ route('shop') }}" wire:navigate
                           class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-violet-700 transition-colors hover:bg-violet-50">
                            <flux:icon name="arrow-left" class="h-4 w-4" />
                            {{ __('store.cart_continue') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- ===================== ORDER SUMMARY ===================== --}}
            <div class="lg:col-span-1">
                <div class="sticky top-24 overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
                    <div class="store-grad px-5 py-4">
                        <h2 class="font-display text-lg font-extrabold text-white">{{ __('store.cart_summary') }}</h2>
                    </div>

                    <div class="p-5">
                        <div class="space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500">{{ __('store.cart_subtotal') }} · {{ trans_choice('store.cart_item_count', $cart->items->count(), ['count' => $cart->items->count()]) }}</span>
                                <span class="font-semibold tabular-nums text-zinc-800">MYR {{ $this->getCartSubtotal() }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500">{{ __('store.cart_tax') }}</span>
                                <span class="font-semibold tabular-nums text-zinc-800">MYR {{ $this->getCartTax() }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex items-end justify-between border-t border-dashed border-zinc-200 pt-4">
                            <span class="font-display text-base font-bold text-zinc-900">{{ __('store.cart_total') }}</span>
                            <span class="store-grad-text font-display text-2xl font-extrabold tabular-nums">MYR {{ $this->getCartTotal() }}</span>
                        </div>

                        <a href="{{ route('checkout') }}"
                           class="store-grad store-grad-hover mt-5 flex w-full items-center justify-center gap-2 rounded-2xl px-5 py-3.5 text-base font-bold text-white">
                            {{ __('store.cart_checkout') }}
                            <flux:icon name="arrow-right" class="h-5 w-5" />
                        </a>

                        <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-zinc-400">
                            <flux:icon name="lock-closed" class="h-3.5 w-3.5" />
                            {{ __('store.cart_secure') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- ===================== EMPTY CART ===================== --}}
        <div class="rounded-3xl border border-zinc-200 bg-white px-6 py-16 text-center shadow-sm sm:py-20">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-violet-100 to-fuchsia-100">
                <flux:icon name="shopping-cart" class="h-11 w-11 text-violet-500" />
            </div>
            <h2 class="font-display mt-6 text-xl font-extrabold text-zinc-900">{{ __('store.cart_empty_title') }}</h2>
            <p class="mx-auto mt-2 max-w-sm text-sm text-zinc-500">{{ __('store.cart_empty_text') }}</p>
            <a href="{{ route('shop') }}" wire:navigate
               class="store-grad store-grad-hover mt-6 inline-flex items-center gap-2 rounded-2xl px-6 py-3 text-sm font-bold text-white">
                <flux:icon name="squares-2x2" class="h-5 w-5" />
                {{ __('store.cart_browse') }}
            </a>
        </div>
    @endif
</div>