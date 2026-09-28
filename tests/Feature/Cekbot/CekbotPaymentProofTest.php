<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Cekbot\CekbotOutbound;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

beforeEach(function () {
    Storage::fake('public');
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'openai.api_key' => 'sk-test',
    ]);
    Http::fake([
        'waha.test/api/sendText' => Http::response(['id' => 'out'], 201),
        'waha.test/api/sendImage' => Http::response(['id' => 'img'], 201),
        'waha.test/api/files/*' => Http::response('RECEIPT-BYTES', 200, ['Content-Type' => 'image/jpeg']),
    ]);
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);

    $this->flow = CekbotFlow::create([
        'cekbot_session_id' => $this->session->id,
        'name' => 'Funnel AI',
        'is_active' => true,
        'use_ai' => true,
        'match_type' => 'contains',
        'trigger_keywords' => ['order'],
        'payment_transfer_enabled' => true,
        'payment_cod_enabled' => true,
        'bank_details' => 'Maybank 5121xxxx',
    ]);
    CekbotFlowPackage::create(['cekbot_flow_id' => $this->flow->id, 'label' => 'Pakej A', 'price' => 97, 'currency' => 'RM', 'sort_order' => 1]);
});

function proofInbound(string $id, string $body = '', ?string $mediaUrl = null): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => array_filter([
            'id' => $id, 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us',
            'body' => $body, 'type' => $mediaUrl ? 'image' : 'chat', 'notifyName' => 'Ali',
            'hasMedia' => (bool) $mediaUrl,
            'media' => $mediaUrl ? ['url' => $mediaUrl, 'mimetype' => 'image/jpeg'] : null,
        ], fn ($v) => $v !== null),
    ])->assertOk();
}

function fakeTransferOrderCall(string $method = 'transfer'): void
{
    OpenAI::fake([
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant', 'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1', 'type' => 'function',
                    'function' => ['name' => 'create_order', 'arguments' => json_encode([
                        'package' => 'Pakej A', 'payment_method' => $method,
                        'customer_name' => 'Ali Bin Abu', 'customer_phone' => '0123456789',
                        'address' => 'No 5, Kajang',
                    ])],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]]]),
        CreateResponse::fake(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Pesanan berjaya! Sila transfer & hantar resit ye.'],
            'finish_reason' => 'stop',
        ]]]),
    ]);
}

function lastProofReply(): ?string
{
    return CekbotMessage::query()->where('direction', 'out')->where('type', 'text')->latest('id')->value('body');
}

it('keeps a transfer order waiting for the receipt, reminds on text and stores the image', function () {
    fakeTransferOrderCall();
    proofInbound('p1', 'nak order, semua betul');

    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->firstOrFail();
    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment->status)->toBe(CekbotFlowEnrollment::STATUS_ACTIVE)
        ->and($enrollment->current_step)->toBe(CekbotFlowEnrollment::STEP_AWAIT_PROOF)
        ->and($enrollment->product_order_id)->toBe($order->id);

    // Text instead of a receipt → reminder, no AI call.
    proofInbound('p2', 'done transfer');
    expect(lastProofReply())->toContain('screenshot resit')
        ->and($enrollment->fresh()->current_step)->toBe(CekbotFlowEnrollment::STEP_AWAIT_PROOF);

    // The receipt image is downloaded and attached to the order.
    proofInbound('p3', '', 'https://waha.test/api/files/default/receipt.jpg');

    $order->refresh();
    expect($order->receipt_attachment)->toStartWith('cekbot-receipts/')
        ->and($order->payment_status)->toBe('pending')
        ->and(data_get($order->metadata, 'payment_proof_submitted_at'))->not->toBeNull();
    Storage::disk('public')->assertExists($order->receipt_attachment);
    expect(Storage::disk('public')->get($order->receipt_attachment))->toBe('RECEIPT-BYTES');

    expect($enrollment->fresh()->status)->toBe(CekbotFlowEnrollment::STATUS_COMPLETED)
        ->and(lastProofReply())->toContain('Bukti pembayaran');
});

it('lets the conversation go after two reminders without a receipt', function () {
    fakeTransferOrderCall();
    proofInbound('r1', 'nak order, semua betul');

    proofInbound('r2', 'ok nanti');
    proofInbound('r3', 'kejap ye');
    $replies = CekbotMessage::query()->where('direction', 'out')->count();

    proofInbound('r4', 'terima kasih');

    expect(CekbotFlowEnrollment::query()->latest('id')->first()->status)->toBe(CekbotFlowEnrollment::STATUS_COMPLETED)
        ->and(CekbotMessage::query()->where('direction', 'out')->count())->toBe($replies);
});

