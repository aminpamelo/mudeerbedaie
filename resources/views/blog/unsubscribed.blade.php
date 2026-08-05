<x-layouts.store :seo="['title' => __('blog.unsubscribe_title'), 'description' => __('blog.unsubscribe_message'), 'noindex' => true]">
    <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center sm:px-6 lg:px-8">
        <span class="grid h-16 w-16 place-items-center rounded-2xl bg-gradient-to-br from-violet-100 via-fuchsia-100 to-rose-100">
            <flux:icon name="envelope-open" class="h-7 w-7 text-violet-600" />
        </span>

        <h1 class="font-display mt-6 text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('blog.unsubscribe_title') }}</h1>
        <p class="mt-3 max-w-md text-sm leading-relaxed text-zinc-600">{{ __('blog.unsubscribe_message') }}</p>

        <a href="{{ route('blog.index') }}" class="store-grad store-grad-hover mt-8 inline-flex h-11 items-center rounded-xl px-6 text-sm font-bold text-white">
            {{ __('blog.unsubscribe_back') }}
        </a>
    </section>
</x-layouts.store>
