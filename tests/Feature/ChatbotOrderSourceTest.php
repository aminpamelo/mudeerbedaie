<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function chatbotOrder(array $attributes = []): ProductOrder
{
    return ProductOrder::factory()->create([
        'source' => 'whatsapp_bot',
        'agent_id' => null,
        'platform_id' => null,
        'hidden_from_admin' => false,
        'metadata' => ['cekbot_flow_name' => 'Funnel Safi Bida'],
        ...$attributes,
    ]);
}

it('counts chatbot orders as their own source, separate from agent/company', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    chatbotOrder();
    ProductOrder::factory()->create(['source' => 'manual', 'agent_id' => null, 'platform_id' => null, 'hidden_from_admin' => false]);

    $counts = Volt::actingAs($admin)->test('admin.orders.order-list')->instance()->getSourceCounts();

    expect($counts['all'])->toBe(2)
        ->and($counts['chatbot'])->toBe(1)
        ->and($counts['agent_company'])->toBe(1);
});

it('labels a chatbot order with the Chatbot badge (not "Company")', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $src = Volt::actingAs($admin)->test('admin.orders.order-list')->instance()->getOrderSource(chatbotOrder());

    expect($src['type'])->toBe('chatbot')
        ->and($src['label'])->toBe('Chatbot');
});

it('filters the list to chatbot orders on the Chatbot tab', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $bot = chatbotOrder(['order_number' => 'PO-BOT-0001']);
    $other = ProductOrder::factory()->create(['order_number' => 'PO-MAN-0002', 'source' => 'manual', 'agent_id' => null, 'platform_id' => null, 'hidden_from_admin' => false]);

    Volt::actingAs($admin)->test('admin.orders.order-list')
        ->set('sourceTab', 'chatbot')
        ->assertSee($bot->order_number)
        ->assertSee('Funnel Safi Bida')
        ->assertDontSee($other->order_number);

    Volt::actingAs($admin)->test('admin.orders.order-list')
        ->set('sourceTab', 'agent_company')
        ->assertSee($other->order_number)
        ->assertDontSee($bot->order_number);
});