it('completes a COD order immediately without waiting for a receipt', function () {
    fakeTransferOrderCall('cod');
    proofInbound('c1', 'nak order, semua betul');

    expect(CekbotFlowEnrollment::query()->latest('id')->first()->status)->toBe(CekbotFlowEnrollment::STATUS_COMPLETED);
});

it('lets the team confirm the payment and notifies the customer', function () {
    fakeTransferOrderCall();
    proofInbound('t1', 'nak order, semua betul');
    proofInbound('t2', '', 'https://waha.test/api/files/default/receipt.jpg');
    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->firstOrFail();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/cekbot/orders?status=proof')
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.proof_submitted', true)
            ->where('stats.awaiting_confirmation', 1));

    $this->actingAs($admin)
        ->post("/admin/cekbot/orders/{$order->id}/confirm-payment")
        ->assertRedirect();

    $order->refresh();
    expect($order->payment_status)->toBe('paid')
        ->and($order->status)->toBe('confirmed')
        ->and($order->payment_confirmed_by_user_id)->toBe($admin->id)
        ->and(lastProofReply())->toContain('disahkan');
});

it('refuses to confirm orders that did not come from the bot', function () {
    $order = ProductOrder::factory()->create(['source' => 'funnel', 'payment_status' => 'pending']);

    $this->actingAs(User::factory()->admin()->create())
        ->post("/admin/cekbot/orders/{$order->id}/confirm-payment")
        ->assertNotFound();

    expect($order->fresh()->payment_status)->toBe('pending');
});

it('downloads Cloud API media via the Graph media id', function () {
    Http::fake([
        'graph.facebook.com/*/MEDIA123' => Http::response(['url' => 'https://lookaside.fbsbx.com/file', 'mime_type' => 'image/png']),
        'lookaside.fbsbx.com/*' => Http::response('PNG-BYTES'),
    ]);
    $session = CekbotSession::factory()->create([
        'provider' => CekbotSession::PROVIDER_CLOUD_API,
        'phone_number_id' => '111',
        'access_token' => 'token-abc',
    ]);
    $conversation = CekbotConversation::create(['cekbot_session_id' => $session->id, 'chat_id' => '60123']);
    $message = CekbotMessage::create([
        'cekbot_conversation_id' => $conversation->id, 'cekbot_session_id' => $session->id,
        'direction' => 'in', 'type' => 'image',
        'payload' => ['type' => 'image', 'image' => ['id' => 'MEDIA123']],
    ]);

    $media = app(CekbotOutbound::class)->fetchInboundMedia($session, $message);

    expect($media)->toBe(['body' => 'PNG-BYTES', 'mime' => 'image/png']);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'MEDIA123') && $r->hasHeader('Authorization', 'Bearer token-abc'));
});

it('notifies the bot customer once per milestone wherever the order is updated', function () {
    fakeTransferOrderCall();
    proofInbound('n1', 'nak order, semua betul');
    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->firstOrFail();
    $sent = fn () => CekbotMessage::query()->where('direction', 'out')->where('type', 'text')->count();

    $before = $sent();
    $order->update(['payment_status' => 'paid']);
    expect($sent())->toBe($before + 1)->and(lastProofReply())->toContain('disahkan');

    $order->update(['status' => 'processing']);
    expect(lastProofReply())->toContain('sedang kami proses');

    $order->update(['status' => 'shipped', 'tracking_id' => 'JT123456']);
    expect(lastProofReply())->toContain('dalam proses penghantaran')->toContain('2-3 hari')->toContain('JT123456');

    // Re-saving the same milestone doesn't message the customer again.
    $count = $sent();
    $order->update(['status' => 'processing']);
    $order->update(['status' => 'shipped']);
    expect($sent())->toBe($count);
});

