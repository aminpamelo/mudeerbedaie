<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

beforeEach(function () {
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'openai.api_key' => 'sk-test',
    ]);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'ai-out'], 201)]);
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);

    $this->flow = CekbotFlow::create([
        'cekbot_session_id' => $this->session->id,
        'name' => 'Funnel AI',
        'is_active' => true,
        'use_ai' => true,
        'match_type' => 'contains',
        'trigger_keywords' => ['order', 'minat'],
        'payment_transfer_enabled' => true,
        'payment_cod_enabled' => true,
        'bank_details' => 'Maybank 5121xxxx',
    ]);
    CekbotFlowPackage::create(['cekbot_flow_id' => $this->flow->id, 'label' => 'Pakej A', 'price' => 97, 'currency' => 'RM', 'sort_order' => 1]);
});

function aiInbound(string $body, string $id): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => $id, 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat', 'notifyName' => 'Ali'],
    ])->assertOk();
}

it('creates an order when the AI calls create_order with confirmed details', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1', 'type' => 'function',
                    'function' => [
                        'name' => 'create_order',
                        'arguments' => json_encode([
                            'package' => 'Pakej A',
                            'payment_method' => 'cod',
                            'customer_name' => 'Ali Bin Abu',
                            'customer_phone' => '0123456789',
                            'address' => 'No 5, Jalan Mawar, 43000 Kajang, Selangor',
                        ]),
                    ],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]]]),
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Terima kasih Ali! Pesanan anda berjaya 🎉'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    aiInbound('nak order, saya dah sahkan semua betul', 'a1');

    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_method)->toBe('cod')
        ->and((float) $order->total_amount)->toBe(97.0)
        ->and($order->customer_name)->toBe('Ali Bin Abu')
        ->and($order->customer_phone)->toBe('0123456789');

    expect($order->items()->value('product_name'))->toBe('Pakej A');

    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment->status)->toBe(CekbotFlowEnrollment::STATUS_COMPLETED)
        ->and($enrollment->product_order_id)->toBe($order->id);
});

it('refuses to create an order and asks for the phone when it is missing', function () {
    OpenAI::fake([
        // AI tries to create an order without a phone number.
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1', 'type' => 'function',
                    'function' => [
                        'name' => 'create_order',
                        'arguments' => json_encode([
                            'package' => 'Pakej A',
                            'payment_method' => 'cod',
                            'customer_name' => 'Ali',
                            'customer_phone' => '',
                            'address' => 'No 5, Jalan Mawar, 43000 Kajang',
                        ]),
                    ],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]]]),
        // After the tool error, the AI asks for the phone number.
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Boleh kongsi no. telefon anda ye? 🙂'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    aiInbound('nak order pakej A, COD, nama Ali', 'b1');

    expect(ProductOrder::query()->where('source', 'whatsapp_bot')->count())->toBe(0);

    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment->status)->toBe(CekbotFlowEnrollment::STATUS_ACTIVE);

    // The tool error (asking for the phone) was fed back to the model.
    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => $method === 'create'
        && collect($params['messages'])->contains(fn ($m) => ($m['role'] ?? '') === 'tool' && str_contains($m['content'] ?? '', 'telefon')));
});

it('answers conversationally without forcing a number', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Pakej A sesuai untuk anda! Nak saya terangkan lebih lanjut? 🙂'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    aiInbound('saya berminat dengan kelas ni, apa kelebihan dia?', 'c1');

    expect(\App\Models\CekbotMessage::query()->where('direction', 'out')->latest('id')->value('body'))
        ->toBe('Pakej A sesuai untuk anda! Nak saya terangkan lebih lanjut? 🙂');

    // The create_order tool is offered to the model.
    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => $method === 'create'
        && collect($params['tools'] ?? [])->contains(fn ($t) => data_get($t, 'function.name') === 'create_order'));
});

it('gives the AI the bank details and QR so it can answer before the order', function () {
    $this->flow->update(['bank_image' => 'cekbot-flows/qr.png']);
    Http::fake([
        'waha.test/api/sendText' => Http::response(['id' => 'ai-out'], 201),
        'waha.test/api/sendImage' => Http::response(['id' => 'ai-img'], 201),
    ]);

    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_qr', 'type' => 'function',
                    'function' => ['name' => 'send_payment_qr', 'arguments' => '{}'],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]]]),
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Ada! Saya hantar QR ye 🙂'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    aiInbound('nak order, ada qr ke?', 'q1');

    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => $method === 'create'
        && str_contains($params['messages'][0]['content'], 'Maybank 5121xxxx')
        && str_contains($params['messages'][0]['content'], 'send_payment_qr')
        && collect($params['tools'])->contains(fn ($t) => data_get($t, 'function.name') === 'send_payment_qr'));

    $img = \App\Models\CekbotMessage::query()->where('type', 'image')->latest('id')->first();
    expect($img)->not->toBeNull()
        ->and($img->media_url)->toContain('cekbot-flows/qr.png');

    expect(ProductOrder::query()->where('source', 'whatsapp_bot')->exists())->toBeFalse();
});

it('does not offer the QR tool when no QR is uploaded', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Boleh transfer ke Maybank ye.'],
            'finish_reason' => 'stop',
        ]]]),
    ]);

    aiInbound('nak order, bank apa?', 'q2');

    OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn (string $method, array $params) => $method === 'create'
        && ! collect($params['tools'])->contains(fn ($t) => data_get($t, 'function.name') === 'send_payment_qr')
        && ! str_contains($params['messages'][0]['content'], 'QR BAYARAN'));

    expect(\App\Models\CekbotMessage::query()->where('type', 'image')->exists())->toBeFalse();
});
