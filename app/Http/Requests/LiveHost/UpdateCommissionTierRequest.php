<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionTierRequest extends FormRequest
{
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
            'min_gmv_myr' => ['required', 'numeric', 'min:0'],
            'max_gmv_myr' => ['nullable', 'numeric', 'min:0'],
            'internal_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'l1_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'l2_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();
            $min = $data['min_gmv_myr'] ?? null;
            $max = $data['max_gmv_myr'] ?? null;

            if ($min === null || $max === null) {
                return;
            }

            if ((float) $max <= (float) $min) {
                $validator->errors()->add(
                    'max_gmv_myr',
                    'The max_gmv_myr must be greater than min_gmv_myr.',
                );
            }
        });
    }
}
