<?php

declare(strict_types=1);

use App\Models\PendingPlatformProduct;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- resolveVariantSku ---

test('resolveVariantSku returns sku when present', function () {
    $sku = PendingPlatformProduct::resolveVariantSku([
        'sku' => 'SELLER-SKU-001',
        'sku_id' => '17328881234',
    ]);

    expect($sku)->toBe('SELLER-SKU-001');
});

test('resolveVariantSku falls back to sku_id when sku is empty string', function () {
    $sku = PendingPlatformProduct::resolveVariantSku([
        'sku' => '',
        'sku_id' => '17328881234',
    ]);

    expect($sku)->toBe('17328881234');
});

test('resolveVariantSku falls back to sku_id when sku is null', function () {
    $sku = PendingPlatformProduct::resolveVariantSku([
        'sku' => null,
        'sku_id' => '17328881234',
    ]);

    expect($sku)->toBe('17328881234');
});

test('resolveVariantSku falls back to sku_id when sku key is missing', function () {
    $sku = PendingPlatformProduct::resolveVariantSku([
        'sku_id' => '17328881234',
    ]);

    expect($sku)->toBe('17328881234');
});

test('resolveVariantSku returns null when both sku and sku_id are missing', function () {
    $sku = PendingPlatformProduct::resolveVariantSku([
        'name' => 'Variant A',
        'price' => '10',
    ]);

    expect($sku)->toBeNull();
});

test('resolveVariantSku casts numeric sku to string', function () {
    $sku = PendingPlatformProduct::resolveVariantSku([
        'sku' => 17328881234,
    ]);

    expect($sku)->toBe('17328881234');
});

// --- resolveVariantName ---

test('resolveVariantName returns name field when present', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-NAME-TEST',
        'name' => 'Test Product',
        'status' => 'pending',
        'variants' => [
            ['sku' => 'V1', 'name' => 'Tahun 1', 'price' => '10', 'quantity' => 5, 'attributes' => []],
        ],
    ]);

    $name = $pending->resolveVariantName($pending->variants[0], 0);
    expect($name)->toBe('Tahun 1');
});

test('resolveVariantName falls back to attributes', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-ATTR-TEST',
        'name' => 'Test Product',
        'status' => 'pending',
        'variants' => [
            [
                'sku' => 'V1',
                'name' => null,
                'price' => '10',
                'quantity' => 5,
                'attributes' => [
                    ['name' => 'Color', 'value' => 'Red'],
                    ['name' => 'Size', 'value' => 'Large'],
                ],
            ],
        ],
    ]);

    $name = $pending->resolveVariantName($pending->variants[0], 0);
    expect($name)->toBe('Red / Large');
});

test('resolveVariantName extracts from raw_data sales_attributes', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-RAW-TEST',
        'name' => 'Test Product',
        'status' => 'pending',
        'variants' => [
            ['sku' => '17328881234', 'sku_id' => '17328881234', 'name' => null, 'price' => '10', 'quantity' => 5, 'attributes' => []],
        ],
        'raw_data' => [
            'skus' => [
                [
                    'id' => '17328881234',
                    'seller_sku' => '',
                    'sales_attributes' => [
                        ['name' => 'Variant', 'value_name' => 'Adab Tahun 1'],
                    ],
                    'price' => ['tax_exclusive_price' => '10'],
                ],
            ],
        ],
    ]);

    $name = $pending->resolveVariantName($pending->variants[0], 0);
    expect($name)->toBe('Adab Tahun 1');
});

test('resolveVariantName returns fallback index name when nothing available', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-FALLBACK-TEST',
        'name' => 'Test Product',
        'status' => 'pending',
        'variants' => [
            ['sku' => '17328881234', 'sku_id' => '17328881234', 'name' => null, 'price' => '10', 'quantity' => 5, 'attributes' => []],
        ],
    ]);

    $name = $pending->resolveVariantName($pending->variants[0], 2);
    expect($name)->toBe('Variant 3');
});
