<?php

use App\Models\CekbotAutoReply;
use App\Models\CekbotBotSetting;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key']);
    Http::fake(['waha.test/*' => Http::response(['id' => 'bot-out'], 201)]);

    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    $this->settings = CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);
});

function skipInbound(string $body, string $id, string $from = '60129999999@c.us'): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => $id, 'from' => $from, 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat', 'notifyName' => 'Ali'],
    ])->assertOk();
}

function lastInboundSkipReason(): ?string
{
    return CekbotMessage::query()->where('direction', 'in')->latest('id')->value('bot_skip_reason');
}

it('records no_match when nothing in the setup answers the message', function () {
    skipInbound('qadha solat', 'm1');

    expect(lastInboundSkipReason())->toBe(CekbotMessage::SKIP_NO_MATCH)
        ->and(CekbotMessage::query()->where('direction', 'out')->exists())->toBeFalse();
});

it('records handed_over when an admin has taken over the chat', function () {
    skipInbound('hai', 'm1');
    CekbotConversation::query()->sole()->update(['handed_over_at' => now()]);

    skipInbound('hai lagi', 'm2');

    expect(lastInboundSkipReason())->toBe(CekbotMessage::SKIP_HANDED_OVER);
});

it('records bot_disabled when the bot is switched off', function () {
    $this->settings->update(['bot_enabled' => false]);

    skipInbound('hai', 'm1');

    expect(lastInboundSkipReason())->toBe(CekbotMessage::SKIP_BOT_DISABLED);
});

it('records not_test_number in test mode for numbers outside the list', function () {
    $this->settings->update(['test_mode' => true, 'test_numbers' => ['60111111111']]);

    skipInbound('hai', 'm1');

    expect(lastInboundSkipReason())->toBe(CekbotMessage::SKIP_NOT_TEST_NUMBER);
});

it('leaves no skip reason when the bot replies', function () {
    CekbotAutoReply::factory()->create(['cekbot_session_id' => $this->session->id, 'keywords' => ['harga'], 'reply_body' => 'RM49']);

    skipInbound('berapa harga', 'm1');

    expect(lastInboundSkipReason())->toBeNull()
        ->and(CekbotMessage::query()->where('direction', 'out')->value('body'))->toBe('RM49');
});

it('exposes the skip reason to the inbox', function () {
    skipInbound('qadha solat', 'm1');

    test()->actingAs(User::factory()->admin()->create())
        ->getJson(route('cekbot.inbox.messages', CekbotConversation::query()->sole()->id))
        ->assertOk()
        ->assertJsonPath('messages.0.bot_skip_reason', CekbotMessage::SKIP_NO_MATCH);
});
