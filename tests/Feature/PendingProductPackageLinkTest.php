<?php

declare(strict_types=1);

use App\Models\Package;
use App\Models\PendingPlatformProduct;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Services\TikTok\MatchResult;
use App\Services\TikTok\ProductMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- PendingPlatformProduct::linkToPackage ---

test('linkToPackage creates a platform sku mapping with package_id', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-12345',
        'platform_sku' => 'SKU-TEST-001',
        'name' => 'Test TikTok Product',
        'status' => 'pending',
    ]);

    $mapping = $pending->linkToPackage($package, 1);

    expect($mapping)->toBeInstanceOf(PlatformSkuMapping::class);
    expect($mapping->package_id)->toBe($package->id);
    expect($mapping->product_id)->toBeNull();
    expect($mapping->product_variant_id)->toBeNull();
    expect($mapping->platform_sku)->toBe('SKU-TEST-001');
    expect($mapping->is_active)->toBeTrue();

    $pending->refresh();
    expect($pending->status)->toBe('linked');
    expect($pending->reviewed_by)->toBe(1);
    expect($pending->reviewed_at)->not->toBeNull();
});

// --- PendingPlatformProduct::linkVariantSku ---

test('linkVariantSku creates mapping for a variant sku to a product', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $product = Product::factory()->create();

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-VARIANT-001',
        'platform_sku' => 'SKU-MAIN',
        'name' => 'TikTok Product With Variants',
        'status' => 'pending',
        'variants' => [
            ['sku' => 'VAR-RED', 'name' => 'Red', 'price' => 10.00, 'quantity' => 5, 'attributes' => []],
            ['sku' => 'VAR-BLUE', 'name' => 'Blue', 'price' => 10.00, 'quantity' => 3, 'attributes' => []],
        ],
    ]);

    $mapping = $pending->linkVariantSku('VAR-RED', $product, null, null, 1);

    expect($mapping)->toBeInstanceOf(PlatformSkuMapping::class);
    expect($mapping->platform_sku)->toBe('VAR-RED');
    expect($mapping->product_id)->toBe($product->id);
    expect($mapping->package_id)->toBeNull();

    // Pending product should still be pending (not all variants mapped)
    $pending->refresh();
    expect($pending->status)->toBe('pending');
});

test('linkVariantSku creates mapping for a variant sku to a package', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-VARIANT-002',
        'platform_sku' => 'SKU-MAIN-2',
        'name' => 'TikTok Package Variant Product',
        'status' => 'pending',
        'variants' => [
            ['sku' => 'PKG-VAR-1', 'name' => 'Option A', 'price' => 20.00, 'quantity' => 10, 'attributes' => []],
        ],
    ]);

    $mapping = $pending->linkVariantSku('PKG-VAR-1', null, null, $package, 1);

    expect($mapping->platform_sku)->toBe('PKG-VAR-1');
    expect($mapping->package_id)->toBe($package->id);
    expect($mapping->product_id)->toBeNull();

    // All variants mapped, pending product should be linked
    $pending->refresh();
    expect($pending->status)->toBe('linked');
});

// --- PlatformSkuMapping helpers ---

test('isPackageMapping returns true for package mappings', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();

    $mapping = PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'TEST-SKU',
        'package_id' => $package->id,
        'is_active' => true,
    ]);

    expect($mapping->isPackageMapping())->toBeTrue();
    expect($mapping->isProductMapping())->toBeFalse();
});

test('isProductMapping returns true for product mappings', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $product = Product::factory()->create();

    $mapping = PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'PROD-SKU',
        'product_id' => $product->id,
        'is_active' => true,
    ]);

    expect($mapping->isProductMapping())->toBeTrue();
    expect($mapping->isPackageMapping())->toBeFalse();
});

test('getTarget returns package for package mapping', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();

    $mapping = PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'PKG-TARGET',
        'package_id' => $package->id,
        'is_active' => true,
    ]);

    $target = $mapping->getTarget();
    expect($target)->toBeInstanceOf(Package::class);
    expect($target->id)->toBe($package->id);
});

// --- PendingPlatformProduct suggestion helpers ---

test('hasSuggestion returns true for package suggestions', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();

    $pending = PendingPlatformProduct::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_product_id' => 'TT-SUG-001',
        'name' => 'Suggested Package Product',
        'status' => 'pending',
        'suggested_package_id' => $package->id,
        'match_confidence' => 85.5,
        'match_reason' => 'Package name similarity (86%)',
    ]);

    expect($pending->hasSuggestion())->toBeTrue();
    expect($pending->hasPackageSuggestion())->toBeTrue();
    expect($pending->hasProductSuggestion())->toBeFalse();
});

// --- MatchResult ---

test('MatchResult isPackageMatch works correctly', function () {
    $package = Package::factory()->create();

    $result = new MatchResult(
        package: $package,
        confidence: 85.0,
        matchReason: 'Package name similarity (85%)',
    );

    expect($result->isPackageMatch())->toBeTrue();
    expect($result->package->id)->toBe($package->id);
    expect($result->product)->toBeNull();

    $array = $result->toArray();
    expect($array['package_id'])->toBe($package->id);
    expect($array['product_id'])->toBeNull();
    expect($array['type'])->toBe('package');
});

test('MatchResult product match returns type product', function () {
    $product = Product::factory()->create();

    $result = new MatchResult(
        product: $product,
        confidence: 100.0,
        matchReason: 'SKU exact match',
        autoLink: true,
    );

    expect($result->isPackageMatch())->toBeFalse();
    expect($result->toArray()['type'])->toBe('product');
});

// --- ProductMatchingService ---

test('matchByPackageNameSimilarity finds matching packages', function () {
    $package = Package::factory()->create([
        'name' => 'Buku Latihan Mengaji Set Lengkap',
        'status' => 'active',
    ]);

    $service = app(ProductMatchingService::class);

    $result = $service->matchByPackageNameSimilarity([
        'title' => 'Buku Latihan Mengaji Set Lengkap',
    ]);

    expect($result)->not->toBeNull();
    expect($result->isPackageMatch())->toBeTrue();
    expect($result->package->id)->toBe($package->id);
    expect($result->confidence)->toBeGreaterThanOrEqual(80);
});

test('matchByExistingMapping returns package match for package mappings', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();

    PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'EXISTING-PKG-SKU',
        'package_id' => $package->id,
        'is_active' => true,
    ]);

    $service = app(ProductMatchingService::class);

    $result = $service->matchByExistingMapping([
        'id' => 'TT-123',
        'skus' => [['seller_sku' => 'EXISTING-PKG-SKU']],
    ], $account);

    expect($result)->not->toBeNull();
    expect($result->isPackageMatch())->toBeTrue();
    expect($result->package->id)->toBe($package->id);
    expect($result->confidence)->toBe(100.0);
    expect($result->autoLink)->toBeTrue();
});

// --- findMapping with package ---

test('findMapping loads package relationship', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();

    PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'FIND-PKG-SKU',
        'package_id' => $package->id,
        'is_active' => true,
    ]);

    $found = PlatformSkuMapping::findMapping($platform->id, $account->id, 'FIND-PKG-SKU');

    expect($found)->not->toBeNull();
    expect($found->relationLoaded('package'))->toBeTrue();
    expect($found->package->id)->toBe($package->id);
});
