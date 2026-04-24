<?php

use App\Http\Requests\LiveHost\StoreCommissionTierScheduleRequest;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateTierSchedule(array $input): \Illuminate\Validation\Validator
{
    $request = new StoreCommissionTierScheduleRequest;
    $validator = Validator::make($input, $request->rules());
    $request->withValidator($validator);
    $validator->passes();

    return $validator;
}

function canonicalFiveTierSchedule(int $platformId, array $overrides = []): array
{
    return array_merge([
        'platform_id' => $platformId,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 15000, 'max_gmv_myr' => 30000, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 60000, 'internal_percent' => 7, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 3, 'min_gmv_myr' => 60000, 'max_gmv_myr' => 100000, 'internal_percent' => 8, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 4, 'min_gmv_myr' => 100000, 'max_gmv_myr' => 150000, 'internal_percent' => 9, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 5, 'min_gmv_myr' => 150000, 'max_gmv_myr' => null, 'internal_percent' => 10, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ], $overrides);
}

it('accepts a canonical 5-tier schedule with open-ended top', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule(canonicalFiveTierSchedule($platform->id));

    expect($validator->passes())->toBeTrue();
});

it('accepts a single-tier schedule with null max', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => null, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->passes())->toBeTrue();
});

it('rejects non-contiguous tier_numbers (1, 3)', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => 30000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 3, 'min_gmv_myr' => 30000, 'max_gmv_myr' => null, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
    expect($validator->errors()->first('tiers'))->toContain('contiguous');
});

it('rejects tier_numbers not starting at 1', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 2, 'min_gmv_myr' => 0, 'max_gmv_myr' => 30000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 3, 'min_gmv_myr' => 30000, 'max_gmv_myr' => null, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
});

it('rejects overlapping ranges', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => 35000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => null, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
});

it('rejects gaps between ranges', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => 25000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => null, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
});

it('rejects when a non-highest tier has null max', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => null, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 60000, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
});

it('rejects internal_percent > 100', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => null, 'internal_percent' => 150, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers.0.internal_percent'))->toBeTrue();
});

it('rejects l1_percent > 100', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => null, 'internal_percent' => 5, 'l1_percent' => 120, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers.0.l1_percent'))->toBeTrue();
});

it('rejects when tiers array is empty', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
});

it('rejects when tiers key is missing', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
});

it('rejects when platform_id is missing', function () {
    $validator = validateTierSchedule([
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => null, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('platform_id'))->toBeTrue();
});

it('rejects when effective_from is missing', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => null, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('effective_from'))->toBeTrue();
});

it('rejects when a row has max_gmv_myr equal to or less than min_gmv_myr', function () {
    $platform = Platform::factory()->create();

    $validator = validateTierSchedule([
        'platform_id' => $platform->id,
        'effective_from' => '2026-04-01',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 20000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => null, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tiers'))->toBeTrue();
});
