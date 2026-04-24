<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin', 'livehost_assistant'], true);
    }

    /**
     * Normalise blank platform_account_id / day_of_week strings to null and
     * coerce is_active into a boolean before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'platform_account_id' => $this->nullableId($this->input('platform_account_id')),
            'day_of_week' => $this->nullableInt($this->input('day_of_week')),
            'is_active' => $this->toBool($this->input('is_active'), true),
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
            'platform_account_id' => ['nullable', 'integer', 'exists:platform_accounts,id'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'draft'])],
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
            'end_time.after' => 'The end time must be later than the start time.',
            'day_of_week.between' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }
}
