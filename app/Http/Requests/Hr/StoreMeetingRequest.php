<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'status' => ['nullable', 'in:draft,scheduled'],
            'meeting_series_id' => ['nullable', 'exists:meeting_series,id'],
            'note_taker_id' => ['nullable', 'exists:employees,id'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['exists:employees,id'],
            'agenda_items' => ['nullable', 'array'],
            'agenda_items.*.title' => ['required_with:agenda_items', 'string', 'max:255'],
            'agenda_items.*.description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'meeting_date.after_or_equal' => 'Meeting date must be today or in the future.',
            'end_time.after' => 'End time must be after start time.',
            'attendee_ids.*.exists' => 'One or more selected attendees do not exist.',
        ];
    }
}
