<?php

use App\Events\Cekbot\CekbotMessageReceived;
use App\Models\CekbotConversation;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.waha.api_url' => 'https://waha.test', 'services.waha.api_key' => 'test-key']);
    Http::fake(['waha.test/*' => Http::response(['id' => 'rt-out'], 201)]);
});

it('broadcasts a real-time event when an inbound message is stored', function () {
    Event::fake([CekbotMessageReceived::class]);
    CekbotSession::factory()->working()->create(['session_name' => 'default']);

    $this->postJson('/api/cekbot/webhook', [
        'event' => 'message', 'session' => 'default',
        'payload' => ['id' => 'rt1', 'from' => '60123@c.us', 'fromMe' => false, 'to' => '60111@c.us', 'body' => 'hi', 'type' => 'chat'],
    ])->assertOk();

    Event::assertDispatched(CekbotMessageReceived::class, fn ($e) => $e->direction === 'in');
});

it('broadcasts when an admin replies from the inbox', function () {
    Event::fake([CekbotMessageReceived::class]);
    $admin = User::factory()->admin()->create();
    $session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
    $conversation = CekbotConversation::factory()->create(['cekbot_session_id' => $session->id, 'is_group' => false]);

    $this->actingAs($admin)
        ->postJson("/admin/cekbot/inbox/{$conversation->id}/reply", ['message' => 'Salam!'])
        ->assertOk();

    Event::assertDispatched(CekbotMessageReceived::class, fn ($e) => $e->direction === 'out' && $e->conversationId === $conversation->id);
});

it('gives the event a private cekbot-inbox channel', function () {
    $event = new CekbotMessageReceived(1, 2, 'in');

    expect($event->broadcastOn()->name)->toBe('private-cekbot-inbox')
        ->and($event->broadcastAs())->toBe('message.new')
        ->and($event->broadcastWith())->toBe(['conversation_id' => 1, 'session_id' => 2, 'direction' => 'in']);
});
