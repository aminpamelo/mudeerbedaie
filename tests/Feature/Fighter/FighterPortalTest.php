<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\SalesSource;
use App\Models\User;
use App\Notifications\Fighter\NewOrderNotification;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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

it('lets a fighter create a funnel owned by them (direct-create button path)', function () {
    $f = fighter();

    $this->actingAs($f)
        ->postJson('/api/v1/funnels', ['name' => 'Untitled funnel'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Untitled funnel');

    expect(Funnel::query()->where('user_id', $f->id)->where('name', 'Untitled funnel')->exists())->toBeTrue();
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

it('shows a fighter only the orders from their own funnels', function () {
    $a = fighter();
    $b = fighter();

    $funnelA = Funnel::factory()->create(['user_id' => $a->id]);
    $orderA = ProductOrder::factory()->create(['source' => 'funnel', 'order_number' => 'PO-AAA']);
    FunnelOrder::factory()->create(['funnel_id' => $funnelA->id, 'product_order_id' => $orderA->id, 'order_type' => 'main']);

    $funnelB = Funnel::factory()->create(['user_id' => $b->id]);
    $orderB = ProductOrder::factory()->create(['source' => 'funnel', 'order_number' => 'PO-BBB']);
    FunnelOrder::factory()->create(['funnel_id' => $funnelB->id, 'product_order_id' => $orderB->id, 'order_type' => 'main']);

    $this->actingAs($a)
        ->get('/fighter/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders', false)
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'PO-AAA')
        );
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
