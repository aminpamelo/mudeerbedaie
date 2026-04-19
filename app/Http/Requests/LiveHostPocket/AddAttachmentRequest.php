<?php

namespace App\Http\Requests\LiveHostPocket;

use App\Models\LiveSessionAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request payload for POST /live-host/sessions/{session}/attachments.
 *
 * A single file (image, video, or PDF — 20 MB max) with an optional short
 * description. The attachment row is keyed off `LiveSessionAttachment` which
 * stores `file_type` from Symfony's server-side `getMimeType()` detection so
 * the recap screen can render the correct icon/thumbnail and
 * `hasVisualProof()` can trust the stored mime.
 *
 * An optional `attachment_type` flags the upload as a specific proof
 * document — e.g. `tiktok_shop_screenshot` for GMV verification.
 */
class AddAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'live_host';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/*,video/*,application/pdf'],
            'description' => ['nullable', 'string', 'max:255'],
            'attachment_type' => ['nullable', Rule::in(LiveSessionAttachment::TYPES)],
        ];
    }
}
