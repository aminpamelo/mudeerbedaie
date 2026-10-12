<?php

namespace App\Http\Requests\Funnel\FacebookAds;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdStatusRequest extends FormRequest
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
            'object_type' => ['required', 'in:campaign,adset,ad'],
            'object_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'object_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:ACTIVE,PAUSED'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'object_id.regex' => 'Invalid Facebook object id.',
            'status.in' => 'Status must be ACTIVE or PAUSED.',
        ];
    }
}
