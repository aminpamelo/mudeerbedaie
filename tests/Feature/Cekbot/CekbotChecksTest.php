<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key']);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'chk-out'], 201)]);
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
});

function checkInbound(string $body, string $id): array
{
    return [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => $id, 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat'],
    ];
}

it('answers an order-status query with live data', function () {
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true, 'checks_enabled' => true]);
    ProductOrder::factory()->create([
        'order_number' => 'ORD777',
        'status' => 'shipped',
        'tracking_id' => 'TRK123',
        'total_amount' => 150.00,
    ]);

    test()->postJson('/api/cekbot/webhook', checkInbound('boleh semak status pesanan ORD777?', 'c1'))->assertOk();

    $out = CekbotMessage::query()->where('direction', 'out')->latest('id')->first();
    expect($out)->not->toBeNull()
        ->and($out->body)->toContain('ORD777')
        ->and($out->body)->toContain('Shipped')
        ->and($out->body)->toContain('TRK123');
});

it('says not found for an unknown order number', function () {
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true, 'checks_enabled' => true]);

    test()->postJson('/api/cekbot/webhook', checkInbound('status pesanan ZZZ9999', 'c2'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->latest('id')->value('body'))->toContain('tak jumpa');
});

it('does not run checks when disabled', function () {
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true, 'checks_enabled' => false]);
    ProductOrder::factory()->create(['order_number' => 'ORD888', 'status' => 'shipped']);

    test()->postJson('/api/cekbot/webhook', checkInbound('status pesanan ORD888', 'c3'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(0);
});
