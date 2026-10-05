<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelStudioServer;
use App\Mcp\Tools\CekbotFlowsTool;
use App\Mcp\Tools\CekbotOrdersTool;
use App\Mcp\Tools\CekbotOverviewTool;
use App\Mcp\Tools\TeamSalesOrdersTool;
use App\Mcp\Tools\TeamSalesReportTool;
use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use App\Models\User;

function salesOrder(?User $salesperson, float $total, array $overrides = []): ProductOrder
{
    return ProductOrder::factory()->create(array_merge([
        'source' => 'pos',
        'status' => 'confirmed',
        'order_date' => now(),
        'paid_time' => now(),
        'total_amount' => $total,
        'metadata' => $salesperson ? ['salesperson_id' => $salesperson->id, 'salesperson_name' => $salesperson->name] : null,
    ], $overrides));
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->mel = User::factory()->create(['name' => 'Mellissa', 'role' => 'sales']);
    $this->diak = User::factory()->create(['name' => 'Ahmad Ahadiaq', 'role' => 'sales']);
});

it('reports Team Sales totals and ranks salespeople like the admin page', function () {
    salesOrder($this->mel, 300);
    salesOrder($this->mel, 100, ['paid_time' => null, 'status' => 'pending']);
    salesOrder($this->diak, 250);
    salesOrder($this->diak, 999, ['status' => 'cancelled', 'paid_time' => null]);
    salesOrder(null, 50);
    salesOrder($this->mel, 777, ['order_date' => now()->subMonths(2)]);
    ProductOrder::factory()->create(['source' => 'storefront', 'order_date' => now(), 'total_amount' => 5000, 'metadata' => null]);

    $response = FunnelStudioServer::actingAs($this->admin)->tool(TeamSalesReportTool::class, ['period' => 'this_month']);

    $response->assertOk()
        ->assertSee('"total_revenue":700')
        ->assertSee('"total_orders":4')
        ->assertSee('"cancelled":{"count":1,"revenue":999')
        ->assertSee('"pending":{"count":1,"revenue":100')
        ->assertSee('"name":"Mellissa","orders":2,"revenue":400')
        ->assertSee('"name":"Ahmad Ahadiaq","orders":1,"revenue":250')
        ->assertSee('"name":"Unassigned","orders":1,"revenue":50')
        ->assertDontSee('5000');
});

it('filters Team Sales by salesperson name and status', function () {
    salesOrder($this->mel, 300);
    salesOrder($this->mel, 100, ['paid_time' => null, 'status' => 'pending']);
    salesOrder($this->diak, 250);

    FunnelStudioServer::actingAs($this->admin)
        ->tool(TeamSalesReportTool::class, ['salesperson' => 'melliss', 'status' => 'paid'])
        ->assertOk()
        ->assertSee('"salesperson":"Mellissa"')
        ->assertSee('"total_revenue":300')
        ->assertDontSee('"name":"Ahmad Ahadiaq","orders"');
});

it('explains unknown salesperson names with the list of salespeople', function () {
    salesOrder($this->mel, 300);

    FunnelStudioServer::actingAs($this->admin)
        ->tool(TeamSalesReportTool::class, ['salesperson' => 'Zack'])
        ->assertHasErrors()
        ->assertSee('Mellissa');
});

it('returns a monthly breakdown per salesperson', function () {
    salesOrder($this->mel, 300, ['order_date' => now()->setDate(2026, 3, 10)]);
    salesOrder($this->diak, 120, ['order_date' => now()->setDate(2026, 3, 12)]);

    FunnelStudioServer::actingAs($this->admin)
        ->tool(TeamSalesReportTool::class, ['period' => 'all', 'monthly_year' => 2026])
        ->assertOk()
        ->assertSee('"month":"2026-03","orders":2,"revenue":420');
});

it('lists Team Sales orders for a salesperson', function () {
    salesOrder($this->mel, 300, ['order_number' => 'PO-MEL-1']);
    salesOrder($this->diak, 250, ['order_number' => 'PO-DIAK-1']);

    FunnelStudioServer::actingAs($this->admin)
        ->tool(TeamSalesOrdersTool::class, ['salesperson' => 'Ahadiaq'])
        ->assertOk()
        ->assertSee('PO-DIAK-1')
        ->assertDontSee('PO-MEL-1');
});

