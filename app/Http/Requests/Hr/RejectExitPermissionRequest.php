<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class RejectExitPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.min' => 'Please provide a reason with at least 5 characters.',
        ];
    }
}