it('never messages customers for orders that did not come from the bot', function () {
    $order = ProductOrder::factory()->create(['source' => 'funnel', 'payment_status' => 'pending', 'status' => 'pending']);

    $order->update(['payment_status' => 'paid']);
    $order->update(['status' => 'shipped']);

    expect(CekbotMessage::query()->where('direction', 'out')->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('syncs the order payment status from the admin order page so the customer is told', function () {
    fakeTransferOrderCall();
    proofInbound('s1', 'nak order, semua betul');
    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->firstOrFail();

    \Livewire\Volt\Volt::actingAs(User::factory()->admin()->create())
        ->test('admin.orders.order-show', ['order' => $order])
        ->call('updatePaymentStatus', 'completed');

    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->paid_time)->not->toBeNull()
        ->and(lastProofReply())->toContain('disahkan');
});

function aiSays(string $content): CreateResponse
{
    return CreateResponse::fake(['choices' => [[
        'index' => 0,
        'message' => ['role' => 'assistant', 'content' => $content],
        'finish_reason' => 'stop',
    ]]]);
}

function aiCalls(string $tool, array $args = []): CreateResponse
{
    return CreateResponse::fake(['choices' => [[
        'index' => 0,
        'message' => [
            'role' => 'assistant', 'content' => null,
            'tool_calls' => [['id' => 'call_'.$tool, 'type' => 'function', 'function' => ['name' => $tool, 'arguments' => json_encode($args ?: new stdClass)]]],
        ],
        'finish_reason' => 'tool_calls',
    ]]]);
}

it('uses a receipt sent before the order instead of asking for it again', function () {
    $this->flow->update(['bank_image' => 'cekbot-flows/qr.png']);

    OpenAI::fake([
        // "nak order, qr ada?" → AI sends the QR.
        aiCalls('send_payment_qr'),
        aiSays('Ada! Ini QR ye.'),
        // Receipt photo (caption "done transfer") → AI asks for details.
        aiSays('Terima kasih! Boleh beri nama, telefon & alamat?'),
        // Details confirmed → AI creates the transfer order.
        aiCalls('create_order', [
            'package' => 'Pakej A', 'payment_method' => 'transfer',
            'customer_name' => 'Ahmad Amin', 'customer_phone' => '0165756060', 'address' => 'Lot 1697, Kota Bharu',
        ]),
        aiSays('Pesanan berjaya! Team akan sahkan bayaran.'),
    ]);

    proofInbound('e1', 'nak order, qr ada?');
    expect(CekbotMessage::query()->where('direction', 'out')->where('type', 'image')->count())->toBe(1);

    proofInbound('e2', 'done transfer', 'https://waha.test/api/files/default/receipt.jpg');
    $enrollment = CekbotFlowEnrollment::query()->latest('id')->first();
    expect($enrollment->answer('proof_message_id'))->not->toBeNull();

    proofInbound('e3', 'ya betul semua');

    $order = ProductOrder::query()->where('source', 'whatsapp_bot')->firstOrFail();
    expect($order->receipt_attachment)->toStartWith('cekbot-receipts/')
        ->and(data_get($order->metadata, 'payment_proof_submitted_at'))->not->toBeNull()
        ->and($order->payment_status)->toBe('pending');

    // Already has the receipt → the enrollment closes; no "send your receipt" wait.
    expect($enrollment->fresh()->status)->toBe(CekbotFlowEnrollment::STATUS_COMPLETED);

    // QR was sent once only — not again after the order.
    expect(CekbotMessage::query()->where('direction', 'out')->where('type', 'image')->count())->toBe(1);

    // The model saw the photo, knew the receipt & QR were already handled, and
    // the order result told it not to ask for the receipt again.
    OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $method, array $params) {
        $system = $params['messages'][0]['content'];
        $sawPhoto = collect($params['messages'])->contains(fn ($m) => str_contains((string) ($m['content'] ?? ''), 'kemungkinan resit bayaran'));
        $toolResult = collect($params['messages'])->firstWhere('role', 'tool');

        return $toolResult !== null
            && $sawPhoto
            && str_contains($system, 'SUDAH hantar resit')
            && str_contains($system, 'QR bayaran SUDAH dihantar')
            && str_contains($toolResult['content'], 'JANGAN minta resit')
            && ! str_contains($toolResult['content'], 'bank_details');
    });
});

it('still sends the QR and waits for the receipt when nothing was paid before the order', function () {
    $this->flow->update(['bank_image' => 'cekbot-flows/qr.png']);
    fakeTransferOrderCall();

    proofInbound('w1', 'nak order, semua betul');

    expect(CekbotMessage::query()->where('direction', 'out')->where('type', 'image')->count())->toBe(1)
        ->and(CekbotFlowEnrollment::query()->latest('id')->first()->current_step)->toBe(CekbotFlowEnrollment::STEP_AWAIT_PROOF);
});
