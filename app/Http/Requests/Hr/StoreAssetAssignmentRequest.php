<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetAssignmentRequest extends FormRequest
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
            'asset_id' => ['required', 'exists:assets,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'assigned_by' => ['required', 'exists:employees,id'],
            'assigned_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:assigned_date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_id.exists' => 'The selected asset does not exist.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'expected_return_date.after_or_equal' => 'The expected return date must be on or after the assigned date.',
        ];
    }
}
