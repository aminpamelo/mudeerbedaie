<x-layouts.store :title="__('store.shop_title') . ' — ' . config('store.name')">

    {{-- Page header --}}
    <section class="relative overflow-hidden border-b border-violet-100/70 bg-gradient-to-b from-violet-50 via-fuchsia-50/50 to-white">
        <span class="pointer-events-none absolute -right-20 -top-20 h-60 w-60 rounded-full bg-fuchsia-300/30 blur-3xl"></span>
        <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <nav class="mb-3 flex items-center gap-1.5 text-xs font-medium text-zinc-400">
                <a href="{{ route('storefront.home') }}" class="hover:text-violet-700">{{ __('store.nav_home') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <span class="text-zinc-600">{{ __('store.shop_title') }}</span>
            </nav>
            <h1 class="font-display text-3xl font-extrabold text-zinc-900 sm:text-4xl">{{ __('store.shop_title') }}</h1>
            <p class="mt-1.5 text-sm text-zinc-500">{{ __('store.shop_subtitle') }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <livewire:store.shop-browser />
    </div>

</x-layouts.store>
