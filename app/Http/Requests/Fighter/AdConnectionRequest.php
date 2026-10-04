<?php

namespace App\Http\Requests\Fighter;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit one of the fighter's own Facebook Business Manager
 * connections. The token is required on create and optional on edit (blank
 * keeps the stored token). Ownership is enforced by the controller.
 */
class AdConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isFighter() || $user->isAdmin());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'business_manager_id' => ['required', 'string', 'max:50', 'regex:/^[\d\s]+$/'],
            'access_token' => [$creating ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give this Business Manager a name.',
            'business_manager_id.required' => 'Enter your Business Manager ID.',
            'business_manager_id.regex' => 'The Business Manager ID should contain digits only.',
            'access_token.required' => 'Paste your System User access token.',
        ];
    }
}
