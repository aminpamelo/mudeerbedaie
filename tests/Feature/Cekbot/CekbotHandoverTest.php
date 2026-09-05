<?php

use App\Models\CekbotBotSetting;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key']);
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'ho-out'], 201)]);
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    CekbotBotSetting::create(['cekbot_session_id' => $this->session->id, 'bot_enabled' => true, 'default_reply' => 'Auto reply.']);
});

function handoverInbound(string $id, string $from = '60123@c.us'): array
{
    return [
        'event' => 'message',
        'session' => 'default',
        'payload' => ['id' => $id, 'from' => $from, 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'hello', 'type' => 'chat'],
    ];
}

it('pauses the bot for a handed-over conversation', function () {
    CekbotConversation::factory()->create([
        'cekbot_session_id' => $this->session->id,
        'chat_id' => '60123@c.us',
        'handed_over_at' => now(),
        'handed_over_by' => $this->admin->id,
    ]);

    test()->postJson('/api/cekbot/webhook', handoverInbound('h1'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->count())->toBe(0);
});

it('resumes the bot after release', function () {
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'chat_id' => '60123@c.us']);

    test()->postJson('/api/cekbot/webhook', handoverInbound('h2'))->assertOk();

    expect(CekbotMessage::query()->where('direction', 'out')->where('body', 'Auto reply.')->count())->toBe(1);
});

it('auto-hands over when a human replies', function () {
    $conversation = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'chat_id' => '60123@c.us']);

    test()->actingAs($this->admin)
        ->postJson("/admin/cekbot/inbox/{$conversation->id}/reply", ['message' => 'Hi from human'])
        ->assertOk();

    expect($conversation->fresh()->handed_over_at)->not->toBeNull()
        ->and($conversation->fresh()->handed_over_by)->toBe($this->admin->id);
});

it('releases a conversation back to the bot', function () {
    $conversation = CekbotConversation::factory()->create([
        'cekbot_session_id' => $this->session->id,
        'handed_over_at' => now(),
        'handed_over_by' => $this->admin->id,
    ]);

    test()->actingAs($this->admin)
        ->post("/admin/cekbot/inbox/{$conversation->id}/release")
        ->assertRedirect();

    expect($conversation->fresh()->handed_over_at)->toBeNull();
});

it('renders the analytics dashboard', function () {
    test()->actingAs($this->admin)
        ->get('/admin/cekbot/analytics')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Analytics/Index', false)->has('stats')->has('series', 7));
});
