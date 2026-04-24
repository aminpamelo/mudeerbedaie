<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommissionTierScheduleRequest extends FormRequest
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
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'effective_from' => ['required', 'date'],
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

            $this->validateRowLevelMaxGreaterThanMin($tiers, $validator);

            if ($validator->errors()->has('tiers')) {
                return;
            }

            $this->validateTierNumbersAreContiguous($tiers, $validator);

            if ($validator->errors()->has('tiers')) {
                return;
            }

            $sortedTiers = $this->sortByMinGmv($tiers);

            $this->validateOnlyHighestTierHasNullMax($sortedTiers, $validator);

            if ($validator->errors()->has('tiers')) {
                return;
            }

            $this->validateRangesAreContiguousAndNonOverlapping($sortedTiers, $validator);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    protected function validateRowLevelMaxGreaterThanMin(array $tiers, Validator $validator): void
    {
        foreach ($tiers as $tier) {
            $min = $tier['min_gmv_myr'] ?? null;
            $max = $tier['max_gmv_myr'] ?? null;

            if ($min === null || $max === null) {
                continue;
            }

            if ((float) $max <= (float) $min) {
                $validator->errors()->add(
                    'tiers',
                    'Each tier max_gmv_myr must be greater than its min_gmv_myr.',
                );

                return;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    protected function validateTierNumbersAreContiguous(array $tiers, Validator $validator): void
    {
        $tierNumbers = array_map(static fn (array $tier): int => (int) ($tier['tier_number'] ?? 0), $tiers);
        sort($tierNumbers);

        $expected = range(1, count($tierNumbers));

        if ($tierNumbers !== $expected) {
            $validator->errors()->add(
                'tiers',
                'Tier numbers must be contiguous starting at 1 (e.g. 1, 2, 3).',
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $sortedTiers
     */
    protected function validateOnlyHighestTierHasNullMax(array $sortedTiers, Validator $validator): void
    {
        $lastIndex = count($sortedTiers) - 1;

        foreach ($sortedTiers as $index => $tier) {
            $max = $tier['max_gmv_myr'] ?? null;

            if ($index !== $lastIndex && $max === null) {
                $validator->errors()->add(
                    'tiers',
                    'Only the highest tier may have an open-ended max_gmv_myr (null).',
                );

                return;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $sortedTiers
     */
    protected function validateRangesAreContiguousAndNonOverlapping(array $sortedTiers, Validator $validator): void
    {
        for ($i = 0; $i < count($sortedTiers) - 1; $i++) {
            $currentMax = $sortedTiers[$i]['max_gmv_myr'] ?? null;
            $nextMin = $sortedTiers[$i + 1]['min_gmv_myr'] ?? null;

            if ($currentMax === null || $nextMin === null) {
                $validator->errors()->add(
                    'tiers',
                    'Tier ranges must be contiguous with no gaps.',
                );

                return;
            }

            if ((float) $currentMax !== (float) $nextMin) {
                $validator->errors()->add(
                    'tiers',
                    'Tier ranges must be contiguous and non-overlapping: each tier\'s max_gmv_myr must equal the next tier\'s min_gmv_myr.',
                );

                return;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array<string, mixed>>
     */
    protected function sortByMinGmv(array $tiers): array
    {
        usort(
            $tiers,
            static fn (array $a, array $b): int => (float) ($a['min_gmv_myr'] ?? 0) <=> (float) ($b['min_gmv_myr'] ?? 0),
        );

        return $tiers;
    }
}
