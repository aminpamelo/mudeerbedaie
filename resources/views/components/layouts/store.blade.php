@php
    $storeName = config('store.name');
    $locale = app()->getLocale();
    $company = config('app.company');
    $storeLogo = app(\App\Services\SettingsService::class)->getLogo();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
    <head>
        @include('partials.head')
        {{-- Storefront design tokens. Inlined here (not via @push) because the head's
             @stack('styles') already renders before this layout could push to it. --}}
        <style>
            [x-cloak] { display: none !important; }
            .font-display { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; letter-spacing: -0.02em; }
            .store-hero-grid { background-image: radial-gradient(circle at 1px 1px, rgba(124,58,237,0.14) 1px, transparent 0); background-size: 22px 22px; }
            .store-grad { background-image: linear-gradient(115deg, #7c3aed 0%, #c026d3 52%, #f43f5e 100%); }
            .store-grad-text {
                background-image: linear-gradient(115deg, #7c3aed, #c026d3, #f43f5e);
                -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; color: transparent;
            }
            .store-grad-hover { transition: box-shadow .25s ease, transform .25s ease, filter .25s ease; }
            .store-grad-hover:hover { filter: brightness(1.06); transform: translateY(-1px); box-shadow: 0 14px 26px -10px rgba(192,38,211,.5); }
            @media (prefers-reduced-motion: reduce) {
                .store-grad-hover { transition: none; }
                .store-grad-hover:hover { transform: none; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-white pb-16 text-zinc-800 antialiased md:pb-0">
        {{-- ===================== HEADER ===================== --}}
        <header class="sticky top-0 z-40 border-b border-zinc-200/60 bg-white/80 backdrop-blur-xl" x-data="{ open: false }">
            <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('storefront.home') }}" class="flex items-center gap-2.5">
                    @if($storeLogo)
                        {{-- CSS background so a missing file degrades to a blank tile, never a broken-image icon. --}}
                        <span class="grid h-10 w-10 shrink-0 rounded-xl bg-white bg-contain bg-center bg-no-repeat shadow-sm ring-1 ring-zinc-900/5" style="background-image:url('{{ $storeLogo }}')" role="img" aria-label="{{ $storeName }}"></span>
                    @else
                        <span class="store-grad grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white shadow-lg shadow-fuchsia-500/25">
                            <flux:icon name="shopping-bag" class="h-5 w-5" />
                        </span>
                    @endif
                    <span class="font-display text-lg font-extrabold text-zinc-900">{{ $storeName }}</span>
                </a>

                <nav class="ml-4 hidden items-center gap-1 md:flex">
                    <a href="{{ route('storefront.home') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-600 transition-colors hover:bg-violet-50 hover:text-violet-700">{{ __('store.nav_home') }}</a>
                    <a href="{{ route('shop') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-600 transition-colors hover:bg-violet-50 hover:text-violet-700">{{ __('store.nav_shop') }}</a>
                    <a href="{{ route('storefront.home') }}#packages" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-600 transition-colors hover:bg-violet-50 hover:text-violet-700">{{ __('store.nav_packages') }}</a>
                </nav>

                <div class="ml-auto flex items-center gap-1.5">
                    {{-- Language switch --}}
                    <div class="hidden items-center rounded-lg bg-zinc-100 p-0.5 text-xs font-bold sm:flex">
                        <a href="{{ route('locale.switch', 'ms') }}" class="rounded-md px-2 py-1 transition-colors {{ $locale === 'ms' ? 'bg-white text-violet-700 shadow-sm' : 'text-zinc-500 hover:text-zinc-800' }}">BM</a>
                        <a href="{{ route('locale.switch', 'en') }}" class="rounded-md px-2 py-1 transition-colors {{ $locale === 'en' ? 'bg-white text-violet-700 shadow-sm' : 'text-zinc-500 hover:text-zinc-800' }}">EN</a>
                    </div>

                    {{-- Cart --}}
                    <livewire:store.cart-count />

                    {{-- Account / Login --}}
                    @auth
                        <a href="{{ route('dashboard') }}" class="store-grad store-grad-hover hidden rounded-xl px-4 py-2 text-sm font-semibold text-white sm:inline-block">{{ __('store.nav_account') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="store-grad store-grad-hover hidden rounded-xl px-4 py-2 text-sm font-semibold text-white sm:inline-block">{{ __('store.nav_login') }}</a>
                    @endauth

                    {{-- Mobile menu toggle --}}
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-lg text-zinc-600 hover:bg-zinc-100 md:hidden" @click="open = !open" :aria-expanded="open" aria-label="{{ __('store.menu') }}">
                        <flux:icon name="bars-3" class="h-6 w-6" x-show="!open" />
                        <flux:icon name="x-mark" class="h-6 w-6" x-show="open" x-cloak />
                    </button>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div x-show="open" x-cloak x-collapse class="border-t border-zinc-100 md:hidden">
                <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3">
                    <a href="{{ route('storefront.home') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-violet-50 hover:text-violet-700">{{ __('store.nav_home') }}</a>
                    <a href="{{ route('shop') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-violet-50 hover:text-violet-700">{{ __('store.nav_shop') }}</a>
                    <a href="{{ route('storefront.home') }}#packages" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-violet-50 hover:text-violet-700">{{ __('store.nav_packages') }}</a>
                    <div class="mt-1 flex items-center justify-between border-t border-zinc-100 pt-3">
                        <div class="flex items-center rounded-lg bg-zinc-100 p-0.5 text-xs font-bold">
                            <a href="{{ route('locale.switch', 'ms') }}" class="rounded-md px-2.5 py-1 {{ $locale === 'ms' ? 'bg-white text-violet-700 shadow-sm' : 'text-zinc-500' }}">BM</a>
                            <a href="{{ route('locale.switch', 'en') }}" class="rounded-md px-2.5 py-1 {{ $locale === 'en' ? 'bg-white text-violet-700 shadow-sm' : 'text-zinc-500' }}">EN</a>
                        </div>
                        @auth
                            <a href="{{ route('dashboard') }}" class="store-grad rounded-xl px-4 py-2 text-sm font-semibold text-white">{{ __('store.nav_account') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="store-grad rounded-xl px-4 py-2 text-sm font-semibold text-white">{{ __('store.nav_login') }}</a>
                        @endauth
                    </div>
                </nav>
            </div>
        </header>

        {{-- ===================== CONTENT ===================== --}}
        <main>
            {{ $slot }}
        </main>

        {{-- ===================== FOOTER ===================== --}}
        <footer class="mt-20 border-t border-zinc-100 bg-zinc-50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-10 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2.5">
                            @if($storeLogo)
                                <span class="grid h-10 w-10 shrink-0 rounded-xl bg-white bg-contain bg-center bg-no-repeat shadow-sm ring-1 ring-zinc-900/5" style="background-image:url('{{ $storeLogo }}')" role="img" aria-label="{{ $storeName }}"></span>
                            @else
                                <span class="store-grad grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white">
                                    <flux:icon name="shopping-bag" class="h-5 w-5" />
                                </span>
                            @endif
                            <span class="font-display text-lg font-extrabold text-zinc-900">{{ $storeName }}</span>
                        </div>
                        <p class="mt-4 max-w-md text-sm leading-relaxed text-zinc-500">{{ __('store.footer_about') }}</p>
                    </div>

                    <div>
                        <h3 class="font-display text-sm font-bold text-zinc-900">{{ __('store.footer_explore') }}</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-zinc-500">
                            <li><a href="{{ route('storefront.home') }}" class="transition-colors hover:text-violet-700">{{ __('store.nav_home') }}</a></li>
                            <li><a href="{{ route('shop') }}" class="transition-colors hover:text-violet-700">{{ __('store.nav_shop') }}</a></li>
                            <li><a href="{{ route('storefront.home') }}#packages" class="transition-colors hover:text-violet-700">{{ __('store.nav_packages') }}</a></li>
                            <li><a href="{{ route('cart') }}" class="transition-colors hover:text-violet-700">{{ __('store.nav_cart') }}</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-display text-sm font-bold text-zinc-900">{{ __('store.footer_contact') }}</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-zinc-500">
                            @if(!empty($company['phone']))
                                <li class="flex items-center gap-2"><flux:icon name="phone" class="h-4 w-4 text-violet-600" /> {{ $company['phone'] }}</li>
                            @endif
                            @if(!empty($company['email']))
                                <li class="flex items-center gap-2"><flux:icon name="envelope" class="h-4 w-4 text-violet-600" /> {{ $company['email'] }}</li>
                            @endif
                            @if(!empty($company['address_line_1']))
                                <li class="flex items-start gap-2"><flux:icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-violet-600" /> <span>{{ $company['address_line_1'] }}@if(!empty($company['address_line_2'])), {{ $company['address_line_2'] }}@endif</span></li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-zinc-200 pt-6 text-xs text-zinc-400 sm:flex-row">
                    <p>&copy; {{ date('Y') }} {{ $company['name'] ?? $storeName }}. {{ __('store.footer_rights') }}</p>
                    <p>{{ __('store.footer_tagline') }}</p>
                </div>
            </div>
        </footer>

        {{-- ===================== TOAST ===================== --}}
        <div
            x-data="{ show: false, msg: '', t: null }"
            x-on:cart-notify.window="msg = $event.detail.message; show = true; clearTimeout(t); t = setTimeout(() => show = false, 2800)"
            x-show="show"
            x-cloak
            x-transition.opacity.duration.200ms
            class="fixed inset-x-0 bottom-24 z-[70] mx-auto flex w-fit max-w-[90vw] items-center gap-3 rounded-2xl bg-zinc-900 px-4 py-3 text-sm font-medium text-white shadow-xl md:bottom-6"
        >
            <flux:icon name="check-circle" class="h-5 w-5 shrink-0 text-fuchsia-400" />
            <span x-text="msg" class="truncate"></span>
            <a href="{{ route('cart') }}" class="shrink-0 rounded-lg bg-white/15 px-3 py-1 text-xs font-semibold text-white transition-colors hover:bg-white/25">{{ __('store.view_cart') }}</a>
        </div>

        {{-- ===================== MOBILE BOTTOM NAV ===================== --}}
        @php $storeAuthed = auth()->check(); @endphp
        <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-zinc-200/70 bg-white/90 backdrop-blur-xl md:hidden" aria-label="{{ __('store.menu') }}">
            <div class="mx-auto grid max-w-lg grid-cols-5 px-1 pb-[env(safe-area-inset-bottom)]">
                @php $navHome = request()->routeIs('storefront.home') || request()->routeIs('home'); @endphp
                <a href="{{ route('storefront.home') }}" @class(['relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold leading-none transition-colors', 'text-violet-700' => $navHome, 'text-zinc-500' => ! $navHome])>
                    @if($navHome)<span class="store-grad absolute top-0 h-0.5 w-8 rounded-full"></span>@endif
                    <flux:icon name="home" class="h-[22px] w-[22px]" />
                    <span class="whitespace-nowrap">{{ __('store.nav_home') }}</span>
                </a>

                @php $navShop = request()->routeIs('shop'); @endphp
                <a href="{{ route('shop') }}" @class(['relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold leading-none transition-colors', 'text-violet-700' => $navShop, 'text-zinc-500' => ! $navShop])>
                    @if($navShop)<span class="store-grad absolute top-0 h-0.5 w-8 rounded-full"></span>@endif
                    <flux:icon name="squares-2x2" class="h-[22px] w-[22px]" />
                    <span class="whitespace-nowrap">{{ __('store.nav_shop') }}</span>
                </a>

                <a href="{{ route('storefront.home') }}#packages" class="relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold leading-none text-zinc-500 transition-colors">
                    <flux:icon name="gift" class="h-[22px] w-[22px]" />
                    <span class="whitespace-nowrap">{{ __('store.nav_packages') }}</span>
                </a>

                @php $navCart = request()->routeIs('cart'); @endphp
                <a href="{{ route('cart') }}" @class(['relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold leading-none transition-colors', 'text-violet-700' => $navCart, 'text-zinc-500' => ! $navCart])>
                    @if($navCart)<span class="store-grad absolute top-0 h-0.5 w-8 rounded-full"></span>@endif
                    <flux:icon name="shopping-cart" class="h-[22px] w-[22px]" />
                    <span class="whitespace-nowrap">{{ __('store.nav_cart') }}</span>
                </a>

                @php $navAcct = request()->routeIs('dashboard'); @endphp
                <a href="{{ $storeAuthed ? route('dashboard') : route('login') }}" @class(['relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold leading-none transition-colors', 'text-violet-700' => $navAcct, 'text-zinc-500' => ! $navAcct])>
                    @if($navAcct)<span class="store-grad absolute top-0 h-0.5 w-8 rounded-full"></span>@endif
                    <flux:icon name="user" class="h-[22px] w-[22px]" />
                    <span class="whitespace-nowrap">{{ $storeAuthed ? __('store.nav_account') : __('store.nav_login') }}</span>
                </a>
            </div>
        </nav>

        @fluxScripts
        @stack('scripts')
    </body>
</html>
