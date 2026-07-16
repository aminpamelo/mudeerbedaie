<?php

namespace App\Http\Requests\LiveHost\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Shared structural validation for a commission tier ladder — the same shape
 * whether the tiers are being saved onto a host's schedule or into a reusable
 * master template. Enforces: contiguous tier numbers from 1, per-row max > min,
 * only the highest tier open-ended, and gap-free non-overlapping ranges.
 */
trait ValidatesCommissionTierShape
{
    /**
     * Run the full tier-shape rule chain, short-circuiting on the first failure
     * so the operator sees one clear message at a time.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     */
    protected function applyTierShapeValidation(array $tiers, Validator $validator): void
    {
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
