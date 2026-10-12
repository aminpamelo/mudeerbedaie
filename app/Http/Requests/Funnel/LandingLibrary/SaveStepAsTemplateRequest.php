<?php

namespace App\Http\Requests\Funnel\LandingLibrary;

use App\Models\LandingPageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStepAsTemplateRequest extends FormRequest
{
    /**
     * Funnel ownership is enforced by the funnel.owner route middleware.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'employee', 'fighter'], true);
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
