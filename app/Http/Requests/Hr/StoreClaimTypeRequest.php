<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimTypeRequest extends FormRequest
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
        $uniqueRule = 'unique:claim_types,code';

        if ($this->route('type')) {
            $uniqueRule .= ','.$this->route('type')->id;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', $uniqueRule],
            'description' => ['nullable', 'string'],
            'monthly_limit' => ['nullable', 'numeric', 'min:0'],
            'yearly_limit' => ['nullable', 'numeric', 'min:0'],
            'requires_receipt' => ['required', 'boolean'],
            'is_active' => ['boolean'],
            'is_mileage_type' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This claim type code is already in use.',
            'code.max' => 'Claim type code must not exceed 20 characters.',
        ];
    }
}
