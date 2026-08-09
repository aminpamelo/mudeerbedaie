<?php

namespace App\Http\Requests\Vault;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:vault_categories,id'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => trim((string) $this->input('url')) ?: null,
            'username' => trim((string) $this->input('username')) ?: null,
            'category_id' => $this->input('category_id') ?: null,
        ]);
    }
}
