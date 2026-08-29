@props(['latest', 'popular'])

@php
    $hasLatest = $latest->isNotEmpty();
    $hasPopular = $popular->isNotEmpty();
    // Server-render the default panel visible; only the other one is x-cloak'd so
    // there is no flash and the list still reads without JavaScript.
    $default = $hasLatest ? 'latest' : 'popular';
@endphp

@if($hasLatest || $hasPopular)
    <section class="overflow-hidden rounded-2xl border border-zinc-100 bg-white"
             x-data="{ tab: '{{ $default }}' }"
             aria-label="{{ __('blog.related_title') }}">
        {{-- Tabs --}}
        <div class="flex" role="tablist">
            @if($hasLatest)
                <button type="button" role="tab" @click="tab = 'latest'"
                        :aria-selected="tab === 'latest'"
                        class="relative flex-1 px-4 py-3.5 text-sm font-bold transition-colors"
                        :class="tab === 'latest' ? 'text-violet-700' : 'text-zinc-400 hover:text-zinc-600'">
                    <span class="inline-flex items-center justify-center gap-1.5">
                        <flux:icon name="clock" class="h-4 w-4" />
                        {{ __('blog.latest_title') }}
                    </span>
                    <span x-show="tab === 'latest'" class="store-grad absolute inset-x-4 bottom-0 h-0.5 rounded-full"></span>
                </button>
            @endif
            @if($hasPopular)
                <button type="button" role="tab" @click="tab = 'popular'"
                        :aria-selected="tab === 'popular'"
                        class="relative flex-1 px-4 py-3.5 text-sm font-bold transition-colors"
                        :class="tab === 'popular' ? 'text-violet-700' : 'text-zinc-400 hover:text-zinc-600'">
                    <span class="inline-flex items-center justify-center gap-1.5">
                        <flux:icon name="fire" class="h-4 w-4" />
                        {{ __('blog.popular_title') }}
                    </span>
                    <span x-show="tab === 'popular'" class="store-grad absolute inset-x-4 bottom-0 h-0.5 rounded-full"></span>
                </button>
            @endif
        </div>

        <div class="border-t border-zinc-100"></div>

        {{-- Panels --}}
        @if($hasLatest)
            <ul class="space-y-0.5 p-2" role="tabpanel"
                x-show="tab === 'latest'" @if($default !== 'latest') x-cloak @endif>
                @foreach($latest as $item)
                    <x-store.article-rail-item :post="$item" />
                @endforeach
            </ul>
        @endif

        @if($hasPopular)
            <ul class="space-y-0.5 p-2" role="tabpanel"
                x-show="tab === 'popular'" @if($default !== 'popular') x-cloak @endif>
                @foreach($popular as $item)
                    <x-store.article-rail-item :post="$item" />
                @endforeach
            </ul>
        @endif
    </section>
@endif
