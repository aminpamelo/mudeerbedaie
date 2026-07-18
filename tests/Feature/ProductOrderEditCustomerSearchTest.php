<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function makeEditableOrder(User $customer): ProductOrder
{
    $order = ProductOrder::factory()->create([
        'customer_id' => $customer->id,
        'guest_email' => null,
        'status' => 'processing',
    ]);

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
    ]);

    return $order;
}

test('edit page loads even when there are far more customers than the dropdown cap', function () {
    $customer = User::factory()->create(['role' => 'student', 'name' => 'Assigned Buyer']);
    // Well past the 50-row cap; the old unbounded query would load all of these.
    User::factory()->count(80)->create(['role' => 'student']);

    $order = makeEditableOrder($customer);

    actingAs($this->admin)
        ->get(route('admin.orders.edit', $order))
        ->assertSuccessful();
});

test('mount pre-fills the search box with the assigned customer', function () {
    $customer = User::factory()->create([
        'role' => 'student',
        'name' => 'Siti Aminah',
        'email' => 'siti@example.com',
    ]);
    $order = makeEditableOrder($customer);

    actingAs($this->admin);

    Volt::test('admin.orders.order-edit', ['order' => $order])
        ->assertSet('customerSearch', 'Siti Aminah (siti@example.com)');
});

test('customer list is capped at 50 regardless of table size', function () {
    $customer = User::factory()->create(['role' => 'student']);
    User::factory()->count(80)->create(['role' => 'student']);
    $order = makeEditableOrder($customer);

    actingAs($this->admin);

    Volt::test('admin.orders.order-edit', ['order' => $order])
        ->assertViewHas('customers', fn ($customers) => $customers->count() <= 50);
});

test('searching filters the customer results by name or email', function () {
    $customer = User::factory()->create(['role' => 'student']);
    User::factory()->create(['role' => 'student', 'name' => 'Findable Person', 'email' => 'find@example.com']);
    User::factory()->count(10)->create(['role' => 'student']);
    $order = makeEditableOrder($customer);

    actingAs($this->admin);

    Volt::test('admin.orders.order-edit', ['order' => $order])
        ->set('customerSearch', 'Findable')
        ->assertViewHas('customers', fn ($customers) => $customers->count() === 1
            && $customers->first()->name === 'Findable Person');
});

test('selecting a customer sets the order customer id and fills details', function () {
    $customer = User::factory()->create(['role' => 'student']);
    $newCustomer = User::factory()->create([
        'role' => 'student',
        'name' => 'New Buyer',
        'email' => 'new@example.com',
    ]);
    $order = makeEditableOrder($customer);

    actingAs($this->admin);

    Volt::test('admin.orders.order-edit', ['order' => $order])
        ->call('selectCustomer', $newCustomer->id)
        ->assertSet('form.customer_id', $newCustomer->id)
        ->assertSet('form.customer_email', 'new@example.com')
        ->assertSet('customerSearch', 'New Buyer (new@example.com)');
});

test('clearing the selection resets customer id and search', function () {
    $customer = User::factory()->create(['role' => 'student']);
    $order = makeEditableOrder($customer);

    actingAs($this->admin);

    Volt::test('admin.orders.order-edit', ['order' => $order])
        ->call('clearCustomerSelection')
        ->assertSet('form.customer_id', '')
        ->assertSet('customerSearch', '');
});
