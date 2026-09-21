<?php

declare(strict_types=1);

use App\Models\Package;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function linkToShop(Product|Package $item, string $shopName = 'Test Shop', bool $active = true): PlatformSkuMapping
{
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'name' => $shopName,
    ]);

    return PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'product_id' => $item instanceof Product ? $item->id : null,
        'package_id' => $item instanceof Package ? $item->id : null,
        'platform_sku' => 'SKU-'.class_basename($item).'-'.$item->id,
        'platform_product_name' => $item->name,
        'is_active' => $active,
    ]);
}

/*
|--------------------------------------------------------------------------
| Trait behaviour (Product + Package)
|--------------------------------------------------------------------------
*/

it('reports a product as linked and exposes its shop accounts', function () {
    $product = Product::factory()->create();
    linkToShop($product, 'Kedai Satu');

    expect($product->isLinkedToShop())->toBeTrue()
        ->and($product->linkedShopAccounts()->pluck('name')->all())->toBe(['Kedai Satu']);
});

it('reports a package as linked and exposes its shop accounts', function () {
    $package = Package::factory()->create();
    linkToShop($package, 'Kedai Dua');

    expect($package->isLinkedToShop())->toBeTrue()
        ->and($package->linkedShopAccounts()->pluck('name')->all())->toBe(['Kedai Dua']);
});

it('counts a shop only once even with several active mappings to it', function () {
    $product = Product::factory()->create();
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id, 'name' => 'Same Shop']);

    foreach (['SKU-A', 'SKU-B'] as $sku) {
        PlatformSkuMapping::create([
            'platform_id' => $platform->id,
            'platform_account_id' => $account->id,
            'product_id' => $product->id,
            'platform_sku' => $sku,
            'is_active' => true,
        ]);
    }

    expect($product->linkedShopAccounts())->toHaveCount(1);
});

it('treats an inactive-only mapping as not linked', function () {
    $product = Product::factory()->create();
    linkToShop($product, active: false);

    expect($product->fresh()->isLinkedToShop())->toBeFalse();
});

it('scopes products and packages by shop link state', function () {
    $linkedProduct = Product::factory()->create();
    $unlinkedProduct = Product::factory()->create();
    linkToShop($linkedProduct);

    $linkedPackage = Package::factory()->create();
    $unlinkedPackage = Package::factory()->create();
    linkToShop($linkedPackage);

    expect(Product::linkedToShop()->pluck('id')->all())->toBe([$linkedProduct->id])
        ->and(Product::notLinkedToShop()->pluck('id')->all())->toBe([$unlinkedProduct->id])
        ->and(Package::linkedToShop()->pluck('id')->all())->toBe([$linkedPackage->id])
        ->and(Package::notLinkedToShop()->pluck('id')->all())->toBe([$unlinkedPackage->id]);
});

/*
|--------------------------------------------------------------------------
| Products list (Volt)
|--------------------------------------------------------------------------
*/

it('shows the shop link column with linked and unlinked states on the products list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $linked = Product::factory()->create(['name' => 'Linked Widget']);
    Product::factory()->create(['name' => 'Lonely Widget']);
    linkToShop($linked, 'Kedai Ilmu');

    Volt::actingAs($admin)->test('admin.products.product-list')
        ->assertSee('Shop')
        ->assertSee('1 shop')
        ->assertSee('Not linked');
});

it('filters the products list by shop link state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $linked = Product::factory()->create(['name' => 'Linked Widget']);
    $unlinked = Product::factory()->create(['name' => 'Lonely Widget']);
    linkToShop($linked);

    Volt::actingAs($admin)->test('admin.products.product-list')
        ->set('shopFilter', 'linked')
        ->assertSee('Linked Widget')
        ->assertDontSee('Lonely Widget')
        ->set('shopFilter', 'unlinked')
        ->assertSee('Lonely Widget')
        ->assertDontSee('Linked Widget');
});

it('renders the catalog toggle on the products list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Volt::actingAs($admin)->test('admin.products.product-list')
        ->assertSee('Products')
        ->assertSee('Packages');
});

/*
|--------------------------------------------------------------------------
| Packages list (Volt)
|--------------------------------------------------------------------------
*/

it('shows the shop link column with linked and unlinked states on the packages list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $linked = Package::factory()->create(['name' => 'Linked Bundle']);
    Package::factory()->create(['name' => 'Lonely Bundle']);
    linkToShop($linked, 'Kedai Ilmu');

    Volt::actingAs($admin)->test('admin.packages.index')
        ->assertSee('Shop')
        ->assertSee('1 shop')
        ->assertSee('Not linked');
});

it('filters the packages list by shop link state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $linked = Package::factory()->create(['name' => 'Linked Bundle']);
    $unlinked = Package::factory()->create(['name' => 'Lonely Bundle']);
    linkToShop($linked);

    Volt::actingAs($admin)->test('admin.packages.index')
        ->set('shopFilter', 'linked')
        ->assertSee('Linked Bundle')
        ->assertDontSee('Lonely Bundle')
        ->set('shopFilter', 'unlinked')
        ->assertSee('Lonely Bundle')
        ->assertDontSee('Linked Bundle');
});

it('renders the catalog toggle on the packages list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Volt::actingAs($admin)->test('admin.packages.index')
        ->assertSee('Products')
        ->assertSee('Packages');
});

/*
|--------------------------------------------------------------------------
| Sidebar (full page render)
|--------------------------------------------------------------------------
*/

it('renames the sidebar catalog entry to Products & Packages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('products.index'))
        ->assertOk()
        ->assertSee('Products &amp; Packages', false);
});
