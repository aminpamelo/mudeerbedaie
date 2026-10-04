<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key']);
    Http::fake(['waha.test/*' => Http::response(['id' => 'bot-out'], 201)]);

    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    $this->settings = CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true]);

    $this->flow = CekbotFlow::create([
        'cekbot_session_id' => $this->session->id,
        'name' => 'Funnel Qadha',
        'is_active' => true,
        'use_ai' => false,
        'match_type' => 'contains',
        'trigger_keywords' => ['minat'],
        'welcome_message' => 'Salam! Ni pakej kami.',
    ]);
    CekbotFlowPackage::create(['cekbot_flow_id' => $this->flow->id, 'label' => 'Pakej 1', 'price' => 49, 'currency' => 'RM', 'sort_order' => 0]);
});

function resetTestInbound(string $body, string $id): void
{
    test()->postJson('/api/cekbot/webhook', [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => $id, 'from' => '60129999999@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => $body, 'type' => 'chat', 'notifyName' => 'Tester'],
    ])->assertOk();
}

function resetTestConversation(): CekbotConversation
{
    return CekbotConversation::query()->sole();
}

it('lets the keyword start the flow again after a reset', function () {
    resetTestInbound('saya minat', 'm1');
    $this->travel(5)->seconds();
    resetTestInbound('saya minat', 'm2');

    // Without a reset the second "minat" stays inside the first enrollment.
    expect(CekbotFlowEnrollment::query()->count())->toBe(1);

    $this->travel(5)->seconds();
    test()->actingAs($this->admin)
        ->post(route('cekbot.inbox.reset', resetTestConversation()->id))
        ->assertRedirect();

    expect(CekbotFlowEnrollment::query()->sole()->status)->toBe(CekbotFlowEnrollment::STATUS_ABANDONED);

    $this->travel(5)->seconds();
    resetTestInbound('saya minat', 'm3');

    expect(CekbotFlowEnrollment::query()->count())->toBe(2)
        ->and(CekbotFlowEnrollment::query()->latest('id')->first()->status)->toBe(CekbotFlowEnrollment::STATUS_ACTIVE)
        ->and(CekbotMessage::query()->where('direction', 'out')->where('body', 'like', 'Salam! Ni pakej kami.%')->count())->toBe(2);
});

it('hands a taken-over chat back to the bot on reset', function () {
    resetTestInbound('hai', 'm1');
    resetTestConversation()->update(['handed_over_at' => now(), 'handed_over_by' => $this->admin->id]);

    test()->actingAs($this->admin)->post(route('cekbot.inbox.reset', resetTestConversation()->id));

    $conversation = resetTestConversation();
    expect($conversation->handed_over_at)->toBeNull()
        ->and($conversation->handed_over_by)->toBeNull()
        ->and($conversation->context_reset_at)->not->toBeNull();

    $this->travel(5)->seconds();
    resetTestInbound('saya minat', 'm2');

    expect(CekbotFlowEnrollment::query()->where('status', CekbotFlowEnrollment::STATUS_ACTIVE)->exists())->toBeTrue();
});

it('sends the welcome message again on the first message after a reset', function () {
    $this->flow->update(['is_active' => false]);
    $this->settings->update(['welcome_message' => 'Selamat datang!']);

    resetTestInbound('hai', 'm1');
    $this->travel(5)->seconds();
    resetTestInbound('hai lagi', 'm2');

    expect(CekbotMessage::query()->where('direction', 'out')->where('body', 'Selamat datang!')->count())->toBe(1);

    $this->travel(5)->seconds();
    test()->actingAs($this->admin)->post(route('cekbot.inbox.reset', resetTestConversation()->id));
    $this->travel(5)->seconds();
    resetTestInbound('hai semula', 'm3');

    expect(CekbotMessage::query()->where('direction', 'out')->where('body', 'Selamat datang!')->count())->toBe(2);
});

it('hides messages before the reset from the bot but keeps them in the inbox', function () {
    $this->flow->update(['is_active' => false]);
    resetTestInbound('mesej lama', 'm1');
    $this->travel(5)->seconds();
    test()->actingAs($this->admin)->post(route('cekbot.inbox.reset', resetTestConversation()->id));
    $this->travel(5)->seconds();
    resetTestInbound('mesej baru', 'm2');

    $conversation = resetTestConversation();
    expect($conversation->botContextMessages()->pluck('body')->all())->toBe(['mesej baru'])
        ->and($conversation->messages()->where('direction', 'in')->count())->toBe(2);

    test()->actingAs($this->admin)
        ->getJson(route('cekbot.inbox.messages', $conversation->id))
        ->assertOk()
        ->assertJsonPath('conversation.context_reset_at', $conversation->context_reset_at->toIso8601String());
});

it('forbids non-admins from resetting a conversation', function () {
    resetTestInbound('hai', 'm1');

    test()->actingAs(User::factory()->create())
        ->post(route('cekbot.inbox.reset', resetTestConversation()->id))
        ->assertForbidden();

    expect(resetTestConversation()->context_reset_at)->toBeNull();
});
