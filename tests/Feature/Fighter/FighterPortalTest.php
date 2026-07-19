<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\SalesSource;
use App\Models\User;
use App\Notifications\Fighter\NewOrderNotification;
use App\Services\Fighter\FighterProvisioner;
use App\Services\Funnel\FunnelCheckoutService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

function fighter(array $attrs = []): User
{
    return User::factory()->create(array_merge(['role' => 'fighter'], $attrs));
}

/*
|--------------------------------------------------------------------------
| Portal access + role gating
|--------------------------------------------------------------------------
*/

it('lets a fighter open their dashboard', function () {
    $this->actingAs(fighter())
        ->get('/fighter')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)->has('funnels')->has('stats'));
});

it('forbids non-fighters from the fighter portal', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']))
        ->get('/fighter')
        ->assertForbidden();
});

it('redirects guests away from the fighter portal', function () {
    $this->get('/fighter')->assertRedirect('/login');
});

it('allows admins into the fighter portal', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/fighter')
        ->assertOk();
});

it('sends a fighter to their dashboard from the generic dashboard route', function () {
    $this->actingAs(fighter())
        ->get('/dashboard')
        ->assertRedirect(route('fighter.dashboard'));
});

it('serves a dedicated fighter login page to guests', function () {
    $this->get('/fighter/login')
        ->assertOk()
        ->assertSee('Bedaie Fighter')
        ->assertSee('Ready to run your funnels');
});

it('keeps the fighter login guest-only', function () {
    $this->actingAs(fighter())->get('/fighter/login')->assertRedirect();
});

it('locks a fighter out of the admin area entirely', function () {
    $f = fighter();

    // A fighter never lands on the admin dashboard — they are redirected home.
    $this->actingAs($f)->get('/dashboard')->assertRedirect(route('fighter.dashboard'));

    // And admin-gated pages are forbidden outright.
    $this->actingAs($f)->get('/admin/product-orders')->assertForbidden();
    $this->actingAs($f)->get('/admin/users')->assertForbidden();
    $this->actingAs($f)->get('/admin/funnels')->assertForbidden();
});

it('shows a fighter only their own funnels on the dashboard', function () {
    $a = fighter();
    $b = fighter();
    $mine = Funnel::factory()->create(['user_id' => $a->id, 'name' => 'My Funnel']);
    Funnel::factory()->create(['user_id' => $b->id, 'name' => 'Their Funnel']);

    $this->actingAs($a)
        ->get('/fighter')
        ->assertInertia(fn (Assert $page) => $page
            ->has('funnels', 1)
            ->where('funnels.0.uuid', $mine->uuid)
        );
});

/*
|--------------------------------------------------------------------------
| Funnel ownership isolation (the security-critical part)
|--------------------------------------------------------------------------
*/

it('blocks a fighter from opening another fighter\'s funnel via the API', function () {
    $a = fighter();
    $b = fighter();
    $theirs = Funnel::factory()->create(['user_id' => $b->id]);

    $this->actingAs($a)
        ->getJson('/api/v1/funnels/'.$theirs->uuid)
        ->assertForbidden();
});

it('lets a fighter open their own funnel via the API', function () {
    $a = fighter();
    $mine = Funnel::factory()->create(['user_id' => $a->id]);

    $this->actingAs($a)
        ->getJson('/api/v1/funnels/'.$mine->uuid)
        ->assertOk();
});

it('returns 404 to a fighter for an unknown funnel uuid', function () {
    $this->actingAs(fighter())
        ->getJson('/api/v1/funnels/'.Str::uuid())
        ->assertNotFound();
});

it('scopes the funnel API index to the fighter\'s own funnels', function () {
    $a = fighter();
    $b = fighter();
    $mine = Funnel::factory()->create(['user_id' => $a->id]);
    $theirs = Funnel::factory()->create(['user_id' => $b->id]);

    $this->actingAs($a)
        ->getJson('/api/v1/funnels')
        ->assertOk()
        ->assertJsonFragment(['uuid' => $mine->uuid])
        ->assertJsonMissing(['uuid' => $theirs->uuid]);
});

