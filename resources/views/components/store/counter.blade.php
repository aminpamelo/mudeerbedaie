@props([
    'value' => 0,
    'suffix' => '',
])

@php
    $target = (int) $value;
@endphp

{{--
    Counts up from zero the first time it scrolls into view.

    The final value is rendered server-side inside the element, so crawlers and
    no-JS visitors read the real number; Alpine only takes over once it boots.
    Under `prefers-reduced-motion` it snaps straight to the target.
--}}
<span
    x-data="{
        shown: {{ $target }},
        target: {{ $target }},
        run() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || this.target <= 0) {
                return;
            }

            const duration = 1500;
            const started = performance.now();
            const step = (now) => {
                const progress = Math.min((now - started) / duration, 1);
                // Cubic ease-out: fast at first, settling gently on the number.
                this.shown = Math.round(this.target * (1 - Math.pow(1 - progress, 3)));
                if (progress < 1) requestAnimationFrame(step);
            };

            this.shown = 0;
            requestAnimationFrame(step);
        },
    }"
    x-intersect.once="run()"
    {{ $attributes->merge(['class' => 'tabular-nums']) }}
><span x-text="shown.toLocaleString()">{{ number_format($target) }}</span>{{ $suffix }}</span>
