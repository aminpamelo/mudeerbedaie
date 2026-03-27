<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxProfileRequest extends FormRequest
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
            'tax_number' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['required', 'in:single,married_spouse_not_working,married_spouse_working'],
            'num_children' => ['required', 'integer', 'min:0', 'max:20'],
            'num_children_studying' => ['required', 'integer', 'min:0', 'lte:num_children'],
            'disabled_individual' => ['boolean'],
            'disabled_spouse' => ['boolean'],
            'is_pcb_manual' => ['boolean'],
            'manual_pcb_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marital_status.required' => 'Marital status is required.',
            'marital_status.in' => 'Invalid marital status selected.',
            'num_children.required' => 'Number of children is required.',
            'num_children.max' => 'Number of children cannot exceed 20.',
            'num_children_studying.lte' => 'Children studying cannot exceed total children.',
            'manual_pcb_amount.min' => 'PCB amount cannot be negative.',
        ];
    }
}
