<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotFlow;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['cekbot.typing' => [
        'enabled' => true, 'base_ms' => 700, 'per_char_ms' => 25, 'max_ms' => 3500,
        'media_ms' => 1500, 'after_media_ms' => 3000,
    ]]);
    Sleep::fake();
});

function pacedCloudSession(): CekbotSession
{
    Http::fake(['graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200)]);

    $session = CekbotSession::factory()->create([
        'provider' => CekbotSession::PROVIDER_CLOUD_API,
        'phone_number_id' => 'PNID-PACE',
        'access_token' => 'EAAG-test',
        'phone_number' => '60177884209',
        'status' => CekbotSession::STATUS_WORKING,
    ]);
    CekbotBotSetting::create(['cekbot_session_id' => $session->id, 'bot_enabled' => true]);

    return $session;
}

function pacedFlow(CekbotSession $session): CekbotFlow
{
    return CekbotFlow::create([
        'cekbot_session_id' => $session->id,
        'name' => 'Kelas Solat',
        'is_active' => true,
        'use_ai' => false,
        'match_type' => 'contains',
        'trigger_keywords' => ['kelas solat'],
        'opening_messages' => [
            ['type' => 'text', 'text' => 'Salam Cik'],
            ['type' => 'text', 'text' => 'Untuk join kelas solat ini, Cik tak perlu daftar nama.'],
            ['type' => 'image', 'path' => 'cekbot/opening/poster.jpg', 'caption' => 'Link group'],
            ['type' => 'text', 'text' => 'Cik tak perlu balas mesej ni.'],
        ],
    ]);
}

function pacedCloudInbound(string $body, string $wamid): void
{
    test()->postJson('/api/cekbot/cloud/webhook', [
        'object' => 'whatsapp_business_account',
        'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '60177884209', 'phone_number_id' => 'PNID-PACE'],
            'contacts' => [['profile' => ['name' => 'Ali'], 'wa_id' => '60174874000']],
            'messages' => [['from' => '60174874000', 'id' => $wamid, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $body]]],
        ]]]]],
    ])->assertOk();
}

it('shows typing before each bubble and pauses longer after an image', function () {
    $session = pacedCloudSession();
    $flow = pacedFlow($session);

    pacedCloudInbound('nak join kelas solat', 'wamid.IN1');

    // Bubbles still leave in the configured order.
    expect(CekbotMessage::query()->where('direction', 'out')->orderBy('id')->pluck('type')->all())
        ->toBe(['text', 'text', 'image', 'text']);

    // One typing indicator per bubble, tied to the customer's message.
    $typing = collect(Http::recorded())
        ->filter(fn ($pair) => data_get($pair[0]->data(), 'typing_indicator.type') === 'text');
    expect($typing)->toHaveCount(4)
        ->and($typing->every(fn ($pair) => $pair[0]['message_id'] === 'wamid.IN1'))->toBeTrue();

    // Length-based for text, fixed for the image, then the after-image pause.
    Sleep::assertSequence([
        Sleep::for(700 + mb_strlen('Salam Cik') * 25)->milliseconds(),
        Sleep::for(700 + mb_strlen('Untuk join kelas solat ini, Cik tak perlu daftar nama.') * 25)->milliseconds(),
        Sleep::for(1500)->milliseconds(),
        Sleep::for(3000)->milliseconds(),
    ]);
});

it('caps the typing delay for long messages', function () {
    $session = pacedCloudSession();
    $flow = pacedFlow($session);
    $flow->update(['opening_messages' => [['type' => 'text', 'text' => str_repeat('a', 500)]]]);

    pacedCloudInbound('kelas solat', 'wamid.IN2');

    Sleep::assertSequence([Sleep::for(3500)->milliseconds()]);
});

it('uses WAHA startTyping for unofficial numbers', function () {
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'k']);
    Http::fake(['waha.test/*' => Http::response(['id' => 'out-1'], 201)]);
    $session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $session->id, 'bot_enabled' => true]);
    pacedFlow($session);

    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'm1', 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'kelas solat', 'type' => 'chat', 'notifyName' => 'Ali'],
    ])->assertOk();

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/startTyping') && $r['chatId'] === '60129999999@c.us');
    expect(collect(Http::recorded())->filter(fn ($p) => str_ends_with($p[0]->url(), '/api/startTyping')))->toHaveCount(4);
});

it('sends instantly with no typing when pacing is disabled', function () {
    config(['cekbot.typing.enabled' => false]);
    $session = pacedCloudSession();
    pacedFlow($session);

    pacedCloudInbound('kelas solat', 'wamid.IN3');

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(4);
    Sleep::assertNeverSlept();
    Http::assertNotSent(fn ($r) => data_get($r->data(), 'typing_indicator') !== null);
});
