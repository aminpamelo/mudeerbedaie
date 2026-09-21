@php
    /** @var \App\Models\Product|\App\Models\Package $item */
    $shops = $item->linkedShopAccounts();
    $linked = $item->isLinkedToShop();
@endphp
@if($linked)
    @if($shops->isNotEmpty())
        <flux:tooltip content="{{ $shops->map(fn ($shop) => $shop->name)->join(', ') }}">
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20">
                <flux:icon name="check-circle" class="h-3.5 w-3.5" />
                {{ $shops->count() }} {{ Str::plural('shop', $shops->count()) }}
            </span>
        </flux:tooltip>
    @else
        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20">
            <flux:icon name="check-circle" class="h-3.5 w-3.5" />
            {{ __('Linked') }}
        </span>
    @endif
@else
    <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400 dark:text-gray-500">
        <flux:icon name="minus-circle" class="h-3.5 w-3.5" />
        {{ __('Not linked') }}
    </span>
@endif
