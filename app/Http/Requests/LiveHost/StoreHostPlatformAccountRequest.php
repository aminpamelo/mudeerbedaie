<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

class StoreHostPlatformAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_primary' => $this->toBool($this->input('is_primary'), false),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'creator_handle' => ['nullable', 'string', 'max:191'],
            'creator_platform_user_id' => ['nullable', 'string', 'max:191'],
            'is_primary' => ['boolean'],
        ];
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
