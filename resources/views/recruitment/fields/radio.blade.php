<div>
    <label class="field-label">
        {{ $field['label'] }}
        @if ($field['required'] ?? false)<span class="required-dot" aria-label="required"></span>@endif
    </label>
    @if (! empty($field['help_text']))
        <p class="hint-text" style="margin-top: -4px; margin-bottom: 8px;">{{ $field['help_text'] }}</p>
    @endif
    <div class="grid gap-2.5" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
        @php($selected = old($field['id']))
        @foreach (($field['options'] ?? []) as $opt)
            <label class="platform-option">
                <input type="radio" name="{{ $field['id'] }}" value="{{ $opt['value'] }}" @checked($selected == $opt['value']) @if ($field['required'] ?? false) required @endif>
                <span class="platform-label">{{ $opt['label'] }}</span>
            </label>
        @endforeach
    </div>
    @error($field['id'])
        <p class="hint-text" style="color: var(--rec-danger);">{{ $message }}</p>
    @enderror
</div>
