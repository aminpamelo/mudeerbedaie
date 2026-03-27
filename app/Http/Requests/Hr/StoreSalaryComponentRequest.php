<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryComponentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:salary_components,code'],
            'type' => ['required', 'in:earning,deduction'],
            'category' => ['required', 'in:basic,fixed_allowance,variable_allowance,fixed_deduction,variable_deduction'],
            'is_taxable' => ['boolean'],
            'is_epf_applicable' => ['boolean'],
            'is_socso_applicable' => ['boolean'],
            'is_eis_applicable' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Salary component name is required.',
            'code.required' => 'Salary component code is required.',
            'code.unique' => 'This salary component code already exists.',
            'code.max' => 'Code must not exceed 20 characters.',
            'type.required' => 'Component type is required.',
            'type.in' => 'Type must be earning or deduction.',
            'category.required' => 'Component category is required.',
            'category.in' => 'Invalid category selected.',
        ];
    }
}