it('hides Team Sales from fighters', function () {
    salesOrder($this->mel, 300);
    $fighter = User::factory()->create(['role' => 'fighter']);

    FunnelStudioServer::actingAs($fighter)->tool(TeamSalesReportTool::class, [])->assertHasErrors();
    FunnelStudioServer::actingAs($fighter)->tool(TeamSalesOrdersTool::class, [])->assertHasErrors();
});

it('lets the sales role read Team Sales', function () {
    salesOrder($this->mel, 300);

    FunnelStudioServer::actingAs($this->mel)->tool(TeamSalesReportTool::class, [])->assertOk()->assertSee('"total_revenue":300');
});

/**
 * One Cekbot number with a sales flow: 3 enrollments, 1 converted into a RM49 bot order.
 */
function seedCekbot(): CekbotFlow
{
    $session = CekbotSession::factory()->working()->create(['label' => 'Chatbot Bedaie']);
    $flow = CekbotFlow::create([
        'cekbot_session_id' => $session->id, 'name' => 'Qadha Solat', 'is_active' => true, 'use_ai' => true,
        'match_type' => 'contains', 'trigger_keywords' => ['qadha solat'],
    ]);
    CekbotFlowPackage::create(['cekbot_flow_id' => $flow->id, 'label' => 'Pakej 1', 'price' => 49, 'currency' => 'RM', 'sort_order' => 0]);

    $order = ProductOrder::factory()->create([
        'order_number' => 'PO-BOT-1', 'source' => 'whatsapp_bot', 'status' => 'pending', 'order_date' => now(),
        'total_amount' => 49, 'paid_time' => null, 'metadata' => ['cekbot_flow_id' => $flow->id, 'cekbot_flow_name' => 'Qadha Solat'],
    ]);

    foreach ([null, null, $order->id] as $i => $orderId) {
        $conversation = CekbotConversation::create(['cekbot_session_id' => $session->id, 'chat_id' => '6012000000'.$i, 'is_group' => false]);
        CekbotFlowEnrollment::create([
            'cekbot_conversation_id' => $conversation->id, 'cekbot_flow_id' => $flow->id,
            'status' => $orderId ? CekbotFlowEnrollment::STATUS_COMPLETED : CekbotFlowEnrollment::STATUS_ABANDONED,
            'current_step' => 'ai', 'data' => [], 'product_order_id' => $orderId, 'started_at' => now(),
        ]);
    }

    return $flow;
}

it('summarises Cekbot conversations, bot orders and the flow funnel', function () {
    seedCekbot();

    FunnelStudioServer::actingAs($this->admin)
        ->tool(CekbotOverviewTool::class, ['period' => '7d'])
        ->assertOk()
        ->assertSee('"new_conversations":3')
        ->assertSee('"bot_orders":{"orders":1,"revenue":49')
        ->assertSee('"name":"Qadha Solat"')
        ->assertSee('"started":3')
        ->assertSee('"conversion_rate":33.3');
});

it('lists Cekbot flows with their mode and keywords', function () {
    seedCekbot();

    FunnelStudioServer::actingAs($this->admin)
        ->tool(CekbotFlowsTool::class, [])
        ->assertOk()
        ->assertSee('"mode":"ai"')
        ->assertSee('qadha solat')
        ->assertSee('"orders":1');
});

it('lists Cekbot orders filtered by flow', function () {
    $flow = seedCekbot();

    FunnelStudioServer::actingAs($this->admin)
        ->tool(CekbotOrdersTool::class, ['flow_id' => $flow->id])
        ->assertOk()
        ->assertSee('PO-BOT-1')
        ->assertSee('"flow":"Qadha Solat"');

    FunnelStudioServer::actingAs($this->admin)
        ->tool(CekbotOrdersTool::class, ['flow_id' => $flow->id + 999])
        ->assertOk()
        ->assertDontSee('PO-BOT-1');
});

it('hides Cekbot data from fighters and sales users', function () {
    seedCekbot();

    foreach ([User::factory()->create(['role' => 'fighter']), $this->mel] as $user) {
        FunnelStudioServer::actingAs($user)->tool(CekbotOverviewTool::class, [])->assertHasErrors();
        FunnelStudioServer::actingAs($user)->tool(CekbotOrdersTool::class, [])->assertHasErrors();
    }
});
