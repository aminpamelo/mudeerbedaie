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

        return $user && in_array($user->role, ['admin_livehost', 'admin', 'livehost_assistant'], true);
    }

    /**
     * Normalise nullable ids and coerce is_template into a boolean.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'live_host_id' => $this->nullableId($this->input('live_host_id')),
            'live_host_platform_account_id' => $this->nullableId($this->input('live_host_platform_account_id')),
            'live_account_id' => $this->nullableId($this->input('live_account_id')),
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
        $isTemplate = (bool) $this->input('is_template');
        $scheduleDate = $this->input('schedule_date');

        return [
            // The creator account is the punca kuasa: a single account cannot
            // hold two slots at the same time/day/date (even across shops).
            'live_account_id' => [
                'required', 'integer', 'exists:live_accounts,id',
                Rule::unique('live_schedule_assignments', 'live_account_id')
                    ->where(fn ($q) => $q
                        ->where('time_slot_id', $this->input('time_slot_id'))
                        ->where('day_of_week', $this->input('day_of_week'))
                        ->where('is_template', $isTemplate)
                        ->when(
                            ! $isTemplate && $scheduleDate,
                            fn ($q) => $q->whereDate('schedule_date', $scheduleDate),
                            fn ($q) => $q->whereNull('schedule_date')
                        )
                    ),
            ],
            // The shop is the commerce reference being promoted in this block;
            // many accounts may share one shop+time, so it no longer gates
            // uniqueness.
            'platform_account_id' => ['required', 'exists:platform_accounts,id'],
            'time_slot_id' => ['required', 'exists:live_time_slots,id'],
            'live_host_id' => ['nullable', 'exists:users,id'],
            'live_host_platform_account_id' => [
                'nullable',
                'integer',
                'exists:live_host_platform_account,id',
            ],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedule_date' => ['nullable', 'required_if:is_template,false', 'date_format:Y-m-d'],
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
            'schedule_date.required_if' => 'Pick a specific date when the slot is not a weekly template.',
            'live_account_id.unique' => 'This account is already scheduled for that time slot and day on the selected date.',
            'live_account_id.required' => 'Choose the creator account that will go live.',
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