it('does not restrict an admin from another user\'s funnel', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $theirs = Funnel::factory()->create(['user_id' => fighter()->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/funnels/'.$theirs->uuid)
        ->assertOk();
});

it('gives a fighter access to the funnel builder SPA', function () {
    $this->actingAs(fighter())->get('/funnel-builder')->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'student']))->get('/funnel-builder')->assertForbidden();
});

it('lets a fighter delete their own funnel but not another fighter\'s', function () {
    $a = fighter();
    $b = fighter();
    $mine = Funnel::factory()->create(['user_id' => $a->id]);
    $theirs = Funnel::factory()->create(['user_id' => $b->id]);

    // Owner can delete (soft delete).
    $this->actingAs($a)->deleteJson('/api/v1/funnels/'.$mine->uuid)->assertOk();
    expect(Funnel::query()->whereKey($mine->id)->exists())->toBeFalse();

    // A fighter cannot delete a funnel they don't own.
    $this->actingAs($a)->deleteJson('/api/v1/funnels/'.$theirs->uuid)->assertForbidden();
    expect(Funnel::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('lets a fighter create a funnel owned by them (direct-create button path)', function () {
    $f = fighter();

    $this->actingAs($f)
        ->postJson('/api/v1/funnels', ['name' => 'Untitled funnel'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Untitled funnel');

    expect(Funnel::query()->where('user_id', $f->id)->where('name', 'Untitled funnel')->exists())->toBeTrue();
});

it('creates a funnel even when a soft-deleted funnel holds the same slug', function () {
    $f = fighter();

    // Create then soft-delete — the slug now sits in the unique index.
    $first = $this->actingAs($f)->postJson('/api/v1/funnels', ['name' => 'Untitled funnel'])
        ->assertCreated()->json('data');
    $this->actingAs($f)->deleteJson('/api/v1/funnels/'.$first['uuid'])->assertOk();

    // Re-creating with the same name must not collide with the trashed slug.
    $this->actingAs($f)->postJson('/api/v1/funnels', ['name' => 'Untitled funnel'])->assertCreated();
    $this->actingAs($f)->postJson('/api/v1/funnels', ['name' => 'Untitled funnel'])->assertCreated();
});

/*
|--------------------------------------------------------------------------
| Order tagging via SalesSource + admin visibility
|--------------------------------------------------------------------------
*/

it('provisions a single sales-source segment per fighter, idempotently', function () {
    $f = fighter(['name' => 'Ali Baba']);
    $provisioner = app(FighterProvisioner::class);

    $first = $provisioner->ensureSalesSource($f);
    $second = $provisioner->ensureSalesSource($f->fresh());

    expect($first->id)->toBe($second->id)
        ->and($first->name)->toBe('Fighter: Ali Baba')
        ->and($f->fresh()->sales_source_id)->toBe($first->id)
        ->and(SalesSource::count())->toBe(1);
});

it('tags fighter-funnel orders with the fighter segment and forces admin visibility', function () {
    $f = fighter(['name' => 'Ziad']);
    // Even with the funnel set to hide orders from admin, fighter orders must show.
    $funnel = Funnel::factory()->hideOrdersFromAdmin()->create(['user_id' => $f->id]);

    $tag = app(FighterProvisioner::class)->orderTaggingFor($funnel);

    expect($tag['hidden_from_admin'])->toBeFalse()
        ->and($tag['sales_source_id'])->not->toBeNull();
    expect(SalesSource::find($tag['sales_source_id'])->name)->toBe('Fighter: Ziad');
});

it('leaves non-fighter funnels untagged and respects their admin-visibility setting', function () {
    $owner = User::factory()->create(['role' => 'student']);
    $funnel = Funnel::factory()->hideOrdersFromAdmin()->create(['user_id' => $owner->id]);

    $tag = app(FighterProvisioner::class)->orderTaggingFor($funnel);

    expect($tag['sales_source_id'])->toBeNull()
        ->and($tag['hidden_from_admin'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Fighter order feed scoping
|--------------------------------------------------------------------------
*/

it('shows a fighter only the orders attributed to their segment', function () {
    $a = fighter();
    $segA = app(FighterProvisioner::class)->ensureSalesSource($a);
    $b = fighter();
    $segB = app(FighterProvisioner::class)->ensureSalesSource($b);

    // A funnel order and a manual order, both under fighter A's segment.
    ProductOrder::factory()->create(['source' => 'funnel', 'order_number' => 'PO-AAA', 'sales_source_id' => $segA->id]);
    ProductOrder::factory()->create(['source' => 'pos', 'order_number' => 'PO-MANUAL', 'sales_source_id' => $segA->id]);
    // Fighter B's order must not leak in.
    ProductOrder::factory()->create(['source' => 'pos', 'order_number' => 'PO-BBB', 'sales_source_id' => $segB->id]);

    $this->actingAs($a)
        ->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders', false)
            ->has('orders.data', 2)
            ->where('orders.data', fn ($rows) => collect($rows)->pluck('order_number')->sort()->values()->all() === ['PO-AAA', 'PO-MANUAL'])
        );
});

it('exposes provider label and stored tracking url on the fighter orders feed', function () {
    $f = fighter();
    $seg = app(FighterProvisioner::class)->ensureSalesSource($f);

    ProductOrder::factory()->create([
        'source' => 'funnel',
        'order_number' => 'PO-TRACK',
        'sales_source_id' => $seg->id,
        'status' => 'shipped',
        'shipping_provider' => 'easyparcel',
        'tracking_id' => '632118771195',
        'metadata' => ['shipping_tracking_url' => 'https://easyparcel.com/my/track/632118771195'],
    ]);

    $this->actingAs($f)
        ->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders', false)
            ->where('orders.data.0.tracking_id', '632118771195')
            ->where('orders.data.0.shipping_provider', 'EasyParcel')
            ->where('orders.data.0.tracking_url', 'https://easyparcel.com/my/track/632118771195')
        );
});

it('falls back to the EasyParcel tracker for manual orders with no recorded courier', function () {
    $f = fighter();
    $seg = app(FighterProvisioner::class)->ensureSalesSource($f);

    ProductOrder::factory()->create([
        'source' => 'pos',
        'order_number' => 'PO-MANUAL-TRACK',
        'sales_source_id' => $seg->id,
        'status' => 'delivered',
        'shipping_provider' => null,
        'tracking_id' => '632120810306',
        'metadata' => null,
    ]);

    $this->actingAs($f)
        ->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders', false)
            ->where('orders.data.0.shipping_provider', 'EasyParcel')
            ->where('orders.data.0.tracking_url', 'https://easyparcel.com/my/easytrack/')
        );
});

it('lets a fighter create, edit and delete their own product', function () {
    $f = fighter();

    // Create
    $this->actingAs($f)->post('/fighter/products', [
        'name' => 'My Special Kit',
        'base_price' => 120,
        'status' => 'active',
        'description' => 'Nice.',
    ])->assertRedirect();

    $product = Product::query()->where('created_by_fighter_id', $f->id)->firstOrFail();
    expect($product->name)->toBe('My Special Kit')
        ->and($product->base_price)->toEqual('120.00')
        ->and($product->sku)->toStartWith('FGT-');

    // Edit
    $this->actingAs($f)->put("/fighter/products/{$product->id}", [
        'name' => 'Renamed Kit',
        'base_price' => 99,
        'status' => 'inactive',
    ])->assertRedirect();
    expect($product->fresh()->name)->toBe('Renamed Kit')
        ->and($product->fresh()->status)->toBe('inactive');

    // Delete (no orders → hard delete)
    $this->actingAs($f)->delete("/fighter/products/{$product->id}")->assertRedirect();
    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

it('stops a fighter editing HQ or another fighter\'s product', function () {
    $a = fighter();
    $b = fighter();
    $hq = Product::factory()->create(['status' => 'active']); // created_by_fighter_id null
    $bProduct = Product::factory()->create(['status' => 'active', 'created_by_fighter_id' => $b->id]);

    $this->actingAs($a)->put("/fighter/products/{$hq->id}", ['name' => 'x', 'base_price' => 1, 'status' => 'active'])->assertForbidden();
    $this->actingAs($a)->put("/fighter/products/{$bProduct->id}", ['name' => 'x', 'base_price' => 1, 'status' => 'active'])->assertForbidden();
    $this->actingAs($a)->delete("/fighter/products/{$bProduct->id}")->assertForbidden();

    expect($hq->fresh()->name)->not->toBe('x');
});

it('shows a fighter only HQ + their own products in the catalog', function () {
    $a = fighter();
    $b = fighter();
    $hq = Product::factory()->create(['status' => 'active', 'name' => 'HQ Item']);
    $mine = Product::factory()->create(['status' => 'active', 'name' => 'A Item', 'created_by_fighter_id' => $a->id]);
    Product::factory()->create(['status' => 'active', 'name' => 'B Item', 'created_by_fighter_id' => $b->id]);

    $names = collect($this->actingAs($a)->getJson('/fighter/catalog')->json('products'))->pluck('name');

    expect($names)->toContain('HQ Item')
        ->and($names)->toContain('A Item')
        ->and($names)->not->toContain('B Item');
});

it('lets a fighter favourite a product and returns it pinned in the catalog', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active']);

    $this->actingAs($f)->postJson('/fighter/catalog/favourites', ['product_id' => $product->id])
        ->assertOk()->assertJsonPath('favourited', true);
    expect($f->favouriteProducts()->count())->toBe(1);

    $this->actingAs($f)->getJson('/fighter/catalog')
        ->assertOk()
        ->assertJsonPath('favourites.0.id', $product->id)
        ->assertJsonPath('favourites.0.is_favourite', true)
        ->assertJsonPath('products.0.is_favourite', true);

    $this->actingAs($f)->postJson('/fighter/catalog/favourites', ['product_id' => $product->id])
        ->assertOk()->assertJsonPath('favourited', false);
    expect($f->fresh()->favouriteProducts()->count())->toBe(0);
});

it('records a fighter manual order end-to-end: all details saved, shows in orders + visible to the team', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 20, 'track_quantity' => false, 'name' => 'BC Abjad']);

    $this->actingAs($f)->postJson('/api/pos/sales', [
        'customer_name' => 'Siti Aminah',
        'customer_phone' => '60198887766',
        'customer_email' => 'siti@example.com',
        'customer_address' => 'No 12, Jalan Melati',
        'customer_postcode' => '15200',
        'customer_city' => 'Kota Bharu',
        'customer_state' => 'Kelantan',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'shipping_cost' => 5,
        'items' => [
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 2, 'unit_price' => 20],
        ],
    ])->assertSuccessful();

    $order = ProductOrder::query()->where('source', 'pos')->latest('id')->firstOrFail();

    // 1. Every field we set is stored on the order.
    expect($order->customer_name)->toBe('Siti Aminah')
        ->and($order->customer_phone)->toBe('60198887766')
        ->and($order->guest_email)->toBe('siti@example.com')
        ->and((float) $order->total_amount)->toEqual(45.0)          // 2×20 + 5 shipping
        ->and($order->hidden_from_admin)->toBeFalse()
        ->and($order->sales_source_id)->toBe($f->fresh()->sales_source_id);

    // 2. Structured shipping address (postcode/city/state/address).
    expect($order->shipping_address)->toMatchArray([
        'address' => 'No 12, Jalan Melati',
        'postcode' => '15200',
        'city' => 'Kota Bharu',
        'state' => 'Kelantan',
    ]);

    // 3. The line item is stored with the right product + quantity.
    expect($order->items()->count())->toBe(1);
    $item = $order->items()->first();
    expect($item->product_id)->toBe($product->id)
        ->and((int) $item->quantity_ordered)->toBe(2)
        ->and((float) $item->unit_price)->toEqual(20.0)
        ->and($item->product_name)->toBe('BC Abjad');

    // 4. It appears in the fighter's own Orders list (tagged Manual).
    $this->actingAs($f)->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number)
            ->where('orders.data.0.source_label', 'Manual')
            ->where('orders.data.0.total', 45)
        );

    // 5. It's visible to the internal e-commerce team (admin orders list).
    expect(ProductOrder::query()->visibleInAdmin()->whereKey($order->id)->exists())->toBeTrue();
});

it('stores a structured shipping address on a fighter manual order', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 20, 'track_quantity' => false]);

    $this->actingAs($f)->postJson('/api/pos/sales', [
        'customer_name' => 'Ali',
        'customer_phone' => '60123456789',
        'customer_address' => 'No 1, Jalan Mawar',
        'customer_postcode' => '15200',
        'customer_city' => 'Kota Bharu',
        'customer_state' => 'Kelantan',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'items' => [['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 1, 'unit_price' => 20]],
    ])->assertSuccessful();

    $order = ProductOrder::query()->where('source', 'pos')->latest('id')->first();
    expect($order->shipping_address)->toMatchArray([
        'address' => 'No 1, Jalan Mawar',
        'postcode' => '15200',
        'city' => 'Kota Bharu',
        'state' => 'Kelantan',
    ]);
});

