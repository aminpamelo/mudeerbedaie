<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentApproverRequest extends FormRequest
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
            'department_id' => ['required', 'exists:departments,id'],
            'approver_employee_id' => ['required', 'exists:employees,id'],
            'approval_type' => ['required', 'in:overtime,leave,claims'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.exists' => 'The selected department does not exist.',
            'approver_employee_id.exists' => 'The selected employee does not exist.',
            'approval_type.in' => 'Approval type must be overtime, leave, or claims.',
        ];
    }
}
