<div class="teacher-card mb-6 p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 lg:gap-4">
    <div class="flex flex-1 flex-wrap items-center gap-3">
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:ml-auto">
            {{ $actions }}
        </div>
    @endisset
</div>
