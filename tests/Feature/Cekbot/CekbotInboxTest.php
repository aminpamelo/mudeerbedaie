<?php

use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
    ]);
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
});

function inboundEvent(string $session, string $from, string $body, string $id): array
{
    return [
        'event' => 'message',
        'session' => $session,
        'payload' => [
            'id' => $id,
            'from' => $from,
            'fromMe' => false,
            'to' => '60111@c.us',
            'body' => $body,
            'type' => 'chat',
            'notifyName' => 'Ali',
            'timestamp' => 1788520000,
        ],
    ];
}

it('stores an inbound message from a WAHA webhook', function () {
    $this->postJson('/api/cekbot/webhook', inboundEvent('default', '60129999999@c.us', 'Salam, nak tanya', 'msg-1'))
        ->assertOk();

    $conversation = CekbotConversation::query()->where('chat_id', '60129999999@c.us')->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->cekbot_session_id)->toBe($this->session->id)
        ->and($conversation->unread_count)->toBe(1)
        ->and($conversation->name)->toBe('Ali')
        ->and($conversation->last_message_preview)->toBe('Salam, nak tanya');

    $this->assertDatabaseHas('cekbot_messages', [
        'waha_message_id' => 'msg-1',
        'direction' => 'in',
        'body' => 'Salam, nak tanya',
    ]);
});

it('is idempotent on duplicate webhook deliveries', function () {
    $event = inboundEvent('default', '60128888888@c.us', 'Hello', 'dup-1');
    $this->postJson('/api/cekbot/webhook', $event)->assertOk();
    $this->postJson('/api/cekbot/webhook', $event)->assertOk();

    expect(CekbotMessage::query()->where('waha_message_id', 'dup-1')->count())->toBe(1);
});

it('ignores webhooks for unknown sessions', function () {
    $this->postJson('/api/cekbot/webhook', inboundEvent('ghost-session', '60127777777@c.us', 'Hi', 'x-1'))
        ->assertOk();

    expect(CekbotConversation::query()->count())->toBe(0);
});

it('updates session status from a session.status event', function () {
    $this->postJson('/api/cekbot/webhook', [
        'event' => 'session.status',
        'session' => 'default',
        'payload' => ['status' => 'STOPPED'],
    ])->assertOk();

    expect($this->session->fresh()->status)->toBe('STOPPED');
});

it('rejects a webhook with a bad HMAC signature when a secret is set', function () {
    app(\App\Services\SettingsService::class)->set('cekbot_webhook_secret', 'shh');

    $this->postJson('/api/cekbot/webhook', inboundEvent('default', '60121111111@c.us', 'Hi', 'h-1'), [
        'X-Webhook-Hmac' => 'wrong',
    ])->assertStatus(401);

    expect(CekbotConversation::query()->count())->toBe(0);
});

it('renders the inbox for admins', function () {
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Inbox/Index', false)->has('conversations', 1));
});

it('returns messages and marks the conversation read', function () {
    $conversation = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'unread_count' => 3]);
    CekbotMessage::factory()->create(['cekbot_conversation_id' => $conversation->id, 'cekbot_session_id' => $this->session->id]);

    $this->actingAs($this->admin)
        ->getJson("/admin/cekbot/inbox/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonCount(1, 'messages');

    expect($conversation->fresh()->unread_count)->toBe(0);
});

it('sends a reply through WAHA and stores it outbound', function () {
    Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'out-1'], 201)]);
    $conversation = CekbotConversation::factory()->create([
        'cekbot_session_id' => $this->session->id,
        'chat_id' => '60126666666@c.us',
    ]);

    $this->actingAs($this->admin)
        ->postJson("/admin/cekbot/inbox/{$conversation->id}/reply", ['message' => 'Terima kasih!'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('cekbot_messages', [
        'cekbot_conversation_id' => $conversation->id,
        'direction' => 'out',
        'body' => 'Terima kasih!',
        'sent_by_user_id' => $this->admin->id,
    ]);
    Http::assertSent(fn ($req) => str_contains($req->url(), '/api/sendText')
        && $req['chatId'] === '60126666666@c.us' && $req['text'] === 'Terima kasih!');
});

