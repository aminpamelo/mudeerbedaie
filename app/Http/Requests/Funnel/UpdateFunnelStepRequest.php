<?php

namespace App\Http\Requests\Funnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFunnelStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'type' => [
                'sometimes',
                Rule::in(['landing', 'sales', 'checkout', 'upsell', 'downsell', 'thankyou', 'optin']),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'next_step_id' => ['nullable', 'exists:funnel_steps,id'],
            'decline_step_id' => ['nullable', 'exists:funnel_steps,id'],
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
            'name.max' => 'Step name cannot exceed 255 characters.',
            'type.in' => 'Invalid step type selected.',
            'next_step_id.exists' => 'Selected next step does not exist.',
            'decline_step_id.exists' => 'Selected decline step does not exist.',
        ];
    }
}
