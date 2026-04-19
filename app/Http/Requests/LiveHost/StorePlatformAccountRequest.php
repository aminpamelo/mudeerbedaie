<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'account_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('platform_accounts', 'account_id')
                    ->where(fn ($q) => $q->where('platform_id', $this->input('platform_id'))),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'currency' => ['nullable', 'string', 'max:3'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.unique' => 'Another account for this platform already uses that account ID.',
        ];
    }
}
