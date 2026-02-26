<?php

namespace App\Http\Requests\Funnel;

use Illuminate\Foundation\Http\FormRequest;

class SaveStepContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.content' => ['present', 'array'],
            'content.root' => ['present', 'array'],
            'custom_css' => ['nullable', 'string', 'max:65535'],
            'custom_js' => ['nullable', 'string', 'max:65535'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Page content is required.',
            'content.array' => 'Invalid page content format.',
            'content.content.present' => 'Page content structure is invalid.',
            'content.root.present' => 'Page root structure is invalid.',
        ];
    }
}
