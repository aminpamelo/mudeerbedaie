<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
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
     * Normalise boolean flags before validation so HTML form values ('0', '1',
     * 'on', 'false') and JSON booleans resolve consistently.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->toBool($this->input('is_active'), true),
            'is_recurring' => $this->toBool($this->input('is_recurring'), true),
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
            'live_host_id' => ['nullable', 'exists:users,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['boolean'],
            'is_recurring' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:500'],
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
}
