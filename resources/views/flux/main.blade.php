@props([
    'container' => null,
])

@php
$classes = Flux::classes('[grid-area:main]')
    ->add('p-3 sm:p-4 lg:p-8')
    ->add('w-full min-w-0')
    ->add('[[data-flux-container]_&]:px-0') // If there is a wrapping container, let IT handle the x padding...
    ->add($container ? 'mx-auto [:where(&)]:max-w-7xl' : '')
    ;
@endphp

<div {{ $attributes->class($classes) }} data-flux-main>
    {{ $slot }}
</div>
