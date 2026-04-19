<?php

namespace App\Http\Requests\LiveHostPocket;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request payload for POST /live-host/sessions/{session}/attachments.
 *
 * A single file (image, video, or PDF — 20 MB max) with an optional short
 * description. The attachment row is keyed off `LiveSessionAttachment` which
 * stores `file_type` from Symfony's server-side `getMimeType()` detection so
 * the recap screen can render the correct icon/thumbnail and
 * `hasVisualProof()` can trust the stored mime.
 */
class AddAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'live_host';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/*,video/*,application/pdf'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
