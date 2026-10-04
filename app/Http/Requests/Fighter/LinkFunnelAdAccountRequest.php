<?php

namespace App\Http\Requests\Fighter;

use App\Models\FacebookAdAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Point one of the fighter's funnels at an ad account (its Ads Source) — only
 * accounts under the fighter's own Business Managers are accepted.
 */
class LinkFunnelAdAccountRequest extends FormRequest
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
        $ownedIds = FacebookAdAccount::query()->ownedBy((int) $this->user()->id)->pluck('id')->all();

        return [
            'facebook_ad_account_id' => ['nullable', 'integer', Rule::in($ownedIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facebook_ad_account_id.in' => 'Choose an ad account from your own Business Manager.',
        ];
    }
}
