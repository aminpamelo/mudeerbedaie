<?php

namespace App\Http\Requests\Funnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', Rule::in(['sales', 'lead', 'webinar', 'course'])],
            'template_id' => ['nullable', 'exists:funnel_templates,id'],
            'settings' => ['nullable', 'array'],
            'settings.meta_title' => ['nullable', 'string', 'max:255'],
            'settings.meta_description' => ['nullable', 'string', 'max:500'],
            'settings.favicon' => ['nullable', 'string', 'max:255'],
            'settings.custom_domain' => ['nullable', 'string', 'max:255'],
            'settings.tracking_codes' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Funnel name is required.',
            'name.max' => 'Funnel name cannot exceed 255 characters.',
            'type.in' => 'Invalid funnel type selected.',
            'template_id.exists' => 'Selected template does not exist.',
        ];
    }
}
