<x-layouts.store :seo="$seo">
    @php
        $savingsPct = $package->getSavingsPercentage();
        $originalPrice = $package->calculateOriginalPrice();
        $savings = $package->calculateSavings();
        $whatsapp = config('store.whatsapp');
        $waUrl = $whatsapp
            ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($package->name . ' — ' . $package->formatted_price)
            : null;
    @endphp

    {{-- Breadcrumb --}}
    <section class="border-b border-violet-100/70 bg-gradient-to-b from-violet-50/60 to-white">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <nav class="flex flex-wrap items-center gap-1.5 text-xs font-medium text-zinc-400">
                <a href="{{ route('storefront.home') }}" class="hover:text-violet-700">{{ __('store.nav_home') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <a href="{{ route('storefront.home') }}#packages" class="hover:text-violet-700">{{ __('store.nav_packages') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <span class="truncate text-zinc-600">{{ $package->name }}</span>
            </nav>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-12">
            {{-- Visual --}}
            <div>
                <div class="relative aspect-[4/3] overflow-hidden rounded-2xl ring-1 ring-zinc-900/5">
                    @if($package->featured_image)
                        <div class="h-full w-full bg-zinc-50 bg-cover bg-center" style="background-image:url('{{ $package->featured_image }}')" role="img" aria-label="{{ $package->name }}"></div>
                    @else
                        <div class="store-grad flex h-full w-full items-center justify-center">
                            <flux:icon name="gift" class="h-20 w-20 text-white/90" />
                        </div>
                    @endif
                    <span class="absolute left-4 top-4 rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white backdrop-blur">{{ __('store.package_badge') }}</span>
                    @if($savingsPct > 0)
                        <span class="absolute right-4 top-4 rounded-full bg-amber-300 px-3 py-1 text-xs font-extrabold text-amber-950 tabular-nums">{{ __('store.package_save_pct', ['pct' => $savingsPct]) }}</span>
                    @endif
                </div>
            </div>

            {{-- Info --}}
            <div class="flex flex-col">
                <span class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600">{{ __('store.package_badge') }}</span>
                <h1 class="font-display mt-1.5 text-2xl font-extrabold leading-tight text-zinc-900 sm:text-3xl">{{ $package->name }}</h1>

                @if($package->short_description)
                    <p class="mt-3 text-sm leading-relaxed text-zinc-600">{{ $package->short_description }}</p>
                @endif

                <div class="mt-5 flex items-end gap-3">
                    <span class="store-grad-text font-display text-3xl font-extrabold tabular-nums">{{ $package->formatted_price }}</span>
                    @if($originalPrice > $package->price)
                        <span class="pb-1 text-base text-zinc-400 line-through tabular-nums">{{ $package->formatted_original_price }}</span>
                    @endif
                </div>
                @if($savings > 0)
                    <div class="mt-2">
                        <span class="inline-flex items-center gap-1 rounded-md bg-fuchsia-50 px-2.5 py-1 text-xs font-semibold text-fuchsia-700">
                            <flux:icon name="sparkles" class="h-3.5 w-3.5" /> {{ __('store.package_save', ['amount' => $package->formatted_savings]) }}
                        </span>
                    </div>
                @endif

                <div class="mt-6">
                    <livewire:store.package-cart :package="$package" :key="'pkgc-'.$package->id" />
                </div>

                @if($waUrl)
                    <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:border-violet-300 hover:text-violet-700">
                        <flux:icon name="chat-bubble-left-right" class="h-4 w-4" />
                        {{ __('store.package_order') }}
                    </a>
                @endif

                <div class="mt-8 grid grid-cols-1 gap-3 border-t border-zinc-100 pt-6 text-sm text-zinc-600 sm:grid-cols-2">
                    <div class="flex items-center gap-2"><flux:icon name="shield-check" class="h-4 w-4 text-violet-600" /> {{ __('store.stat_secure') }}</div>
                    <div class="flex items-center gap-2"><flux:icon name="truck" class="h-4 w-4 text-fuchsia-600" /> {{ __('store.stat_delivery') }}</div>
                </div>
            </div>
        </div>

        {{-- What's included --}}
        <section class="mt-12 max-w-3xl">
            <h2 class="font-display text-xl font-bold text-zinc-900">{{ __('store.package_includes') }}</h2>
            <div class="mt-4 divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-100 bg-white">
                @foreach($package->products as $product)
                    <div class="flex items-center gap-3 p-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-violet-50 text-violet-500">
                            @if($product->primaryImage?->url)
                                <img src="{{ $product->primaryImage->url }}" alt="{{ $product->name }}" class="h-full w-full object-cover" onerror="this.style.display='none'">
                            @else
                                <flux:icon name="cube" class="h-5 w-5" />
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-zinc-900">{{ $product->name }}</div>
                            @if($product->category)
                                <div class="text-xs text-zinc-400">{{ $product->category->name }}</div>
                            @endif
                        </div>
                        <span class="shrink-0 text-sm font-semibold tabular-nums text-zinc-500">&times;{{ $product->pivot->quantity }}</span>
                    </div>
                @endforeach

                @foreach($package->courses as $course)
                    <div class="flex items-center gap-3 p-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-fuchsia-50 text-fuchsia-500"><flux:icon name="academic-cap" class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold text-zinc-900">{{ $course->name }}</div><div class="text-xs text-zinc-400">{{ __('store.package_course') }}</div></div>
                    </div>
                @endforeach

                @foreach($package->classes as $class)
                    <div class="flex items-center gap-3 p-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-500"><flux:icon name="user-group" class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold text-zinc-900">{{ $class->title }}</div><div class="text-xs text-zinc-400">{{ __('store.package_class') }}</div></div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Description --}}
        @if($package->description)
            <section class="mt-10 max-w-3xl">
                <h2 class="font-display text-xl font-bold text-zinc-900">{{ __('store.description_title') }}</h2>
                <div class="mt-4 text-sm leading-relaxed text-zinc-600">{!! nl2br(e($package->description)) !!}</div>
            </section>
        @endif
    </div>

</x-layouts.store>
