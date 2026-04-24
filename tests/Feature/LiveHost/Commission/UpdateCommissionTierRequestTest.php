<?php

use App\Http\Requests\LiveHost\UpdateCommissionTierRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateTierUpdate(array $input): \Illuminate\Validation\Validator
{
    $request = new UpdateCommissionTierRequest;
    $validator = Validator::make($input, $request->rules());
    $request->withValidator($validator);
    $validator->passes();

    return $validator;
}

it('accepts a valid single-row payload', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 60000,
        'internal_percent' => 7,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('accepts nullable max_gmv_myr', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 150000,
        'max_gmv_myr' => null,
        'internal_percent' => 10,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('rejects internal_percent > 100', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 60000,
        'internal_percent' => 150,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('internal_percent'))->toBeTrue();
});

it('rejects l1_percent > 100', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 60000,
        'internal_percent' => 5,
        'l1_percent' => 150,
        'l2_percent' => 2,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('l1_percent'))->toBeTrue();
});

it('rejects l2_percent > 100', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 60000,
        'internal_percent' => 5,
        'l1_percent' => 1,
        'l2_percent' => 150,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('l2_percent'))->toBeTrue();
});

it('rejects negative percents', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 60000,
        'internal_percent' => -1,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('internal_percent'))->toBeTrue();
});

it('rejects max_gmv_myr less than min_gmv_myr when both present', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 60000,
        'max_gmv_myr' => 30000,
        'internal_percent' => 5,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('max_gmv_myr'))->toBeTrue();
});

it('rejects max_gmv_myr equal to min_gmv_myr when both present', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 30000,
        'internal_percent' => 5,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('max_gmv_myr'))->toBeTrue();
});

it('rejects missing min_gmv_myr', function () {
    $validator = validateTierUpdate([
        'max_gmv_myr' => 60000,
        'internal_percent' => 5,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('min_gmv_myr'))->toBeTrue();
});

it('rejects negative min_gmv_myr', function () {
    $validator = validateTierUpdate([
        'min_gmv_myr' => -1,
        'max_gmv_myr' => 60000,
        'internal_percent' => 5,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('min_gmv_myr'))->toBeTrue();
});
