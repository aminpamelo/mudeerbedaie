<div>
    <label for="{{ $field['id'] }}" class="field-label">
        {{ $field['label'] }}
        @if ($field['required'] ?? false)<span class="required-dot" aria-label="required"></span>@endif
    </label>
    <textarea id="{{ $field['id'] }}" name="{{ $field['id'] }}"
              rows="{{ $field['rows'] ?? 4 }}"
              placeholder="{{ $field['placeholder'] ?? '' }}"
              @if ($field['required'] ?? false) required @endif
              class="input-base">{{ old($field['id']) }}</textarea>
    @if (! empty($field['help_text']))
        <p class="hint-text">{{ $field['help_text'] }}</p>
    @endif
    @error($field['id'])
        <p class="hint-text" style="color: var(--rec-danger);">{{ $message }}</p>
    @enderror
</div>
