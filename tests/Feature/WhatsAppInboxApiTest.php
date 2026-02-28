<?php

declare(strict_types=1);

use App\Contracts\WhatsAppProviderInterface;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppManager;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin, 'sanctum');
});

/*
|--------------------------------------------------------------------------
| Conversations List
|--------------------------------------------------------------------------
*/

it('lists conversations', function () {
    WhatsAppConversation::factory()->count(3)->create([
        'last_message_at' => now(),
    ]);

    $this->getJson('/api/admin/whatsapp/conversations')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('filters conversations by status', function () {
    WhatsAppConversation::factory()->create(['status' => 'active', 'last_message_at' => now()]);
    WhatsAppConversation::factory()->create(['status' => 'archived', 'last_message_at' => now()]);

    $this->getJson('/api/admin/whatsapp/conversations?status=active')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('searches conversations by phone number', function () {
    WhatsAppConversation::factory()->create(['phone_number' => '60123456789', 'last_message_at' => now()]);
    WhatsAppConversation::factory()->create(['phone_number' => '60987654321', 'last_message_at' => now()]);

    $this->getJson('/api/admin/whatsapp/conversations?search=60123')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('searches conversations by contact name', function () {
    WhatsAppConversation::factory()->create(['contact_name' => 'Ahmad Ali', 'last_message_at' => now()]);
    WhatsAppConversation::factory()->create(['contact_name' => 'Siti Aminah', 'last_message_at' => now()]);

    $this->getJson('/api/admin/whatsapp/conversations?search=Ahmad')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

/*
|--------------------------------------------------------------------------
| Conversation Show (Messages)
|--------------------------------------------------------------------------
*/

it('shows conversation messages and marks as read', function () {
    $conversation = WhatsAppConversation::factory()->withUnread(5)->create();
    WhatsAppMessage::factory()->count(3)->create(['conversation_id' => $conversation->id]);

    $this->getJson("/api/admin/whatsapp/conversations/{$conversation->id}")
        ->assertSuccessful()
        ->assertJsonCount(3, 'messages.data')
        ->assertJsonPath('conversation.id', $conversation->id);

    $conversation->refresh();
    expect($conversation->unread_count)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Reply
|--------------------------------------------------------------------------
*/

it('replies to a conversation', function () {
    $mockProvider = Mockery::mock(WhatsAppProviderInterface::class);
    $mockProvider->shouldReceive('send')
        ->once()
        ->andReturn([
            'success' => true,
            'message_id' => 'wamid.reply-test',
            'message' => 'Message sent',
        ]);

    $mockManager = Mockery::mock(WhatsAppManager::class);
    $mockManager->shouldReceive('provider')->andReturn($mockProvider);
    $this->app->instance(WhatsAppManager::class, $mockManager);

    $conversation = WhatsAppConversation::factory()->create();

    $this->postJson("/api/admin/whatsapp/conversations/{$conversation->id}/reply", [
        'message' => 'Test reply message',
    ])->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message.body', 'Test reply message');

    expect(WhatsAppMessage::where('conversation_id', $conversation->id)->outbound()->count())->toBe(1);
});

it('validates reply message is required', function () {
    $conversation = WhatsAppConversation::factory()->create();

    $this->postJson("/api/admin/whatsapp/conversations/{$conversation->id}/reply", [
        'message' => '',
    ])->assertUnprocessable();
});

/*
|--------------------------------------------------------------------------
| Archive
|--------------------------------------------------------------------------
*/

it('archives a conversation', function () {
    $conversation = WhatsAppConversation::factory()->create(['status' => 'active']);

    $this->postJson("/api/admin/whatsapp/conversations/{$conversation->id}/archive")
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $conversation->refresh();
    expect($conversation->status)->toBe('archived');
});

/*
|--------------------------------------------------------------------------
| Templates
|--------------------------------------------------------------------------
*/

it('lists approved templates', function () {
    WhatsAppTemplate::factory()->approved()->count(2)->create();
    WhatsAppTemplate::factory()->pending()->create();

    $this->getJson('/api/admin/whatsapp/templates')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

it('requires authentication for inbox endpoints', function () {
    // Reset auth (call as guest)
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/admin/whatsapp/conversations')
        ->assertUnauthorized();
});
