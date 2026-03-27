<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:fixed,flexible,shift'],
            'start_time' => ['required_if:type,fixed', 'required_if:type,shift', 'date_format:H:i'],
            'end_time' => ['required_if:type,fixed', 'required_if:type,shift', 'date_format:H:i', 'after:start_time'],
            'break_duration_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'min_hours_per_day' => ['required_if:type,flexible', 'numeric', 'min:1', 'max:24'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_time.required_if' => 'Start time is required for fixed and shift schedules.',
            'end_time.required_if' => 'End time is required for fixed and shift schedules.',
            'end_time.after' => 'End time must be after start time.',
            'min_hours_per_day.required_if' => 'Minimum hours per day is required for flexible schedules.',
            'working_days.min' => 'At least one working day must be selected.',
        ];
    }
}
