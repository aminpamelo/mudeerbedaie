<div>
    <label for="{{ $field['id'] }}" class="field-label">
        {{ $field['label'] }}
        @if ($field['required'] ?? false)<span class="required-dot" aria-label="required"></span>@endif
    </label>
    <select id="{{ $field['id'] }}" name="{{ $field['id'] }}"
            class="input-base"
            @if ($field['required'] ?? false) required @endif>
        <option value="">—</option>
        @foreach (($field['options'] ?? []) as $opt)
            <option value="{{ $opt['value'] }}" @selected(old($field['id']) == $opt['value'])>{{ $opt['label'] }}</option>
        @endforeach
    </select>
    @if (! empty($field['help_text']))
        <p class="hint-text">{{ $field['help_text'] }}</p>
    @endif
    @error($field['id'])
        <p class="hint-text" style="color: var(--rec-danger);">{{ $message }}</p>
    @enderror
</div>
