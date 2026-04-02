<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
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
            'full_name' => ['sometimes', 'string', 'max:255'],
            'ic_number' => ['sometimes', 'nullable', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'in:male,female'],
            'religion' => ['nullable', 'in:islam,christian,buddhist,hindu,sikh,other'],
            'race' => ['nullable', 'in:malay,chinese,indian,other'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'personal_email' => ['sometimes', 'nullable', 'email'],
            'address_line_1' => ['sometimes', 'nullable', 'string'],
            'address_line_2' => ['nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string'],
            'state' => ['sometimes', 'nullable', 'string'],
            'postcode' => ['sometimes', 'nullable', 'string', 'regex:/^\d{5}$/'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'position_id' => ['sometimes', 'exists:positions,id'],
            'reports_to' => ['nullable', 'exists:employees,id'],
            'employment_type' => ['sometimes', 'nullable', 'array'],
            'employment_type.*' => ['string', 'in:full_time,part_time,contract,intern,internship,freelancer'],
            'join_date' => ['sometimes', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'confirmation_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date'],
            'resignation_date' => ['nullable', 'date'],
            'last_working_date' => ['nullable', 'date'],
            'bank_name' => ['sometimes', 'nullable', 'string'],
            'bank_account_number' => ['sometimes', 'nullable', 'string'],
            'epf_number' => ['nullable', 'string'],
            'socso_number' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ic_number.regex' => 'IC number must be in format XXXXXX-XX-XXXX.',
            'postcode.regex' => 'Postcode must be 5 digits.',
            'ic_number.unique' => 'An employee with this IC number already exists.',
        ];
    }
}
