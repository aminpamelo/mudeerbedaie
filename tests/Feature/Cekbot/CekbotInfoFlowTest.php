<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

beforeEach(function () {
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key', 'openai.api_key' => 'sk-test']);
    Http::fake(['waha.test/*' => Http::response(['id' => 'out-1'], 201)]);
    OpenAI::fake([]);

    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);

    // Mirrors the production "[TARIK KELAS] Kelas Solat" flow: AI on, no packages.
    $this->flow = CekbotFlow::create([
        'cekbot_session_id' => $this->session->id,
        'name' => '[TARIK KELAS] Kelas Solat',
        'is_active' => true,
        'use_ai' => true,
        'match_type' => 'contains',
        'trigger_keywords' => ['Join Kelas Solat', 'Kelas Solat'],
        'ai_instructions' => 'jangan jawab apa-apa soalan jika ditanya.',
        'opening_messages' => [
            ['type' => 'text', 'text' => 'Salam Cik.. Terima kasih berminat dengan Kelas Solat Online Bedaie'],
            ['type' => 'text', 'text' => 'https://chat.whatsapp.com/LwUTbh4uYQcIYoExiDJpSK'],
        ],
    ]);
});

function infoInbound(string $body, string $id): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => $id, 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat', 'notifyName' => 'Ali'],
    ])->assertOk();
}

it('sends the opening messages of a flow with no packages and closes it', function () {
    infoInbound('Join Kelas Solat', 'm1');

    expect(CekbotMessage::query()->where('direction', 'out')->orderBy('id')->pluck('body')->all())->toBe([
        'Salam Cik.. Terima kasih berminat dengan Kelas Solat Online Bedaie',
        'https://chat.whatsapp.com/LwUTbh4uYQcIYoExiDJpSK',
    ]);

    $enrollment = CekbotFlowEnrollment::query()->sole();
    expect($enrollment->status)->toBe(CekbotFlowEnrollment::STATUS_COMPLETED)
        ->and($enrollment->completed_at)->not->toBeNull()
        ->and(CekbotMessage::query()->where('direction', 'in')->value('bot_skip_reason'))->toBeNull();

    OpenAI::assertNothingSent();
});

it('does not hold later messages in the info flow', function () {
    infoInbound('Kelas Solat', 'm1');
    infoInbound('bila kelas mula?', 'm2');

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(2)
        ->and(CekbotMessage::query()->where('direction', 'in')->latest('id')->value('bot_skip_reason'))->toBe(CekbotMessage::SKIP_NO_MATCH);
    OpenAI::assertNothingSent();
});

it('sends the link again when the keyword is sent again', function () {
    infoInbound('Kelas Solat', 'm1');
    infoInbound('Kelas Solat', 'm2');

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(4)
        ->and(CekbotFlowEnrollment::query()->count())->toBe(2);
});

it('ignores a flow that has neither packages nor an opening message', function () {
    $this->flow->update(['opening_messages' => []]);

    infoInbound('Kelas Solat', 'm1');

    expect(CekbotFlowEnrollment::query()->count())->toBe(0)
        ->and(CekbotMessage::query()->where('direction', 'in')->value('bot_skip_reason'))->toBe(CekbotMessage::SKIP_NO_MATCH);
});
