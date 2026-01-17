<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\KedaiBukuPricing;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

describe('Kedai Buku Agent Management', function () {
    test('admin can view kedai buku list page', function () {
        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.index'))
            ->assertOk();
    });

    test('admin can view kedai buku create page', function () {
        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.create'))
            ->assertOk();
    });

    test('admin can create a new kedai buku', function () {
        Volt::test('admin.kedai-buku.kedai-buku-create')
            ->actingAs($this->admin)
            ->set('form.name', 'Test Bookstore')
            ->set('form.contact_person', 'John Doe')
            ->set('form.email', 'bookstore@test.com')
            ->set('form.phone', '0123456789')
            ->set('form.pricing_tier', 'standard')
            ->set('form.credit_limit', 5000)
            ->set('form.is_active', true)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agents', [
            'name' => 'Test Bookstore',
            'type' => 'bookstore',
            'email' => 'bookstore@test.com',
            'pricing_tier' => 'standard',
        ]);
    });

    test('admin can view kedai buku details', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'standard',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.show', $kedaiBuku))
            ->assertOk();
    });

    test('admin can edit kedai buku', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'standard',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.edit', $kedaiBuku))
            ->assertOk();
    });

    test('admin can update kedai buku', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'name' => 'Original Name',
            'pricing_tier' => 'standard',
            'is_active' => true,
        ]);

        Volt::test('admin.kedai-buku.kedai-buku-edit', ['kedaiBuku' => $kedaiBuku])
            ->actingAs($this->admin)
            ->set('form.name', 'Updated Bookstore Name')
            ->set('form.pricing_tier', 'premium')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agents', [
            'id' => $kedaiBuku->id,
            'name' => 'Updated Bookstore Name',
            'pricing_tier' => 'premium',
        ]);
    });
});

describe('Kedai Buku Pricing', function () {
    test('admin can view pricing page', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'standard',
        ]);

        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.pricing', $kedaiBuku))
            ->assertOk();
    });

    test('tier discount percentages are correct', function () {
        $standard = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'standard',
        ]);
        $premium = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'premium',
        ]);
        $vip = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'vip',
        ]);

        expect($standard->getTierDiscountPercentage())->toBe(10);
        expect($premium->getTierDiscountPercentage())->toBe(15);
        expect($vip->getTierDiscountPercentage())->toBe(20);
    });

    test('tier price calculation is correct', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'premium', // 15% discount
        ]);

        $originalPrice = 100.00;
        $expectedPrice = 85.00; // 100 - 15%

        expect($kedaiBuku->calculateTierPrice($originalPrice))->toBe($expectedPrice);
    });

    test('custom pricing overrides tier pricing', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'pricing_tier' => 'standard',
        ]);

        $product = Product::factory()->create([
            'base_price' => 100.00,
        ]);

        // Create custom pricing
        KedaiBukuPricing::create([
            'agent_id' => $kedaiBuku->id,
            'product_id' => $product->id,
            'price' => 75.00,
            'min_quantity' => 1,
            'is_active' => true,
        ]);

        $price = $kedaiBuku->getPriceForProduct($product->id, 1);

        expect($price)->toBe(75.00);
    });
});

describe('Kedai Buku Credit Limit', function () {
    test('available credit is calculated correctly', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'credit_limit' => 10000.00,
        ]);

        // Create an unpaid order
        ProductOrder::factory()->create([
            'agent_id' => $kedaiBuku->id,
            'total_amount' => 3000.00,
            'payment_status' => 'pending',
        ]);

        $kedaiBuku->refresh();

        expect($kedaiBuku->outstanding_balance)->toBe(3000.00);
        expect($kedaiBuku->available_credit)->toBe(7000.00);
    });

    test('would exceed credit limit check works', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
            'credit_limit' => 5000.00,
        ]);

        ProductOrder::factory()->create([
            'agent_id' => $kedaiBuku->id,
            'total_amount' => 4000.00,
            'payment_status' => 'pending',
        ]);

        $kedaiBuku->refresh();

        expect($kedaiBuku->wouldExceedCreditLimit(500.00))->toBeFalse();
        expect($kedaiBuku->wouldExceedCreditLimit(1500.00))->toBeTrue();
    });
});

describe('Kedai Buku Orders', function () {
    test('admin can view kedai buku orders index', function () {
        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.orders.index'))
            ->assertOk();
    });

    test('admin can view kedai buku order create page', function () {
        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.orders.create'))
            ->assertOk();
    });

    test('admin can view specific kedai buku orders', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
        ]);

        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.orders', $kedaiBuku))
            ->assertOk();
    });

    test('admin can view kedai buku order details', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
        ]);

        $order = ProductOrder::factory()->create([
            'agent_id' => $kedaiBuku->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.orders.show', $order))
            ->assertOk();
    });

    test('admin can view kedai buku order invoice', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
        ]);

        $order = ProductOrder::factory()->create([
            'agent_id' => $kedaiBuku->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.orders.invoice', $order))
            ->assertOk();
    });

    test('admin can view kedai buku order delivery note', function () {
        $kedaiBuku = Agent::factory()->create([
            'type' => 'bookstore',
        ]);

        $order = ProductOrder::factory()->create([
            'agent_id' => $kedaiBuku->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('agents-kedai-buku.orders.delivery-note', $order))
            ->assertOk();
    });
});

describe('Agent Model Bookstore Methods', function () {
    test('isBookstore returns true for bookstore type', function () {
        $bookstore = Agent::factory()->create(['type' => 'bookstore']);
        $agent = Agent::factory()->create(['type' => 'agent']);

        expect($bookstore->isBookstore())->toBeTrue();
        expect($agent->isBookstore())->toBeFalse();
    });

    test('generateBookstoreCode creates unique code', function () {
        $code1 = Agent::generateBookstoreCode();
        $code2 = Agent::generateBookstoreCode();

        expect($code1)->toStartWith('KB-');
        expect($code2)->toStartWith('KB-');
        expect($code1)->not->toBe($code2);
    });

    test('bookstores scope only returns bookstore agents', function () {
        Agent::factory()->create(['type' => 'bookstore']);
        Agent::factory()->create(['type' => 'bookstore']);
        Agent::factory()->create(['type' => 'agent']);
        Agent::factory()->create(['type' => 'company']);

        $bookstores = Agent::bookstores()->get();

        expect($bookstores)->toHaveCount(2);
        expect($bookstores->every(fn ($a) => $a->type === 'bookstore'))->toBeTrue();
    });
});
