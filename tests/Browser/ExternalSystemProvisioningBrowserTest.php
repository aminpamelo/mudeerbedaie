<?php

declare(strict_types=1);

use App\Models\ExternalSystem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Browser smoke tests for the external-system fulfillment feature: the product
 * edit form's provisioning card and the orders "Needs Account" filter.
 */
beforeEach(function () {
    // The product edit page loads plan options from the external system's API on
    // mount. Fail that call fast/deterministically so the headless page never
    // blocks on a real network request.
    Http::fake(['*' => Http::response([], 500)]);
});

it('shows the provisioning card on an external-system product edit page', function () {
    $admin = User::factory()->admin()->create();
    $system = ExternalSystem::factory()->create([
        'name' => 'DaiePRO',
        'is_active' => true,
        'base_url' => 'http://127.0.0.1:1', // fast connection-refused fallback
        'timeout' => 1,
    ]);
    $product = Product::factory()->create([
        'name' => 'DaiePRO Access',
        'fulfillment_type' => 'external_system',
        'metadata' => ['provisioning' => ['external_system_id' => $system->id, 'plan' => 'pro']],
    ]);

    $this->actingAs($admin);

    // The card heading + the "Plan" field prove hydration: the Plan field only
    // renders once the stored external_system_id has loaded. (The chosen system
    // name lives inside a <select> option, which is not "visible" text.)
    visit("/admin/products/{$product->id}/edit")
        ->assertNoJavascriptErrors()
        ->assertSee('Fulfillment Type')
        ->assertSee('External System Provisioning')
        ->assertSee('External System')
        ->assertSee('Plan');
});

it('hides the provisioning card for a physical product', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['fulfillment_type' => 'physical']);

    $this->actingAs($admin);

    visit("/admin/products/{$product->id}/edit")
        ->assertNoJavascriptErrors()
        ->assertSee('Fulfillment Type')
        ->assertDontSee('External System Provisioning');
});

it('lets an admin toggle the "Needs Account" order filter', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    visit('/admin/product-orders')
        ->assertNoJavascriptErrors()
        ->assertSee('Needs Account')
        ->click('Needs Account')
        ->assertSee('Filters:'); // the active-filter chip row only shows once a filter is on
});