it('forces a fighter\'s manual (POS) order onto their own segment', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 50, 'track_quantity' => false]);
    $otherSegment = SalesSource::factory()->create(['name' => 'Sales Team']);

    $this->actingAs($f)->postJson('/api/pos/sales', [
        // A fighter tries to attribute the sale to another (sales-team) segment...
        'sales_source_id' => $otherSegment->id,
        'customer_name' => 'Walk In',
        'customer_phone' => '60123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [[
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]],
    ])->assertSuccessful();

    $order = ProductOrder::query()->where('source', 'pos')->latest('id')->first();

    // ...but the server forces it onto the fighter's own segment.
    expect($order->sales_source_id)->toBe($f->fresh()->sales_source_id)
        ->and($order->sales_source_id)->not->toBe($otherSegment->id);
});

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

it('surfaces new-order notifications in the fighter feed and marks them read', function () {
    $f = fighter();
    $order = ProductOrder::factory()->create(['total_amount' => 49]);
    $funnel = Funnel::factory()->create(['user_id' => $f->id, 'name' => 'Qadha Solat']);

    $f->notify(new NewOrderNotification($order, $funnel));

    $this->actingAs($f)
        ->getJson('/fighter/notifications/feed')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.body', "{$order->order_number} from “Qadha Solat”");

    $this->actingAs($f)->postJson('/fighter/notifications/read-all')->assertOk();

    $this->actingAs($f)
        ->getJson('/fighter/notifications/feed')
        ->assertJsonPath('unread_count', 0);
});

