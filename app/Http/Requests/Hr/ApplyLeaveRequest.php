<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class ApplyLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['boolean'],
            'half_day_period' => ['required_if:is_half_day,true', 'in:morning,afternoon'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'Leave start date must be today or in the future.',
            'end_date.after_or_equal' => 'Leave end date must be on or after the start date.',
            'half_day_period.required_if' => 'Please specify morning or afternoon for half-day leave.',
            'reason.min' => 'Please provide a reason (at least 5 characters).',
            'attachment.max' => 'Attachment must not exceed 5MB.',
            'attachment.mimes' => 'Attachment must be a PDF, JPG, or PNG file.',
        ];
    }
}
