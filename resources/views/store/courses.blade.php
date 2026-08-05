<x-layouts.store :seo="$seo">

    {{-- Page header --}}
    <section class="relative overflow-hidden border-b border-violet-100/70 bg-gradient-to-b from-violet-50 via-fuchsia-50/50 to-white">
        <span class="pointer-events-none absolute -right-20 -top-20 h-60 w-60 rounded-full bg-fuchsia-300/30 blur-3xl"></span>
        <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <nav class="mb-3 flex items-center gap-1.5 text-xs font-medium text-zinc-400">
                <a href="{{ route('storefront.home') }}" class="hover:text-violet-700">{{ __('store.nav_home') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <span class="text-zinc-600">{{ __('store.lms_title') }}</span>
            </nav>
            <div class="flex items-center gap-2">
                <span class="store-grad grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white shadow-lg shadow-fuchsia-500/25">
                    <flux:icon name="academic-cap" class="h-5 w-5" />
                </span>
                <h1 class="font-display text-3xl font-extrabold text-zinc-900 sm:text-4xl">{{ __('store.lms_title') }}</h1>
            </div>
            <p class="mt-1.5 text-sm text-zinc-500">{{ __('store.lms_subtitle') }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if($courses->isNotEmpty())
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
                    <x-store.course-card :course="$course" />
                @endforeach
            </div>
        @else
            <div class="grid place-items-center rounded-2xl border border-dashed border-zinc-200 bg-white py-20 text-center">
                <flux:icon name="academic-cap" class="h-12 w-12 text-zinc-300" />
                <h3 class="font-display mt-3 text-base font-bold text-zinc-900">{{ __('store.lms_empty_title') }}</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ __('store.lms_empty_text') }}</p>
            </div>
        @endif
    </div>

</x-layouts.store>
