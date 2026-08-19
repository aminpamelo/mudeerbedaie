<?php

namespace App\Http\Requests\LiveHost;

use App\Models\LiveTimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionSlotRequest extends FormRequest
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
        // A bespoke start/end time resolves to a hidden one-off slot so the rest
        // of validation (uniqueness, exists) keeps keying on time_slot_id. Only a
        // valid, ordered window is resolved here — a malformed or end-before-start
        // time is left for the rules below to reject so no nonsensical slot is
        // ever created.
        [$start, $end] = $this->customTimeWindow();

        $this->merge([
            'live_host_id' => $this->nullableId($this->input('live_host_id')),
            'live_host_platform_account_id' => $this->nullableId($this->input('live_host_platform_account_id')),
            'live_account_id' => $this->nullableId($this->input('live_account_id')),
            'schedule_date' => $this->nullableString($this->input('schedule_date')),
            'is_template' => $this->toBool($this->input('is_template'), true),
            'time_slot_id' => LiveTimeSlot::resolveAssignmentTimeSlotId(
                $start,
                $end,
                $this->nullableId($this->input('time_slot_id')),
                $this->user()?->id,
            ),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Note: `live_account_id` and `live_host_platform_account_id` are nullable
     * on update so legacy slots created before the account became the punca
     * kuasa (and unresolved backfill rows) can still be edited without forcing
     * a backfill. New slots must provide live_account_id — see
     * StoreSessionSlotRequest.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isTemplate = (bool) $this->input('is_template');
        $scheduleDate = $this->input('schedule_date');
        $sessionSlot = $this->route('sessionSlot');
        $ignoreId = is_object($sessionSlot) ? $sessionSlot->getKey() : $sessionSlot;

        return [
            'live_account_id' => [
                'nullable', 'integer', 'exists:live_accounts,id',
                Rule::unique('live_schedule_assignments', 'live_account_id')
                    ->ignore($ignoreId)
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
            'platform_account_id' => ['required', 'exists:platform_accounts,id'],
            'time_slot_id' => ['required', 'exists:live_time_slots,id'],
            // Optional bespoke time for this one assignment. When present it is
            // resolved into time_slot_id (see prepareForValidation); validated
            // here so a malformed time is rejected before any slot is created.
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time', 'after:start_time'],
            'live_host_id' => ['required', 'exists:users,id'],
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
            'live_host_id.required' => 'Choose which host is broadcasting this live.',
            'start_time.date_format' => 'Start time must be a valid time (HH:MM).',
            'end_time.date_format' => 'End time must be a valid time (HH:MM).',
            'end_time.after' => 'End time must be later than the start time.',
        ];
    }

    /**
     * Return the value only when it is a well-formed 24-hour time, else null —
     * so a malformed time never resolves into a created slot before validation.
     */
    private function validTimeOrNull(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null || ! preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * The bespoke start/end time only when both are valid 24-hour times and the
     * end is after the start; otherwise [null, null].
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function customTimeWindow(): array
    {
        $start = $this->validTimeOrNull($this->input('start_time'));
        $end = $this->validTimeOrNull($this->input('end_time'));

        if ($start === null || $end === null || $this->timeToMinutes($start) >= $this->timeToMinutes($end)) {
            return [null, null];
        }

        return [$start, $end];
    }

    private function timeToMinutes(string $hm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hm, 0, 5)));

        return ($h * 60) + $m;
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
