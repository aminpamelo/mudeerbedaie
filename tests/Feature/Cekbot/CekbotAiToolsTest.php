<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'openai.api_key' => 'sk-test',
    ]);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'tool-out'], 201)]);
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
});

it('lets the AI call the order-status tool and answer with live data', function () {
    ProductOrder::factory()->create(['order_number' => 'ORD777', 'status' => 'shipped', 'tracking_id' => 'TRK9']);

    OpenAI::fake([
        // 1st turn: model asks to call the tool
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1', 'type' => 'function',
                    'function' => ['name' => 'check_order_status', 'arguments' => '{"order_number":"ORD777"}'],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]]]),
        // 2nd turn: model answers using the tool result
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Pesanan ORD777 anda sudah dihantar (shipped). No. tracking: TRK9.'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    // AI on, rule-based checks OFF so the message reaches the AI tool path
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true, 'ai_enabled' => true, 'checks_enabled' => false]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 't1', 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'boleh semak pesanan ORD777?', 'type' => 'chat'],
    ])->assertOk();

    $out = CekbotMessage::query()->where('direction', 'out')->first();
    expect($out?->body)->toBe('Pesanan ORD777 anda sudah dihantar (shipped). No. tracking: TRK9.');

    // The tool ran and its live result was fed back to the model.
    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => $method === 'create'
        && collect($params['messages'])->contains(fn ($m) => ($m['role'] ?? '') === 'tool' && str_contains($m['content'] ?? '', 'shipped')));
});
