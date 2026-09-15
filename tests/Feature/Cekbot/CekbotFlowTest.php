<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key']);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'bot-out'], 201)]);
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);
});

/** Drive one inbound customer message through the real webhook pipeline. */
function flowInbound(string $body, string $id, string $type = 'chat'): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => $id, 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => $type, 'notifyName' => 'Ali'],
    ])->assertOk();
}

/** The most recent outbound (bot) message body. */
function lastBotReply(): string
{
    return (string) CekbotMessage::query()->where('direction', 'out')->latest('id')->value('body');
}

function makeFlow(int $sessionId, array $overrides = [], array $packages = []): CekbotFlow
{
    $flow = CekbotFlow::create(array_merge([
        'cekbot_session_id' => $sessionId,
        'name' => 'Funnel Jualan',
        'is_active' => true,
        'use_ai' => false, // these cases exercise the deterministic numbered-menu fallback
        'match_type' => 'contains',
        'trigger_keywords' => ['order', 'minat'],
        'welcome_message' => 'Salam! 🙌 Terima kasih berminat.',
        'ask_payment' => true,
        'payment_transfer_enabled' => true,
        'payment_cod_enabled' => true,
        'bank_details' => 'Maybank 5121xxxx (Kedai ABC)',
        'ask_name' => true,
    ], $overrides));

    $packages = $packages ?: [
        ['label' => 'Pakej A', 'price' => 97, 'sort_order' => 1],
        ['label' => 'Pakej B', 'price' => 197, 'sort_order' => 2],
    ];

    foreach ($packages as $p) {
        CekbotFlowPackage::create(array_merge(['cekbot_flow_id' => $flow->id, 'currency' => 'RM'], $p));
    }

    return $flow;
}

it('starts the funnel and lists packages when a trigger keyword arrives', function () {
    makeFlow($this->session->id);

    flowInbound('Nak order sekarang', 'm1');

    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->status)->toBe(CekbotFlowEnrollment::STATUS_ACTIVE)
        ->and($enrollment->current_step)->toBe(CekbotFlowEnrollment::STEP_AWAIT_PACKAGE);

    $reply = lastBotReply();
    expect($reply)->toContain('Pakej A')->toContain('Pakej B')->toContain('RM97')->toContain('RM197');
});

it('does not start a funnel when nothing matches the trigger', function () {
    makeFlow($this->session->id);

    flowInbound('Apa khabar?', 'n1');

    expect(CekbotFlowEnrollment::query()->count())->toBe(0);
});

it('drives package → COD → name → address and creates a pending order', function () {
    makeFlow($this->session->id);

    flowInbound('nak order', 'c1');           // trigger → package menu
    flowInbound('2', 'c2');                    // pick Pakej B (RM197) → payment menu
    expect(lastBotReply())->toContain('Transfer')->toContain('COD');

    flowInbound('cod', 'c3');                  // choose COD → ask name
    expect(lastBotReply())->toContain('nama');

    flowInbound('Ali Bin Abu', 'c4');          // name → ask address
    expect(lastBotReply())->toContain('alamat');

    flowInbound('No 5, Jalan Mawar, 43000 Kajang, Selangor', 'c5'); // address → order

    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_method)->toBe('cod')
        ->and((float) $order->total_amount)->toBe(197.0)
        ->and($order->customer_name)->toBe('Ali Bin Abu')
        ->and($order->customer_phone)->toBe('60129999999')
        ->and($order->status)->toBe('pending')
        ->and($order->payment_status)->toBe('pending');

    expect($order->items()->value('product_name'))->toBe('Pakej B');

    // The free-text address is captured in the shipping_address JSON and
    // normalises for courier booking (postcode extracted, state derived).
    $addr = $order->effectiveAddress('shipping');
    expect($addr)->not->toBeNull()
        ->and($addr->address_line_1)->toContain('Jalan Mawar')
        ->and($addr->postal_code)->toBe('43000');

    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment->status)->toBe(CekbotFlowEnrollment::STATUS_COMPLETED)
        ->and($enrollment->product_order_id)->toBe($order->id);
});

it('shows bank details on the transfer path and creates the order after the receipt', function () {
    makeFlow($this->session->id);

    flowInbound('minat', 't1');                // trigger
    flowInbound('1', 't2');                    // pick Pakej A (RM97) → payment menu
    flowInbound('1', 't3');                    // choose Transfer → ask name
    flowInbound('Siti', 't4');                 // name → bank details + await receipt
    expect(lastBotReply())->toContain('Maybank')->toContain('resit');

    flowInbound('dah transfer ni', 't5');      // receipt → order

    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_method)->toBe('bank_transfer')
        ->and((float) $order->total_amount)->toBe(97.0)
        ->and($order->customer_name)->toBe('Siti');
});

it('skips the payment question when only one method is enabled', function () {
    makeFlow($this->session->id, [
        'payment_transfer_enabled' => false,
        'payment_cod_enabled' => true,
    ]);

    flowInbound('nak order', 's1');            // trigger → menu
    flowInbound('1', 's2');                    // pick Pakej A → should skip straight to name

    expect(lastBotReply())->toContain('nama');

    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment->current_step)->toBe(CekbotFlowEnrollment::STEP_AWAIT_NAME)
        ->and($enrollment->answer('payment_method'))->toBe(CekbotFlowEnrollment::PAYMENT_COD);
});

it('lets the customer cancel mid-funnel', function () {
    makeFlow($this->session->id);

    flowInbound('nak order', 'x1');
    flowInbound('batal', 'x2');

    expect(lastBotReply())->toContain('batalkan');

    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment->status)->toBe(CekbotFlowEnrollment::STATUS_ABANDONED);
    expect(ProductOrder::query()->where('source', 'whatsapp_bot')->count())->toBe(0);
});
