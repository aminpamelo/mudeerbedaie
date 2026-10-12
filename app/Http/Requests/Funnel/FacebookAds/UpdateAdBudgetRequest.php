<?php

namespace App\Http\Requests\Funnel\FacebookAds;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'employee'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'object_type' => ['required', 'in:campaign,adset'],
            'object_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'object_name' => ['nullable', 'string', 'max:255'],
            'daily_budget' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'current_budget' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'object_id.regex' => 'Invalid Facebook object id.',
            'daily_budget.min' => 'Daily budget must be at least 1.',
        ];
    }
}
