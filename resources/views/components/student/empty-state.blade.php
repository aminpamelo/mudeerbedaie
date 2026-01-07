{{-- Reusable Empty State Component --}}
@props([
    'type' => 'default', // default, no-classes, no-sessions, no-orders, no-invoices, no-activity, search-no-results
    'title' => null,
    'description' => null,
    'icon' => null,
    'actionUrl' => null,
    'actionLabel' => null,
])

@php
    $presets = [
        'default' => [
            'icon' => 'inbox',
            'title' => __('student.empty.no_data'),
            'description' => __('student.empty.no_data_desc'),
        ],
        'no-classes' => [
            'icon' => 'academic-cap',
            'title' => __('student.empty.no_classes'),
            'description' => __('student.empty.no_classes_desc'),
            'actionUrl' => route('student.courses'),
            'actionLabel' => __('student.empty.browse_courses'),
        ],
        'no-sessions' => [
            'icon' => 'calendar',
            'title' => __('student.empty.no_sessions'),
            'description' => __('student.empty.no_sessions_desc'),
        ],
        'no-sessions-today' => [
            'icon' => 'sun',
            'title' => __('student.empty.no_sessions_today'),
            'description' => __('student.empty.no_sessions_today_desc'),
        ],
        'no-orders' => [
            'icon' => 'shopping-bag',
            'title' => __('student.empty.no_orders'),
            'description' => __('student.empty.no_orders_desc'),
        ],
        'no-invoices' => [
            'icon' => 'document-text',
            'title' => __('student.empty.no_invoices'),
            'description' => __('student.empty.no_invoices_desc'),
        ],
        'no-activity' => [
            'icon' => 'clock',
            'title' => __('student.empty.no_activity'),
            'description' => __('student.empty.no_activity_desc'),
        ],
        'no-payment-methods' => [
            'icon' => 'credit-card',
            'title' => __('student.empty.no_payment_methods'),
            'description' => __('student.empty.no_payment_methods_desc'),
        ],
        'no-subscriptions' => [
            'icon' => 'arrow-path',
            'title' => __('student.empty.no_subscriptions'),
            'description' => __('student.empty.no_subscriptions_desc'),
        ],
        'search-no-results' => [
            'icon' => 'magnifying-glass',
            'title' => __('student.empty.no_results'),
            'description' => __('student.empty.no_results_desc'),
        ],
    ];

    $preset = $presets[$type] ?? $presets['default'];

    $finalIcon = $icon ?? $preset['icon'];
    $finalTitle = $title ?? $preset['title'];
    $finalDescription = $description ?? $preset['description'];
    $finalActionUrl = $actionUrl ?? ($preset['actionUrl'] ?? null);
    $finalActionLabel = $actionLabel ?? ($preset['actionLabel'] ?? null);
@endphp

<div {{ $attributes->merge(['class' => 'py-10 px-4 text-center']) }}>
    {{-- Icon with soft background --}}
    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
        <flux:icon name="{{ $finalIcon }}" class="w-8 h-8 text-gray-400" />
    </div>

    {{-- Title --}}
    <flux:heading size="base" class="text-gray-700 mb-1">{{ $finalTitle }}</flux:heading>

    {{-- Description --}}
    <flux:text size="sm" class="text-gray-500 max-w-xs mx-auto">{{ $finalDescription }}</flux:text>

    {{-- Optional Action Button --}}
    @if($finalActionUrl && $finalActionLabel)
        <div class="mt-4">
            <flux:button variant="primary" size="sm" :href="$finalActionUrl" wire:navigate>
                {{ $finalActionLabel }}
            </flux:button>
        </div>
    @endif

    {{-- Slot for custom content --}}
    @if($slot->isNotEmpty())
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</div>
