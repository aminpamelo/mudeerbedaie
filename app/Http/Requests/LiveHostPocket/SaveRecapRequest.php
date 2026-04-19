<?php

namespace App\Http\Requests\LiveHostPocket;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request payload for POST /live-host/sessions/{session}/recap.
 *
 * Accepts the fields rendered on screen 03 (UPLOAD/RECAP) of the Live Host
 * Pocket: an optional cover image + optional actual-timing overrides +
 * optional analytics counters + optional remarks. Nothing is required —
 * hosts can save partial recaps and refine later. Role authorization mirrors
 * the surrounding `role:live_host` route middleware.
 */
class SaveRecapRequest extends FormRequest
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
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'actual_start_at' => ['nullable', 'date'],
            'actual_end_at' => ['nullable', 'date', 'after:actual_start_at'],
            'viewers_peak' => ['nullable', 'integer', 'min:0'],
            'viewers_avg' => ['nullable', 'integer', 'min:0'],
            'total_likes' => ['nullable', 'integer', 'min:0'],
            'total_comments' => ['nullable', 'integer', 'min:0'],
            'total_shares' => ['nullable', 'integer', 'min:0'],
            'gifts_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
