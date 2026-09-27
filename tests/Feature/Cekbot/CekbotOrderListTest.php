<?php

use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Cekbot\CekbotFlowOrderCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    $this->conversation = CekbotConversation::create([
        'cekbot_session_id' => $this->session->id,
        'chat_id' => '60129999999',
        'name' => 'Ali',
    ]);
    $this->flowA = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'Funnel A', 'is_active' => true]);
    $this->flowB = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'Funnel B', 'is_active' => true]);

    $creator = app(CekbotFlowOrderCreator::class);
    $this->codOrder = $creator->create($this->flowA, $this->conversation, [
        'label' => 'Pakej A', 'price' => 97, 'payment_method' => 'cod',
        'name' => 'Ali Bin Abu', 'phone' => '0123456789', 'address' => 'No 5, Kajang', 'driver' => 'ai',
    ]);
    $this->transferOrder = $creator->create($this->flowB, $this->conversation, [
        'label' => 'Pakej B', 'price' => 8, 'payment_method' => 'bank_transfer',
        'name' => 'Siti Aminah', 'phone' => '0198887777',
    ]);
});

it('lists only bot orders with stats', function () {
    ProductOrder::factory()->create(['source' => 'funnel', 'customer_name' => 'Bukan Bot']);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index', false)
            ->has('orders.data', 2)
            ->where('orders.data.0.order_number', $this->transferOrder->order_number)
            ->where('orders.data.1.customer_name', 'Ali Bin Abu')
            ->where('orders.data.1.flow_name', 'Funnel A')
            ->where('orders.data.1.driver', 'ai')
            ->where('orders.data.1.address', 'No 5, Kajang')
            ->where('orders.data.1.items.0.name', 'Pakej A')
            ->where('stats.total', 2)
            ->where('stats.revenue', 105)
            ->where('stats.pending_payment', 2)
            ->has('flows', 2));
});

it('filters by flow, payment method and search', function (array $query, string $expectedName) {
    $this->actingAs($this->admin)
        ->get('/admin/cekbot/orders?'.http_build_query($query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.customer_name', $expectedName));
})->with([
    'payment cod' => [['payment' => 'cod'], 'Ali Bin Abu'],
    'payment transfer' => [['payment' => 'bank_transfer'], 'Siti Aminah'],
    'search name' => [['search' => 'siti'], 'Siti Aminah'],
    'search phone' => [['search' => '0123456'], 'Ali Bin Abu'],
]);

it('filters by flow', function () {
    $this->actingAs($this->admin)
        ->get('/admin/cekbot/orders?flow='.$this->flowB->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.flow_name', 'Funnel B'));
});

it('shows an empty list when nothing matches', function () {
    $this->actingAs($this->admin)
        ->get('/admin/cekbot/orders?search=tiadasiapa')
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});

it('forbids non-admins', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/cekbot/orders')
        ->assertForbidden();
});
