@props([
    'container' => null,
])

@php
$classes = Flux::classes('[grid-area:main]')
    ->add('w-full min-w-0')
    ->add($container ? 'mx-auto [:where(&)]:max-w-7xl' : '')
    ;
@endphp

<div {{ $attributes->class($classes) }} data-flux-main>
    <div class="px-4 py-4 sm:px-6 sm:py-4 lg:px-8 lg:py-8 w-full">
        {{ $slot }}
    </div>
</div>
