<?php

namespace App\Http\Requests\LiveHostPocket;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request payload for POST /live-host/sessions/{session}/attachments.
 *
 * A single file (any mime, 10 MB max) with an optional short description
 * — the attachment row is keyed off `LiveSessionAttachment` which stores
 * `file_type` from `getClientMimeType()` so the recap screen can render the
 * correct icon/thumbnail.
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
            'file' => ['required', 'file', 'max:10240'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
