<div>
    <label for="{{ $field['id'] }}" class="field-label">
        {{ $field['label'] }}
        @if ($field['required'] ?? false)<span class="required-dot" aria-label="required"></span>@endif
    </label>
    <input type="url" id="{{ $field['id'] }}" name="{{ $field['id'] }}"
           value="{{ old($field['id']) }}"
           placeholder="{{ $field['placeholder'] ?? 'https://' }}"
           @if ($field['required'] ?? false) required @endif
           class="input-base">
    @if (! empty($field['help_text']))
        <p class="hint-text">{{ $field['help_text'] }}</p>
    @endif
    @error($field['id'])
        <p class="hint-text" style="color: var(--rec-danger);">{{ $message }}</p>
    @enderror
</div>
