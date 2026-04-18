<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * Normalise nullable ids and coerce is_template into a boolean.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'live_host_id' => $this->nullableId($this->input('live_host_id')),
            'schedule_date' => $this->nullableString($this->input('schedule_date')),
            'is_template' => $this->toBool($this->input('is_template'), true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform_account_id' => ['required', 'exists:platform_accounts,id'],
            'time_slot_id' => ['required', 'exists:live_time_slots,id'],
            'live_host_id' => ['nullable', 'exists:users,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedule_date' => ['nullable', 'date_format:Y-m-d'],
            'is_template' => ['boolean'],
            'status' => ['nullable', Rule::in([
                'scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled',
            ])],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'day_of_week.between' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'schedule_date.date_format' => 'Schedule date must be a valid YYYY-MM-DD date.',
        ];
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (string) $value;
    }
}
