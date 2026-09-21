<?php

use App\Jobs\ProcessWhatsAppWebhookJob;
use App\Models\CekbotAutoReply;
use App\Models\CekbotBotSetting;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * Stub Meta's Graph API with a successful response (send + status check).
 * Registered per-test because Http::fake() merges (first stub wins), so the
 * one failure test can register its own error stub without being shadowed.
 */
function fakeGraphOk(): void
{
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200),
    ]);
}

/**
 * A connected official (Cloud API) number.
 */
function cloudSession(array $overrides = []): CekbotSession
{
    return CekbotSession::factory()->create(array_merge([
        'provider' => CekbotSession::PROVIDER_CLOUD_API,
        'phone_number_id' => 'PNID123',
        'access_token' => 'EAAG-test-token',
        'phone_number' => '60111222333',
        'status' => CekbotSession::STATUS_WORKING,
    ], $overrides));
}

function enableBotFor(CekbotSession $session, array $overrides = []): CekbotBotSetting
{
    return CekbotBotSetting::create(array_merge([
        'cekbot_session_id' => $session->id,
        'bot_enabled' => true,
    ], $overrides));
}

/**
 * A Meta inbound-message webhook `value` block for the given number.
 */
function cloudInboundPayload(string $phoneNumberId, string $body, string $wamid = 'wamid.IN1', string $from = '60129999999'): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA1',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '60111222333', 'phone_number_id' => $phoneNumberId],
                    'contacts' => [['profile' => ['name' => 'Ali'], 'wa_id' => $from]],
                    'messages' => [[
                        'from' => $from,
                        'id' => $wamid,
                        'timestamp' => '1690000000',
                        'type' => 'text',
                        'text' => ['body' => $body],
                    ]],
                ],
            ]],
        ]],
    ];
}

it('routes a bot auto-reply on a cloud number through the Graph API, not WAHA', function () {
    fakeGraphOk();
    $session = cloudSession();
    enableBotFor($session);
    CekbotAutoReply::factory()->create([
        'cekbot_session_id' => $session->id,
        'keywords' => ['harga'],
        'reply_body' => 'Harga bermula RM99.',
    ]);

    $this->postJson('/api/cekbot/cloud/webhook', cloudInboundPayload('PNID123', 'Nak tanya harga produk'))
        ->assertOk();

    // Inbound stored + conversation named from the contact profile.
    $conversation = CekbotConversation::query()->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->chat_id)->toBe('60129999999')
        ->and($conversation->name)->toBe('Ali');

    expect(CekbotMessage::query()->where('direction', 'in')->where('body', 'Nak tanya harga produk')->exists())->toBeTrue();

    $out = CekbotMessage::query()->where('direction', 'out')->first();
    expect($out)->not->toBeNull()
        ->and($out->body)->toBe('Harga bermula RM99.')
        ->and($out->from_me)->toBeTrue();

    // Sent to Meta's Graph API for this number with a bearer token, not to WAHA.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com')
        && str_contains($r->url(), 'PNID123/messages')
        && $r->hasHeader('Authorization', 'Bearer EAAG-test-token')
        && ($r['to'] ?? null) === '60129999999'
        && ($r['text']['body'] ?? null) === 'Harga bermula RM99.');
});

it('deduplicates repeated cloud webhook deliveries by wamid', function () {
    fakeGraphOk();
    $session = cloudSession();
    enableBotFor($session, ['default_reply' => 'ok']);

    $payload = cloudInboundPayload('PNID123', 'hello', 'wamid.DUP');
    $this->postJson('/api/cekbot/cloud/webhook', $payload)->assertOk();
    $this->postJson('/api/cekbot/cloud/webhook', $payload)->assertOk();

    expect(CekbotMessage::query()->where('direction', 'in')->count())->toBe(1);
});

it('ignores a cloud webhook for an unknown phone number id', function () {
    cloudSession(); // PNID123

    $this->postJson('/api/cekbot/cloud/webhook', cloudInboundPayload('OTHER_PNID', 'hi'))->assertOk();

    expect(CekbotConversation::query()->count())->toBe(0);
});

