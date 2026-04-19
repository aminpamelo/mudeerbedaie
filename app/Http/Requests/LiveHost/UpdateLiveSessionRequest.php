<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLiveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('live_host_id')) {
            $payload['live_host_id'] = $this->nullableId($this->input('live_host_id'));
        }

        if ($this->has('platform_account_id')) {
            $payload['platform_account_id'] = $this->nullableId($this->input('platform_account_id'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'live_host_id' => ['nullable', 'integer', 'exists:users,id'],
            'platform_account_id' => ['nullable', 'integer', 'exists:platform_accounts,id'],
            'status' => ['required', Rule::in(['scheduled', 'live', 'ended', 'cancelled', 'missed'])],

            'scheduled_start_at' => ['nullable', 'date'],
            'actual_start_at' => ['nullable', 'date'],
            'actual_end_at' => ['nullable', 'date', 'after_or_equal:actual_start_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:86400'],

            'remarks' => ['nullable', 'string', 'max:5000'],

            'missed_reason_code' => ['nullable', Rule::in(['tech_issue', 'sick', 'account_issue', 'schedule_conflict', 'other'])],
            'missed_reason_note' => ['nullable', 'string', 'max:1000'],

            'analytics' => ['nullable', 'array'],
            'analytics.viewers_peak' => ['nullable', 'integer', 'min:0'],
            'analytics.viewers_avg' => ['nullable', 'integer', 'min:0'],
            'analytics.total_likes' => ['nullable', 'integer', 'min:0'],
            'analytics.total_comments' => ['nullable', 'integer', 'min:0'],
            'analytics.total_shares' => ['nullable', 'integer', 'min:0'],
            'analytics.gifts_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }
}
