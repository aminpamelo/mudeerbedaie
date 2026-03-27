<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveEntitlementRequest extends FormRequest
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
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern,all'],
            'min_service_months' => ['required', 'integer', 'min:0'],
            'max_service_months' => ['nullable', 'integer', 'gt:min_service_months'],
            'days_per_year' => ['required', 'numeric', 'min:0', 'max:365'],
            'is_prorated' => ['required', 'boolean'],
            'carry_forward_max' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'leave_type_id.exists' => 'The selected leave type does not exist.',
            'max_service_months.gt' => 'Maximum service months must be greater than minimum service months.',
            'days_per_year.max' => 'Days per year cannot exceed 365.',
        ];
    }
}
