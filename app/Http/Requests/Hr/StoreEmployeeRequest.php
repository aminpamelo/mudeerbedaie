<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'ic_number' => ['required', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'religion' => ['required', 'in:islam,christian,buddhist,hindu,sikh,other'],
            'race' => ['required', 'in:malay,chinese,indian,other'],
            'marital_status' => ['required', 'in:single,married,divorced,widowed'],
            'phone' => ['required', 'string'],
            'personal_email' => ['required', 'email'],
            'address_line_1' => ['required', 'string'],
            'address_line_2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'state' => ['required', 'string'],
            'postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'join_date' => ['required', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after:join_date'],
            'bank_name' => ['required', 'string'],
            'bank_account_number' => ['required', 'string'],
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