it('keeps non-fighter notifications out of the fighter feed', function () {
    $f = fighter();
    $order = ProductOrder::factory()->create();
    $funnel = Funnel::factory()->create(['user_id' => $f->id]);
    $f->notify(new NewOrderNotification($order, $funnel));

    // A notification from another namespace must never appear in the feed.
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Other\\SomethingElse',
        'notifiable_type' => User::class,
        'notifiable_id' => $f->id,
        'data' => json_encode(['title' => 'unrelated']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($f)
        ->getJson('/fighter/notifications/feed')
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('unread_count', 1);
});

it('stores a payment receipt on a fighter manual order and surfaces it in the orders list', function () {
    Storage::fake('public');
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);

    $this->actingAs($f)->post('/api/pos/sales', [
        'customer_name' => 'Nurul',
        'customer_phone' => '60111222333',
        'payment_method' => 'bank_transfer',
        'payment_reference' => 'TRX-9001',
        'payment_status' => 'paid',
        'items' => [
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 1, 'unit_price' => 30],
        ],
        'receipt_attachment' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertSuccessful();

    $order = ProductOrder::query()->where('source', 'pos')->latest('id')->firstOrFail();

    // The uploaded file is stored on the public disk and linked to the order.
    expect($order->receipt_attachment)->not->toBeNull();
    Storage::disk('public')->assertExists($order->receipt_attachment);

    // The fighter's Orders list exposes a viewable receipt URL for the order.
    $this->actingAs($f)->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number)
            ->where('orders.data.0.receipt_url', $order->receipt_attachment_url)
            ->whereNot('orders.data.0.receipt_url', null)
        );
});

