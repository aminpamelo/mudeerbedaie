@props(['classModel'])

{{-- Inline per-row switch for a class's student-portal visibility. Calls the
     class-list component's toggleStudentVisibility() action. Prop is named
     `classModel` because `class` is a reserved Blade component attribute. --}}
@php($on = (bool) $classModel->is_visible_to_students)

<div class="flex items-center gap-1.5" wire:key="vis-{{ $classModel->id }}">
    <button
        type="button"
        wire:click="toggleStudentVisibility({{ $classModel->id }})"
        wire:loading.attr="disabled"
        wire:target="toggleStudentVisibility({{ $classModel->id }})"
        role="switch"
        aria-checked="{{ $on ? 'true' : 'false' }}"
        title="{{ $on ? 'Visible to students — click to hide' : 'Hidden from students — click to show' }}"
        @class([
            'relative inline-flex h-[18px] w-8 shrink-0 cursor-pointer items-center rounded-full transition-colors',
            'bg-violet-600' => $on,
            'bg-zinc-300 dark:bg-zinc-600' => ! $on,
        ])
    >
        <span @class([
            'inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform',
            'translate-x-[15px]' => $on,
            'translate-x-0.5' => ! $on,
        ])></span>
    </button>
    <span @class([
        'text-[10px] font-medium',
        'text-violet-600 dark:text-violet-400' => $on,
        'text-zinc-400' => ! $on,
    ])>{{ $on ? 'Students' : 'Hidden' }}</span>
</div>
