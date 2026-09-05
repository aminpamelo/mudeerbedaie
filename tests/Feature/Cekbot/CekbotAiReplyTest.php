<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
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
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'ai-out'], 201)]);
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
});

it('replies with an AI answer when AI is enabled and no rule matches', function () {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Boleh, kami buka 9 pagi hingga 6 petang.'], 'finish_reason' => 'stop']],
        ]),
    ]);

    CekbotBotSetting::create([
        'cekbot_session_id' => $this->session->id,
        'bot_enabled' => true,
        'ai_enabled' => true,
    ]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => 'q1', 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'Pukul berapa buka?', 'type' => 'chat'],
    ])->assertOk();

    $out = CekbotMessage::query()->where('direction', 'out')->first();
    expect($out)->not->toBeNull()
        ->and($out->body)->toBe('Boleh, kami buka 9 pagi hingga 6 petang.')
        ->and($out->sent_by_user_id)->toBeNull();
});

it('stays silent when AI is disabled and nothing else matches', function () {
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true, 'ai_enabled' => false]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => 'q2', 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'apa-apa', 'type' => 'chat'],
    ])->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(0);
});
