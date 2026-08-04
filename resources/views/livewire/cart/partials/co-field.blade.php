{{-- Storefront checkout text field. Params: $model (required), $label, $ph, $type, $req, $inputmode --}}
@php
    $type = $type ?? 'text';
    $ph = $ph ?? '';
    $req = $req ?? false;
@endphp
<div>
    @isset($label)
        <label class="mb-1.5 block text-sm font-semibold text-zinc-700">
            {{ $label }}@if($req)<span class="text-rose-500"> *</span>@endif
        </label>
    @endisset
    <input
        type="{{ $type }}"
        wire:model="{{ $model }}"
        placeholder="{{ $ph }}"
        @isset($inputmode) inputmode="{{ $inputmode }}" @endisset
        class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-200/70 @error($model) border-rose-300 focus:border-rose-400 focus:ring-rose-200/70 @enderror"
    />
    @error($model)<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>
