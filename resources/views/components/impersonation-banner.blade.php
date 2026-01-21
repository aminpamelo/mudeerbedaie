@php
    $impersonationService = app(\App\Services\ImpersonationService::class);
    $isImpersonating = $impersonationService->isImpersonating();
    $impersonator = $isImpersonating ? $impersonationService->getImpersonator() : null;
@endphp

@if($isImpersonating && $impersonator)
<div class="bg-amber-500 dark:bg-amber-600">
    <div class="mx-auto max-w-7xl px-3 py-2 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-1 items-center">
                <span class="flex rounded-md bg-amber-800 p-1 mr-3">
                    <flux:icon name="eye" class="h-5 w-5 text-white" />
                </span>
                <p class="text-sm font-medium text-white">
                    <span class="hidden md:inline">
                        {{ $impersonator->name }} is impersonating
                    </span>
                    <span class="font-bold">{{ auth()->user()->name }}</span>
                    <span class="hidden md:inline">({{ auth()->user()->email }})</span>
                </p>
            </div>
            <form action="{{ route('impersonation.stop') }}" method="POST" class="flex-shrink-0">
                @csrf
                <button type="submit"
                    class="flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-amber-600 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-white">
                    <flux:icon name="arrow-right-start-on-rectangle" class="h-4 w-4 mr-1" />
                    Stop Impersonation
                </button>
            </form>
        </div>
    </div>
</div>
@endif