it('skips WhatsApp status/broadcast noise', function () {
    Http::fake(['waha.test/*' => Http::response([], 200)]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'st1', 'from' => 'status@broadcast', 'fromMe' => false, 'body' => 'x', 'type' => 'chat'],
    ])->assertOk();

    expect(CekbotConversation::query()->count())->toBe(0);
});

it('resolves a contact name from WAHA when notifyName is absent', function () {
    Http::fake([
        'waha.test/api/contacts*' => Http::response(['id' => '60129@c.us', 'name' => '', 'pushname' => 'Afiq'], 200),
        'waha.test/*' => Http::response([], 200),
    ]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'n1', 'from' => '60129@c.us', 'fromMe' => false, 'body' => 'hi', 'type' => 'chat'],
    ])->assertOk();

    expect(CekbotConversation::where('chat_id', '60129@c.us')->value('name'))->toBe('Afiq');
});

it('labels a media message that has no text body', function () {
    Http::fake(['waha.test/*' => Http::response([], 200)]);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'md1', 'from' => '60128@c.us', 'fromMe' => false, 'type' => 'image', 'hasMedia' => true, 'media' => ['mimetype' => 'image/jpeg']],
    ])->assertOk();

    $this->assertDatabaseHas('cekbot_messages', ['waha_message_id' => 'md1', 'type' => 'image']);
    expect(CekbotConversation::where('chat_id', '60128@c.us')->value('last_message_preview'))->toContain('Gambar');
});

it('assigns a conversation to a staff member', function () {
    $staff = User::factory()->create();
    $c = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id]);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/inbox/{$c->id}/assign", ['assigned_to' => $staff->id])
        ->assertRedirect();

    expect($c->fresh()->assigned_to)->toBe($staff->id);
});

it('sets allowed labels and rejects unknown ones', function () {
    $c = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id]);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/inbox/{$c->id}/labels", ['labels' => ['penting', 'selesai']])
        ->assertRedirect();
    expect($c->fresh()->labels)->toBe(['penting', 'selesai']);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/inbox/{$c->id}/labels", ['labels' => ['bogus']])
        ->assertSessionHasErrors('labels.0');
});

it('adds an internal note', function () {
    $c = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id]);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/inbox/{$c->id}/notes", ['body' => 'Ikut up esok'])
        ->assertRedirect();

    $this->assertDatabaseHas('cekbot_conversation_notes', [
        'cekbot_conversation_id' => $c->id, 'body' => 'Ikut up esok', 'user_id' => $this->admin->id,
    ]);
});

it('proxies media hosted on the WAHA server', function () {
    Http::fake(['waha.test/api/files/*' => Http::response('IMGDATA', 200, ['Content-Type' => 'image/jpeg'])]);
    $c = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id]);
    $m = CekbotMessage::factory()->create([
        'cekbot_conversation_id' => $c->id, 'cekbot_session_id' => $this->session->id,
        'type' => 'image', 'media_url' => 'https://waha.test/api/files/default/x.jpeg',
    ]);

    $res = $this->actingAs($this->admin)->get("/admin/cekbot/inbox/messages/{$m->id}/media");

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('image/jpeg')
        ->and($res->getContent())->toBe('IMGDATA');
});

it('refuses to proxy media from a foreign host (SSRF guard)', function () {
    $c = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id]);
    $m = CekbotMessage::factory()->create([
        'cekbot_conversation_id' => $c->id, 'cekbot_session_id' => $this->session->id,
        'type' => 'image', 'media_url' => 'https://evil.example.com/x.jpeg',
    ]);

    $this->actingAs($this->admin)
        ->get("/admin/cekbot/inbox/messages/{$m->id}/media")
        ->assertNotFound();
});
