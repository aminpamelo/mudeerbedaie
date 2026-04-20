<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_primary')) {
            $this->merge([
                'is_primary' => filter_var($this->input('is_primary'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'creator_handle' => ['nullable', 'string', 'max:191'],
            'creator_platform_user_id' => ['required', 'string', 'max:191'],
            'is_primary' => ['boolean'],
        ];
    }
}
