<div>
    <label class="field-label">
        {{ $field['label'] }}
        @if ($field['required'] ?? false)<span class="required-dot" aria-label="required"></span>@endif
    </label>
    <label for="{{ $field['id'] }}" class="file-drop">
        <span class="file-drop-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
        </span>
        <div class="min-w-0 flex-1">
            <div class="file-drop-text" id="filename-{{ $field['id'] }}">Upload file</div>
            @php($acceptList = collect($field['accept'] ?? ['pdf', 'doc', 'docx'])->map(fn ($e) => strtoupper($e))->implode(', '))
            <div class="file-drop-hint">{{ $acceptList }} up to {{ floor((($field['max_size_kb'] ?? 5120)) / 1024) }} MB</div>
        </div>
    </label>
    <input type="file" id="{{ $field['id'] }}" name="{{ $field['id'] }}"
           @if (! empty($field['accept'])) accept=".{{ implode(',.', $field['accept']) }}" @endif
           @if ($field['required'] ?? false) required @endif
           class="sr-only"
           onchange="document.getElementById('filename-{{ $field['id'] }}').textContent = this.files[0] ? this.files[0].name : 'Upload file';">
    @if (! empty($field['help_text']))
        <p class="hint-text">{{ $field['help_text'] }}</p>
    @endif
    @error($field['id'])
        <p class="hint-text" style="color: var(--rec-danger);">{{ $message }}</p>
    @enderror
</div>
