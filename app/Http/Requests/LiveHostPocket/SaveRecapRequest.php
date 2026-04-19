<?php

namespace App\Http\Requests\LiveHostPocket;

use App\Models\LiveSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request payload for POST /live-host/sessions/{session}/recap.
 *
 * Branches on the required `went_live` boolean. When true, all timing +
 * analytics fields are accepted but nullable, and an after-hook requires
 * at least one image/video attachment on the session row as proof. When
 * false, a `missed_reason_code` enum is required and everything else is
 * ignored.
 */
class SaveRecapRequest extends FormRequest
{
    public const MISSED_REASONS = [
        'tech_issue',
        'sick',
        'account_issue',
        'schedule_conflict',
        'other',
    ];

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
            'went_live' => ['required', 'boolean'],

            // went_live === true branch
            'remarks' => ['nullable', 'string', 'max:1000'],
            'actual_start_at' => ['nullable', 'date'],
            'actual_end_at' => ['nullable', 'date', 'after:actual_start_at'],
            'viewers_peak' => ['nullable', 'integer', 'min:0'],
            'viewers_avg' => ['nullable', 'integer', 'min:0'],
            'total_likes' => ['nullable', 'integer', 'min:0'],
            'total_comments' => ['nullable', 'integer', 'min:0'],
            'total_shares' => ['nullable', 'integer', 'min:0'],
            'gifts_value' => ['nullable', 'numeric', 'min:0'],
            'gmv_amount' => ['required_if:went_live,true', 'nullable', 'numeric', 'min:0', 'max:9999999.99'],

            // went_live === false branch
            'missed_reason_code' => [
                'required_if:went_live,false',
                'nullable',
                'in:'.implode(',', self::MISSED_REASONS),
            ],
            'missed_reason_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Extra guards layered on top of the rule-based validation so we only
     * hit the DB when the payload shape is already valid:
     *
     * 1. Proof-of-live: when `went_live=true`, require at least one image or
     *    video attachment on this session.
     * 2. GMV proof: when `went_live=true`, require at least one attachment
     *    flagged `tiktok_shop_screenshot` to substantiate the self-reported
     *    GMV amount.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('went_live')) {
                return;
            }

            /** @var LiveSession|null $session */
            $session = $this->route('session');
            if (! $session instanceof LiveSession) {
                return;
            }

            if (! $session->hasVisualProof()) {
                $validator->errors()->add(
                    'proof',
                    'Upload at least one image or video as proof you went live.'
                );
            }

            if (! $session->hasTikTokShopScreenshot()) {
                $validator->errors()->add(
                    'tiktok_shop_screenshot',
                    'Upload the TikTok Shop backend screenshot so we can verify the GMV you entered.'
                );
            }
        });
    }
}
