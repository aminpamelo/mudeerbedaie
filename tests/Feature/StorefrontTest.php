<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('homepage', function () {
    it('serves the public storefront to guests', function () {
        Product::factory()->count(3)->create(['status' => 'active']);

        $this->get('/')
            ->assertOk()
            ->assertViewIs('store.home')
            ->assertSee(config('store.name'));
    });

    it('renders in Malay by default for guests', function () {
        Product::factory()->create(['status' => 'active']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Beli Sekarang')   // BM hero CTA
            ->assertSee('Kedai');          // BM nav label
    });

    it('redirects authenticated users to their dashboard', function () {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    });
});

describe('shop', function () {
    it('lists only active products', function () {
        Product::factory()->create(['status' => 'active', 'name' => 'Visible Book']);
        Product::factory()->create(['status' => 'draft', 'name' => 'Hidden Draft']);

        $this->get('/shop')
            ->assertOk()
            ->assertViewIs('store.shop')
            ->assertSee('Visible Book')
            ->assertDontSee('Hidden Draft');
    });

    it('filters by search query', function () {
        Product::factory()->create(['status' => 'active', 'name' => 'Alpha Widget']);
        Product::factory()->create(['status' => 'active', 'name' => 'Beta Gadget']);

        $this->get('/shop?q=Alpha')
            ->assertOk()
            ->assertSee('Alpha Widget')
            ->assertDontSee('Beta Gadget');
    });

    it('filters by category', function () {
        $category = ProductCategory::factory()->create(['name' => 'Books', 'slug' => 'books-'.uniqid()]);
        Product::factory()->create(['status' => 'active', 'name' => 'In Category', 'category_id' => $category->id]);
        Product::factory()->create(['status' => 'active', 'name' => 'No Category']);

        $this->get('/shop?category='.$category->id)
            ->assertOk()
            ->assertSee('In Category')
            ->assertDontSee('No Category');
    });
});

describe('cart access', function () {
    it('lets a guest open the cart page without crashing', function () {
        // Regression: the cart previously rendered in the auth-only app layout,
        // which called auth()->user()->isStudent() and 500'd for guests.
        $this->get('/cart')->assertOk();
    });
});

describe('language', function () {
    it('persists a valid locale choice in the session', function () {
        $this->get('/lang/en')->assertRedirect();
        expect(session('locale'))->toBe('en');

        $this->get('/lang/ms')->assertRedirect();
        expect(session('locale'))->toBe('ms');
    });

    it('ignores an unsupported locale', function () {
        session(['locale' => 'ms']);

        $this->get('/lang/fr')->assertRedirect();

        expect(session('locale'))->toBe('ms');
    });
});
