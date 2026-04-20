<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_primary' => filter_var($this->input('is_primary'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'live_host')),
            ],
            'platform_account_id' => [
                'required',
                'integer',
                Rule::exists('platform_accounts', 'id'),
                Rule::unique('live_host_platform_account', 'platform_account_id')
                    ->where(fn ($q) => $q->where('user_id', $this->input('user_id'))),
            ],
            'creator_handle' => ['nullable', 'string', 'max:191'],
            'creator_platform_user_id' => ['required', 'string', 'max:191'],
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform_account_id.unique' => 'This host is already linked to the selected platform account.',
            'creator_platform_user_id.required' => 'Creator ID is required to match TikTok reports.',
        ];
    }
}
