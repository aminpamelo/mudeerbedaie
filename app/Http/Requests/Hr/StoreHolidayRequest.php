<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['required', 'in:public,company,state,replacement'],
            'states' => ['nullable', 'array'],
            'states.*' => ['string'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2050'],
            'is_recurring' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'states.required_if' => 'States are required for state-level holidays.',
            'year.min' => 'Year must be 2020 or later.',
            'year.max' => 'Year must be 2050 or earlier.',
        ];
    }
}
