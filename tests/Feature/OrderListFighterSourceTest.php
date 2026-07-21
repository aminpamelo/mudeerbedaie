<?php

use App\Models\ProductOrder;
use App\Models\SalesSource;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function makeFighterWithSource(): SalesSource
{
    $source = SalesSource::factory()->create(['name' => 'Fighter: Test Fighter', 'is_active' => true]);
    User::factory()->create(['role' => 'fighter', 'sales_source_id' => $source->id]);

    return $source;
}

test('fighter source count reflects only orders tagged to a fighter segment', function () {
    $fighterSource = makeFighterWithSource();
    $otherSource = SalesSource::factory()->create(['name' => 'Newsletter']);

    ProductOrder::factory()->count(2)->create([
        'sales_source_id' => $fighterSource->id,
        'hidden_from_admin' => false,
    ]);
    ProductOrder::factory()->create(['sales_source_id' => $otherSource->id, 'hidden_from_admin' => false]);
    ProductOrder::factory()->create(['sales_source_id' => null, 'hidden_from_admin' => false]);

    actingAs($this->admin);

    $counts = Volt::test('admin.orders.order-list')->instance()->getSourceCounts();

    expect($counts['fighter'])->toBe(2);
});

test('selecting the fighter tab shows only fighter orders', function () {
    $fighterSource = makeFighterWithSource();
    $otherSource = SalesSource::factory()->create(['name' => 'Newsletter']);

    $fighterOrder = ProductOrder::factory()->create([
        'sales_source_id' => $fighterSource->id,
        'hidden_from_admin' => false,
    ]);
    $plainOrder = ProductOrder::factory()->create([
        'sales_source_id' => $otherSource->id,
        'hidden_from_admin' => false,
    ]);

    actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->assertSee('Fighter')
        ->set('sourceTab', 'fighter')
        ->assertSee($fighterOrder->order_number)
        ->assertDontSee($plainOrder->order_number);
});

test('fighter source count is zero and page still renders when there are no fighters', function () {
    ProductOrder::factory()->create(['sales_source_id' => null, 'hidden_from_admin' => false]);

    actingAs($this->admin);

    $component = Volt::test('admin.orders.order-list')
        ->assertSuccessful()
        ->set('sourceTab', 'fighter')
        ->assertSuccessful();

    expect($component->instance()->getSourceCounts()['fighter'])->toBe(0);
});

test('orders of a soft-deleted fighter still count under fighter', function () {
    $source = SalesSource::factory()->create(['name' => 'Fighter: Gone Fighter']);
    $fighter = User::factory()->create(['role' => 'fighter', 'sales_source_id' => $source->id]);
    $order = ProductOrder::factory()->create([
        'sales_source_id' => $source->id,
        'hidden_from_admin' => false,
    ]);
    $fighter->delete();

    actingAs($this->admin);

    $component = Volt::test('admin.orders.order-list');

    expect($component->instance()->getSourceCounts()['fighter'])->toBe(1);

    $component->set('sourceTab', 'fighter')->assertSee($order->order_number);
});

test('a fighter funnel order still counts under fighter even though its channel is funnel', function () {
    $fighterSource = makeFighterWithSource();

    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'sales_source_id' => $fighterSource->id,
        'hidden_from_admin' => false,
    ]);

    actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('sourceTab', 'fighter')
        ->assertSee($order->order_number);
});

test('a fighter pos order does not also count under the pos tab', function () {
    $fighterSource = makeFighterWithSource();

    $fighterPosOrder = ProductOrder::factory()->create([
        'source' => 'pos',
        'sales_source_id' => $fighterSource->id,
        'hidden_from_admin' => false,
    ]);
    $plainPosOrder = ProductOrder::factory()->create([
        'source' => 'pos',
        'sales_source_id' => null,
        'hidden_from_admin' => false,
    ]);

    actingAs($this->admin);

    $component = Volt::test('admin.orders.order-list');
    $counts = $component->instance()->getSourceCounts();

    expect($counts['pos'])->toBe(1);
    expect($counts['fighter'])->toBe(1);

    $component->set('sourceTab', 'pos')
        ->assertSee($plainPosOrder->order_number)
        ->assertDontSee($fighterPosOrder->order_number);
});

test('a fighter funnel order does not also count under the funnel tab', function () {
    $fighterSource = makeFighterWithSource();

    $fighterFunnelOrder = ProductOrder::factory()->create([
        'source' => 'funnel',
        'sales_source_id' => $fighterSource->id,
        'hidden_from_admin' => false,
    ]);
    $plainFunnelOrder = ProductOrder::factory()->create([
        'source' => 'funnel',
        'sales_source_id' => null,
        'hidden_from_admin' => false,
    ]);

    actingAs($this->admin);

    $component = Volt::test('admin.orders.order-list');
    $counts = $component->instance()->getSourceCounts();

    expect($counts['funnel'])->toBe(1);
    expect($counts['fighter'])->toBe(1);

    $component->set('sourceTab', 'funnel')
        ->assertSee($plainFunnelOrder->order_number)
        ->assertDontSee($fighterFunnelOrder->order_number);
});
