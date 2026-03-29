<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisciplinaryActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'in:verbal_warning,first_written,second_written,show_cause,suspension,termination'],
            'reason' => ['required', 'string'],
            'incident_date' => ['required', 'date'],
            'issued_date' => ['nullable', 'date'],
            'response_required' => ['boolean'],
            'response_deadline' => ['nullable', 'date', 'after:today'],
            'previous_action_id' => ['nullable', 'exists:disciplinary_actions,id'],
        ];
    }
}
