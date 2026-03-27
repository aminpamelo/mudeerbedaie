<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveTypeRequest extends FormRequest
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
        $uniqueRule = 'unique:leave_types,code';

        if ($this->route('leaveType')) {
            $uniqueRule .= ','.$this->route('leaveType')->id;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', $uniqueRule],
            'description' => ['nullable', 'string'],
            'is_paid' => ['required', 'boolean'],
            'is_attachment_required' => ['required', 'boolean'],
            'gender_restriction' => ['nullable', 'in:male,female'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This leave type code is already in use.',
            'code.max' => 'Leave type code must not exceed 10 characters.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #3B82F6).',
        ];
    }
}
