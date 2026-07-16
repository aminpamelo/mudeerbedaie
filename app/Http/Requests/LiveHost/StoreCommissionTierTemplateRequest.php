<?php

namespace App\Http\Requests\LiveHost;

use App\Http\Requests\LiveHost\Concerns\ValidatesCommissionTierShape;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or update a reusable master commission tier template. The tier ladder
 * is validated with the exact same structural rules a host schedule uses, so a
 * template can always be applied cleanly. Platform-agnostic — a template holds
 * only the ladder; the platform is chosen when it is applied to a host.
 */
class StoreCommissionTierTemplateRequest extends FormRequest
{
    use ValidatesCommissionTierShape;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.tier_number' => ['required', 'integer', 'min:1'],
            'tiers.*.min_gmv_myr' => ['required', 'numeric', 'min:0'],
            'tiers.*.max_gmv_myr' => ['nullable', 'numeric', 'min:0'],
            'tiers.*.internal_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tiers.*.l1_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tiers.*.l2_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tiers = data_get($validator->getData(), 'tiers');

            if (! is_array($tiers) || count($tiers) === 0) {
                return;
            }

            $this->applyTierShapeValidation($tiers, $validator);
        });
    }
}
