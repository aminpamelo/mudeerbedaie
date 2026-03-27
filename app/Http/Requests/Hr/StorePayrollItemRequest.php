<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollItemRequest extends FormRequest
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
            'employee_id' => ['required', 'exists:employees,id'],
            'component_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:earning,deduction'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'component_name.required' => 'Component name is required.',
            'type.required' => 'Item type is required.',
            'type.in' => 'Type must be earning or deduction.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount cannot be negative.',
        ];
    }
}
