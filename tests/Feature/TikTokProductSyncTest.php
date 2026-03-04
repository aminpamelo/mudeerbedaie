<?php

use App\Models\PendingPlatformProduct;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Services\TikTok\MatchResult;
use App\Services\TikTok\ProductMatchingService;
use App\Services\TikTok\TikTokClientFactory;
use App\Services\TikTok\TikTokProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->platform = Platform::factory()->create([
        'name' => 'TikTok Shop',
        'slug' => 'tiktok-shop',
        'is_active' => true,
    ]);

    $this->account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'name' => 'Test TikTok Account',
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-001',
    ]);
});

function makeTikTokProduct(string $id, string $title, string $sellerSku = '', ?string $skuId = null): array
{
    return [
        'id' => $id,
        'title' => $title,
        'status' => 'ACTIVATE',
        'create_time' => time(),
        'update_time' => time(),
        'sales_regions' => ['MY'],
        'skus' => [
            [
                'id' => $skuId ?? $id.'_sku',
                'seller_sku' => $sellerSku,
                'inventory' => [['quantity' => 100, 'warehouse_id' => 'wh1']],
                'price' => ['currency' => 'MYR', 'tax_exclusive_price' => '50'],
            ],
        ],
    ];
}

test('new product with empty seller_sku is not falsely detected as already linked', function () {
    // Create an existing mapping with a different product
    PlatformSkuMapping::create([
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'product_id' => $this->product->id,
        'platform_sku' => 'existing_product_sku_id',
        'platform_product_name' => 'Existing Product',
        'is_active' => true,
        'mapping_metadata' => [
            'platform_product_id' => 'existing_product_123',
            'auto_linked' => true,
        ],
    ]);

    $clientFactory = $this->mock(TikTokClientFactory::class);
    $matchingService = $this->mock(ProductMatchingService::class);
    $matchingService->shouldReceive('findMatch')->andReturn(null);

    $syncService = new TikTokProductSyncService($clientFactory, $matchingService);

    // Process a NEW product with empty seller_sku
    $newProduct = makeTikTokProduct('new_product_456', 'Brand New Product', '');

    $result = $syncService->processProduct($this->account, $newProduct);

    // Should be queued for review, NOT already_linked
    expect($result)->toBe('queued');

    // Verify it was queued as pending
    expect(PendingPlatformProduct::where('platform_product_id', 'new_product_456')->exists())->toBeTrue();
});

test('product with matching platform_product_id is correctly detected as already linked', function () {
    PlatformSkuMapping::create([
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'product_id' => $this->product->id,
        'platform_sku' => 'product_123_sku',
        'platform_product_name' => 'Existing Product',
        'is_active' => true,
        'mapping_metadata' => [
            'platform_product_id' => 'product_123',
            'auto_linked' => true,
        ],
    ]);

    $clientFactory = $this->mock(TikTokClientFactory::class);
    $matchingService = $this->mock(ProductMatchingService::class);

    $syncService = new TikTokProductSyncService($clientFactory, $matchingService);

    $existingProduct = makeTikTokProduct('product_123', 'Existing Product', '');

    $result = $syncService->processProduct($this->account, $existingProduct);

    expect($result)->toBe('already_linked');
});

test('product with matching seller_sku is correctly detected as already linked', function () {
    PlatformSkuMapping::create([
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'product_id' => $this->product->id,
        'platform_sku' => 'MY-SKU-001',
        'platform_product_name' => 'Existing Product',
        'is_active' => true,
        'mapping_metadata' => [
            'platform_product_id' => 'old_product_id',
        ],
    ]);

    $clientFactory = $this->mock(TikTokClientFactory::class);
    $matchingService = $this->mock(ProductMatchingService::class);

    $syncService = new TikTokProductSyncService($clientFactory, $matchingService);

    // Different product ID but same seller_sku
    $product = makeTikTokProduct('different_product_id', 'Product with SKU', 'MY-SKU-001');

    $result = $syncService->processProduct($this->account, $product);

    expect($result)->toBe('already_linked');
});

test('autoLinkProduct uses SKU ID as platform_sku when seller_sku is empty', function () {
    $clientFactory = $this->mock(TikTokClientFactory::class);
    $matchingService = $this->mock(ProductMatchingService::class);

    $match = new MatchResult(
        product: $this->product,
        variant: null,
        package: null,
        confidence: 100,
        matchReason: 'sku_match',
    );

    $matchingService->shouldReceive('findMatch')->andReturn($match);
    $matchingService->shouldReceive('shouldAutoLink')->andReturn(true);

    $syncService = new TikTokProductSyncService($clientFactory, $matchingService);

    $product = makeTikTokProduct('prod_789', 'Auto Link Product', '', 'sku_id_789');

    $result = $syncService->processProduct($this->account, $product);

    expect($result)->toBe('auto_linked');

    // Verify the mapping was created with the SKU ID, not empty string
    $mapping = PlatformSkuMapping::where('platform_account_id', $this->account->id)
        ->where('platform_sku', 'sku_id_789')
        ->first();

    expect($mapping)->not->toBeNull();
    expect($mapping->platform_sku)->toBe('sku_id_789');
    expect($mapping->mapping_metadata['platform_product_id'])->toBe('prod_789');
});
