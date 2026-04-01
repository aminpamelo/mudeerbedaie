<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreExitPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'exit_date' => ['required', 'date', 'after_or_equal:today'],
            'exit_time' => ['required', 'date_format:H:i'],
            'return_time' => ['required', 'date_format:H:i', 'after:exit_time'],
            'errand_type' => ['required', 'in:company,personal'],
            'purpose' => ['required', 'string', 'min:10', 'max:1000'],
            'addressed_to' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exit_date.after_or_equal' => 'Exit date must be today or in the future.',
            'return_time.after' => 'Return time must be after exit time.',
            'purpose.min' => 'Please provide more detail (at least 10 characters).',
        ];
    }
}
