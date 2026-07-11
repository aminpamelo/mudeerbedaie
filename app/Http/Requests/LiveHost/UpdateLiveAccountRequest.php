<?php

namespace App\Http\Requests\LiveHost;

use App\Models\LiveAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLiveAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin', 'livehost_assistant'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'creator_user_id' => $this->nullableString($this->input('creator_user_id')),
            'nickname' => $this->nullableString($this->input('nickname')),
            'display_name' => $this->nullableString($this->input('display_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->route('liveAccount');
        $ignoreId = is_object($account) ? $account->getKey() : $account;

        return [
            'creator_user_id' => [
                'nullable', 'string', 'max:255',
                Rule::unique('live_accounts', 'creator_user_id')->ignore($ignoreId),
            ],
            'nickname' => ['nullable', 'string', 'max:255', 'required_without:creator_user_id'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'needs_review' => ['boolean'],
            'account_type' => ['nullable', Rule::in(LiveAccount::ACCOUNT_TYPES)],
            'shop_ids' => ['array'],
            'shop_ids.*' => ['integer', 'exists:platform_accounts,id'],
            'primary_shop_id' => ['nullable', 'integer', 'exists:platform_accounts,id'],
            'host_ids' => ['array'],
            'host_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required_without' => 'Provide a nickname or a Creator ID.',
            'creator_user_id.unique' => 'A live account with this Creator ID already exists.',
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return trim((string) $value);
    }
}
