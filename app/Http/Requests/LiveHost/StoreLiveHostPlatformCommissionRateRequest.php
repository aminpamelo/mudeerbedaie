<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiveHostPlatformCommissionRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'commission_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