it('echoes the hub challenge when the verify token matches', function () {
    cloudSession(['verify_token' => 'my-verify-token']);

    $this->get('/api/cekbot/cloud/webhook?hub_mode=subscribe&hub_verify_token=my-verify-token&hub_challenge=CHAL-123')
        ->assertOk()
        ->assertSee('CHAL-123', false);
});

it('rejects the hub challenge when the verify token is wrong', function () {
    cloudSession(['verify_token' => 'my-verify-token']);

    $this->get('/api/cekbot/cloud/webhook?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=CHAL-123')
        ->assertForbidden();
});

it('rejects a cloud webhook with a bad signature when an app secret is set', function () {
    cloudSession(['app_secret' => 'super-secret']);

    $this->call(
        'POST',
        '/api/cekbot/cloud/webhook',
        [],
        [],
        [],
        ['HTTP_X-Hub-Signature-256' => 'sha256=deadbeef', 'CONTENT_TYPE' => 'application/json'],
        json_encode(cloudInboundPayload('PNID123', 'hi'))
    )->assertStatus(401);

    expect(CekbotConversation::query()->count())->toBe(0);
});

it('accepts a cloud webhook with a valid signature when an app secret is set', function () {
    fakeGraphOk();
    $session = cloudSession(['app_secret' => 'super-secret']);
    enableBotFor($session, ['default_reply' => 'ok']);

    $body = json_encode(cloudInboundPayload('PNID123', 'hi'));
    $signature = 'sha256='.hash_hmac('sha256', $body, 'super-secret');

    $this->call(
        'POST',
        '/api/cekbot/cloud/webhook',
        [],
        [],
        [],
        ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $body
    )->assertOk();

    expect(CekbotMessage::query()->where('direction', 'in')->exists())->toBeTrue();
});

it('routes a shared Meta app webhook to Cekbot when the number is a cloud number', function () {
    fakeGraphOk();
    $session = cloudSession();
    enableBotFor($session, ['default_reply' => 'Terima kasih.']);

    // Arrives at the campaign webhook job (one Meta app serving both surfaces).
    ProcessWhatsAppWebhookJob::dispatchSync(cloudInboundPayload('PNID123', 'assalamualaikum', 'wamid.SHARED'));

    // Landed in Cekbot, not the campaign inbox.
    expect(CekbotMessage::query()->where('direction', 'in')->where('body', 'assalamualaikum')->exists())->toBeTrue()
        ->and(WhatsAppMessage::query()->count())->toBe(0);
});

it('creates a cloud number and auto-generates a verify token', function () {
    $this->actingAs($this->admin)
        ->post(route('cekbot.sessions.store'), [
            'label' => 'Official CS',
            'provider' => 'cloud_api',
            'phone_number_id' => 'PNID999',
            'access_token' => 'EAAG-permanent',
            'phone_number' => '60123456789',
        ])
        ->assertRedirect();

    $session = CekbotSession::query()->where('phone_number_id', 'PNID999')->first();
    expect($session)->not->toBeNull()
        ->and($session->provider)->toBe('cloud_api')
        ->and($session->access_token)->toBe('EAAG-permanent') // decrypted via cast
        ->and($session->verify_token)->not->toBeEmpty();
});

it('requires phone number id and access token for a cloud number', function () {
    $this->actingAs($this->admin)
        ->post(route('cekbot.sessions.store'), [
            'label' => 'Broken',
            'provider' => 'cloud_api',
        ])
        ->assertSessionHasErrors(['phone_number_id', 'access_token']);
});

it('verifies cloud credentials on connect and marks the number working', function () {
    fakeGraphOk();
    $session = cloudSession(['status' => null]);

    $this->actingAs($this->admin)
        ->postJson(route('cekbot.sessions.connect', $session->id))
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'WORKING', 'provider' => 'cloud_api']);

    expect($session->fresh()->status)->toBe('WORKING');
});

it('marks the number failed when Meta rejects the credentials', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]], 401),
    ]);

    $session = cloudSession(['status' => null]);

    $this->actingAs($this->admin)
        ->postJson(route('cekbot.sessions.connect', $session->id))
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'FAILED']);

    expect($session->fresh()->status)->toBe('FAILED');
});
