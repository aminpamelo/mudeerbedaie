@props(['classModel'])

{{-- Small square cover thumbnail for a class row. Uses the class image
     (falling back to the course thumbnail via the image_url accessor); shows a
     branded placeholder when neither exists. Prop is named `classModel` because
     `class` is a reserved Blade component attribute. --}}
@php($url = $classModel->image_url)

<div {{ $attributes->merge(['class' => 'relative h-10 w-10 shrink-0 overflow-hidden rounded-lg']) }}>
    @if ($url)
        <img
            src="{{ $url }}"
            alt="{{ $classModel->title }}"
            class="h-full w-full object-cover"
            loading="lazy"
        >
    @else
        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-500 to-violet-700 text-[13px] font-bold text-white">
            {{ Str::upper(Str::substr($classModel->title, 0, 1)) }}
        </div>
    @endif
</div>
