<?php

declare(strict_types=1);

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('redirects guests to login', function () {
    $this->get('/livehost/orders')->assertRedirect('/login');
});

it('returns Inertia response for admin', function () {
    actingAs($this->admin)
        ->get('/livehost/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('orders/Index', false));
});

it('only includes tiktok_shop source orders', function () {
    $account = PlatformAccount::factory()->create();
    ProductOrder::factory()->count(2)->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
    ]);
    ProductOrder::factory()->create(['source' => 'manual']);
    ProductOrder::factory()->create(['source' => 'funnel']);

    actingAs($this->admin)
        ->get('/livehost/orders')
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index', false)
            ->has('orders.data', 2)
        );
});

it('filters by shop (platform_account_id)', function () {
    $shopA = PlatformAccount::factory()->create();
    $shopB = PlatformAccount::factory()->create();
    ProductOrder::factory()->create(['source' => 'tiktok_shop', 'platform_account_id' => $shopA->id]);
    ProductOrder::factory()->create(['source' => 'tiktok_shop', 'platform_account_id' => $shopB->id]);

    actingAs($this->admin)
        ->get("/livehost/orders?shop={$shopA->id}")
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
});

it('filters unmatched only', function () {
    $account = PlatformAccount::factory()->create();
    $session = LiveSession::factory()->create(['platform_account_id' => $account->id]);
    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => $session->id,
    ]);
    ProductOrder::factory()->create([
        'source' => 'tiktok_shop',
        'platform_account_id' => $account->id,
        'matched_live_session_id' => null,
    ]);

    actingAs($this->admin)
        ->get('/livehost/orders?unmatched_only=1')
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
});
