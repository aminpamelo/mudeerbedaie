<?php

use App\Models\CekbotFlow;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function () {
    $this->employee = User::factory()->create(['role' => 'employee']);
    $this->session = CekbotSession::factory()->working()->create();
    $this->flow = CekbotFlow::create(['cekbot_session_id' => $this->session->id, 'name' => 'F1']);
});

it('lets employees open every Cekbot page', function (string $path) {
    $this->actingAs($this->employee)
        ->get(str_replace('{flow}', (string) $this->flow->id, $path))
        ->assertOk();
})->with([
    'numbers' => ['/admin/cekbot'],
    'inbox' => ['/admin/cekbot/inbox'],
    'leads' => ['/admin/cekbot/leads'],
    'flows' => ['/admin/cekbot/flows'],
    'flow builder' => ['/admin/cekbot/flows/{flow}'],
    'orders' => ['/admin/cekbot/orders'],
    'auto-reply' => ['/admin/cekbot/auto-reply'],
    'products' => ['/admin/cekbot/products'],
    'broadcast' => ['/admin/cekbot/broadcast'],
    'analytics' => ['/admin/cekbot/analytics'],
    'settings' => ['/admin/cekbot/settings'],
]);

it('lets employees act in Cekbot (save a flow, confirm a bot payment)', function () {
    $this->actingAs($this->employee)
        ->put(route('cekbot.flows.update', $this->flow->id), [
            'name' => 'F1 edited', 'match_type' => 'contains', 'trigger_keywords' => ['minat'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect($this->flow->fresh()->name)->toBe('F1 edited');

    $order = ProductOrder::factory()->create(['source' => 'whatsapp_bot', 'payment_status' => 'pending', 'status' => 'pending']);
    $this->actingAs($this->employee)
        ->post(route('cekbot.orders.confirm-payment', $order))
        ->assertRedirect();
    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->payment_confirmed_by_user_id)->toBe($this->employee->id);
});

it('still blocks other roles from Cekbot', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/admin/cekbot/inbox')
        ->assertForbidden();
})->with(['student', 'teacher', 'sales', 'accountant']);

it('authorises the real-time inbox channel for admins and employees only', function () {
    $channels = Broadcast::getChannels();
    $callback = $channels['cekbot-inbox'];

    expect($callback(User::factory()->admin()->create()))->toBeTrue()
        ->and($callback($this->employee))->toBeTrue()
        ->and($callback(User::factory()->create(['role' => 'teacher'])))->toBeFalse();
});

it('shows the Cekbot link in the sidebar for employees', function () {
    $this->actingAs($this->employee)
        ->get('/admin/product-orders')
        ->assertOk()
        ->assertSee('href="/admin/cekbot"', false);
});
