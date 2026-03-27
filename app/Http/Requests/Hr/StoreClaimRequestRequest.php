<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'claim_type_id' => ['required', 'exists:claim_types,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'claim_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'claim_type_id.exists' => 'The selected claim type does not exist.',
            'amount.min' => 'The claim amount must be at least RM 0.01.',
            'receipt.max' => 'The receipt file must not exceed 5MB.',
            'receipt.mimes' => 'The receipt must be a PDF, JPG, JPEG, or PNG file.',
        ];
    }
}