it('leaves the receipt url null on a fighter order with no attachment', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);

    $this->actingAs($f)->postJson('/api/pos/sales', [
        'customer_name' => 'Aiman',
        'customer_phone' => '60199990000',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'items' => [['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 1, 'unit_price' => 30]],
    ])->assertSuccessful();

    $order = ProductOrder::query()->where('source', 'pos')->latest('id')->firstOrFail();
    expect($order->receipt_attachment)->toBeNull();

    $this->actingAs($f)->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.receipt_url', null)
        );
});

/*
|--------------------------------------------------------------------------
| Order view / edit / soft-delete / restore
|--------------------------------------------------------------------------
*/

function makeFighterOrder(User $fighter, Product $product, int $qty = 1, float $price = 30): ProductOrder
{
    test()->actingAs($fighter)->postJson('/api/pos/sales', [
        'customer_name' => 'Buyer One',
        'customer_phone' => '60123456789',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'items' => [['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => $qty, 'unit_price' => $price]],
    ])->assertSuccessful();

    return ProductOrder::query()->where('source', 'pos')->latest('id')->firstOrFail();
}

it('lets a fighter view the full detail of their own order', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false, 'name' => 'Kitab A']);
    $order = makeFighterOrder($f, $product);

    $this->actingAs($f)->getJson("/fighter/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.customer.name', 'Buyer One')
        ->assertJsonPath('data.items.0.product_name', 'Kitab A')
        ->assertJsonPath('data.total', 30);
});

