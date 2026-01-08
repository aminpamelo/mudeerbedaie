@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'w-full bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg p-6 ' . $class]) }}>
    {{ $slot }}
</div>