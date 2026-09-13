<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * @return array{0: ProductMedia, 1: ProductMedia}
 */
function makeProductWithImages(Product $product, bool $firstIsPrimary): array
{
    $img1 = ProductMedia::create([
        'product_id' => $product->id,
        'type' => 'image',
        'file_name' => 'a.jpg',
        'file_path' => 'products/'.$product->id.'/a.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1000,
        'is_primary' => $firstIsPrimary,
        'sort_order' => 0,
    ]);

    $img2 = ProductMedia::create([
        'product_id' => $product->id,
        'type' => 'image',
        'file_name' => 'b.jpg',
        'file_path' => 'products/'.$product->id.'/b.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1000,
        'is_primary' => false,
        'sort_order' => 1,
    ]);

    return [$img1, $img2];
}

it('keeps the primary image after updating a product', function () {
    $product = Product::factory()->create(['type' => 'simple']);
    [$img1] = makeProductWithImages($product, firstIsPrimary: true);

    Volt::test('admin.products.product-edit', ['product' => $product])
        ->call('save')
        ->assertHasNoErrors();

    // Exactly one row stays primary, and it's still the original primary image.
    expect(ProductMedia::where('product_id', $product->id)->where('is_primary', true)->count())->toBe(1);
    expect($img1->fresh()->is_primary)->toBeTrue();

    $fresh = $product->fresh();
    expect($fresh->primaryImage)->not->toBeNull();
    expect($fresh->primaryImage->id)->toBe($img1->id);
});

it('self-heals a product that has no primary image flagged', function () {
    $product = Product::factory()->create(['type' => 'simple']);
    [$img1] = makeProductWithImages($product, firstIsPrimary: false);

    expect($product->fresh()->primaryImage)->toBeNull();

    Volt::test('admin.products.product-edit', ['product' => $product])
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $product->fresh();
    expect($fresh->primaryImage)->not->toBeNull();
    expect($fresh->primaryImage->id)->toBe($img1->id);
});

it('repairs products with a missing primary image via the artisan command', function () {
    $broken = Product::factory()->create();
    [$brokenImg1] = makeProductWithImages($broken, firstIsPrimary: false);

    $healthy = Product::factory()->create();
    [$healthyImg1] = makeProductWithImages($healthy, firstIsPrimary: true);

    expect($broken->fresh()->primaryImage)->toBeNull();

    $this->artisan('products:repair-primary-images')->assertSuccessful();

    // The broken product gets its first image flagged primary.
    expect($broken->fresh()->primaryImage?->id)->toBe($brokenImg1->id);

    // The healthy product is left untouched — still exactly one primary.
    expect(ProductMedia::where('product_id', $healthy->id)->where('is_primary', true)->count())->toBe(1);
    expect($healthyImg1->fresh()->is_primary)->toBeTrue();
});