it('blocks a fighter from viewing another fighter\'s order', function () {
    $a = fighter();
    $b = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);
    $order = makeFighterOrder($b, $product);

    $this->actingAs($a)->getJson("/fighter/orders/{$order->id}")->assertNotFound();
});

it('lets a fighter edit their own order items, customer and payment', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);
    $order = makeFighterOrder($f, $product, 1, 30);
    $itemId = $order->items()->first()->id;

    $this->actingAs($f)->post("/fighter/orders/{$order->id}", [
        'customer_name' => 'Updated Name',
        'customer_phone' => '60111111111',
        'customer_email' => 'updated@example.com',
        'payment_method' => 'bank_transfer',
        'payment_reference' => 'TRX-777',
        'payment_status' => 'paid',
        'shipping_cost' => 5,
        'items' => [
            ['id' => $itemId, 'itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 3, 'unit_price' => 30],
        ],
    ])->assertOk();

    $order->refresh();
    expect($order->customer_name)->toBe('Updated Name')
        ->and($order->payment_method)->toBe('bank_transfer')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->paid_time)->not->toBeNull()
        ->and((float) $order->total_amount)->toEqual(95.0)   // 3×30 + 5 shipping
        ->and((int) $order->items()->first()->quantity_ordered)->toBe(3)
        ->and($order->metadata['payment_reference'])->toBe('TRX-777');
});

it('edits an item-less funnel order without wiping its stored total', function () {
    $f = fighter();
    $segment = app(FighterProvisioner::class)->ensureSalesSource($f);
    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'sales_source_id' => $segment->id,
        'subtotal' => 63.03,
        'total_amount' => 63.03,
        'customer_name' => null,
        'guest_email' => 'No email provided',
    ]);
    expect($order->items()->count())->toBe(0);

    $this->actingAs($f)->post("/fighter/orders/{$order->id}", [
        'customer_name' => 'Filled In',
        'customer_phone' => '60122223333',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [],
    ])->assertOk();

    $order->refresh();
    expect($order->customer_name)->toBe('Filled In')
        ->and($order->payment_status)->toBe('paid')
        ->and((float) $order->total_amount)->toEqual(63.03)   // total preserved, not zeroed
        ->and($order->items()->count())->toBe(0);
});

it('cleans the funnel "No email provided" placeholder in order detail', function () {
    $f = fighter();
    $segment = app(FighterProvisioner::class)->ensureSalesSource($f);
    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'sales_source_id' => $segment->id,
        'guest_email' => 'No email provided',
    ]);

    $this->actingAs($f)->getJson("/fighter/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.customer.email', null);
});

