<?php

namespace App\Http\Requests\Funnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFunnelStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'type' => [
                'required',
                Rule::in(['landing', 'sales', 'checkout', 'upsell', 'downsell', 'thankyou', 'optin']),
            ],
            'settings' => ['nullable', 'array'],
            'settings.show_progress' => ['nullable', 'boolean'],
            'settings.exit_popup' => ['nullable', 'boolean'],
            'settings.timer_enabled' => ['nullable', 'boolean'],
            'settings.timer_duration' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Step name is required.',
            'name.max' => 'Step name cannot exceed 255 characters.',
            'type.required' => 'Step type is required.',
            'type.in' => 'Invalid step type selected.',
        ];
    }
}
