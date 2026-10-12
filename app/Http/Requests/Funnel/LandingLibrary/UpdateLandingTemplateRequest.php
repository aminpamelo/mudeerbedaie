<?php

namespace App\Http\Requests\Funnel\LandingLibrary;

use App\Models\LandingPageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLandingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $template = $this->route('template');

        return in_array($user?->role, ['admin', 'employee'], true)
            || ($template instanceof LandingPageTemplate && (int) $template->created_by === (int) $user?->id);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', Rule::in(LandingPageTemplate::CATEGORIES)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the template a name.',
        ];
    }
}