it('blocks a fighter from editing another fighter\'s order', function () {
    $a = fighter();
    $b = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);
    $order = makeFighterOrder($b, $product);

    $this->actingAs($a)->post("/fighter/orders/{$order->id}", [
        'customer_name' => 'Hacker',
        'customer_phone' => '60100000000',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'items' => [['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 1, 'unit_price' => 30]],
    ])->assertNotFound();

    expect($order->fresh()->customer_name)->not->toBe('Hacker');
});

it('lets a fighter soft-delete their own order and it leaves the team view', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);
    $order = makeFighterOrder($f, $product);

    $this->actingAs($f)->deleteJson("/fighter/orders/{$order->id}")->assertOk();

    // Soft-deleted (restorable), not gone.
    expect(ProductOrder::withTrashed()->whereKey($order->id)->exists())->toBeTrue()
        ->and(ProductOrder::query()->whereKey($order->id)->exists())->toBeFalse();

    // Gone from the fighter's active list, present in the bin with a count.
    $this->actingAs($f)->get('/fighter/orders')
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0)->where('trashCount', 1));

    $this->actingAs($f)->get('/fighter/orders?view=trash')
        ->assertInertia(fn (Assert $page) => $page->where('view', 'trash')->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number));
});

it('blocks a fighter from deleting another fighter\'s order', function () {
    $a = fighter();
    $b = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);
    $order = makeFighterOrder($b, $product);

    $this->actingAs($a)->deleteJson("/fighter/orders/{$order->id}")->assertNotFound();
    expect(ProductOrder::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('lets a fighter restore a trashed order from the bin', function () {
    $f = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);
    $order = makeFighterOrder($f, $product);
    $order->delete();

    $this->actingAs($f)->postJson("/fighter/orders/{$order->id}/restore")->assertOk();

    expect(ProductOrder::query()->whereKey($order->id)->exists())->toBeTrue();
    $this->actingAs($f)->get('/fighter/orders')
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('trashCount', 0));
});

it('blocks a fighter from restoring another fighter\'s trashed order', function () {
    $a = fighter();
    $b = fighter();
    $product = Product::factory()->create(['status' => 'active', 'base_price' => 30, 'track_quantity' => false]);
    $order = makeFighterOrder($b, $product);
    $order->delete();

    $this->actingAs($a)->postJson("/fighter/orders/{$order->id}/restore")->assertNotFound();
    expect(ProductOrder::onlyTrashed()->whereKey($order->id)->exists())->toBeTrue();
});

it('fires a new-order notification to the funnel-owning fighter on checkout', function () {
    $f = fighter();
    $funnel = Funnel::factory()->create(['user_id' => $f->id, 'name' => 'My Sales Funnel']);
    $order = ProductOrder::factory()->create(['source' => 'funnel', 'total_amount' => 99]);

    // Drive the exact hook the real checkout flow calls after creating the order.
    // Stripe isn't configured in tests, so stub it out of the service constructor.
    $this->mock(StripeService::class, fn ($m) => $m->shouldReceive('getStripe')->andReturn(Mockery::mock(StripeClient::class)));
    $service = app(FunnelCheckoutService::class);
    $method = new ReflectionMethod($service, 'notifyFighterOfNewOrder');
    $method->setAccessible(true);
    $method->invoke($service, $funnel, $order);

    expect($f->fresh()->notifications()->where('type', NewOrderNotification::class)->count())->toBe(1);

    // ...and it surfaces in the fighter's bell feed.
    $this->actingAs($f)->getJson('/fighter/notifications/feed')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.title', 'New order · RM 99.00');
});

it('does not notify when the funnel owner is not a fighter', function () {
    $employee = User::factory()->create(['role' => 'employee']);
    $funnel = Funnel::factory()->create(['user_id' => $employee->id]);
    $order = ProductOrder::factory()->create(['source' => 'funnel', 'total_amount' => 50]);

    $this->mock(StripeService::class, fn ($m) => $m->shouldReceive('getStripe')->andReturn(Mockery::mock(StripeClient::class)));
    $service = app(FunnelCheckoutService::class);
    $method = new ReflectionMethod($service, 'notifyFighterOfNewOrder');
    $method->setAccessible(true);
    $method->invoke($service, $funnel, $order);

    expect($employee->fresh()->notifications()->count())->toBe(0);
});
