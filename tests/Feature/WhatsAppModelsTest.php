<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;

/*
|--------------------------------------------------------------------------
| WhatsAppConversation Model Tests
|--------------------------------------------------------------------------
*/

it('creates a conversation via factory', function () {
    $conversation = WhatsAppConversation::factory()->create();

    expect($conversation)->toBeInstanceOf(WhatsAppConversation::class)
        ->and($conversation->phone_number)->not->toBeEmpty()
        ->and($conversation->status)->toBe('active');
});

it('conversation has messages relationship', function () {
    $conversation = WhatsAppConversation::factory()->create();
    WhatsAppMessage::factory()->count(3)->create(['conversation_id' => $conversation->id]);

    expect($conversation->messages)->toHaveCount(3);
    expect($conversation->messages->first())->toBeInstanceOf(WhatsAppMessage::class);
});

it('conversation has student relationship', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $conversation = WhatsAppConversation::factory()->create(['student_id' => $student->id]);

    expect($conversation->student)->toBeInstanceOf(Student::class)
        ->and($conversation->student->id)->toBe($student->id);
});

it('filters active conversations', function () {
    WhatsAppConversation::factory()->create(['status' => 'active']);
    WhatsAppConversation::factory()->create(['status' => 'archived']);
    WhatsAppConversation::factory()->create(['status' => 'active']);

    expect(WhatsAppConversation::active()->count())->toBe(2);
});

it('filters archived conversations', function () {
    WhatsAppConversation::factory()->create(['status' => 'active']);
    WhatsAppConversation::factory()->create(['status' => 'archived']);

    expect(WhatsAppConversation::archived()->count())->toBe(1);
});

it('filters conversations with unread messages', function () {
    WhatsAppConversation::factory()->create(['unread_count' => 0]);
    WhatsAppConversation::factory()->create(['unread_count' => 5]);
    WhatsAppConversation::factory()->create(['unread_count' => 2]);

    expect(WhatsAppConversation::withUnread()->count())->toBe(2);
});

it('checks service window is open when not expired', function () {
    $conversation = WhatsAppConversation::factory()->withServiceWindow()->create();

    expect($conversation->isServiceWindowOpen())->toBeTrue();
});

it('checks service window is closed when expired', function () {
    $conversation = WhatsAppConversation::factory()->withExpiredServiceWindow()->create();

    expect($conversation->isServiceWindowOpen())->toBeFalse();
});

it('checks service window is closed when null', function () {
    $conversation = WhatsAppConversation::factory()->create([
        'service_window_expires_at' => null,
    ]);

    expect($conversation->isServiceWindowOpen())->toBeFalse();
});

it('marks conversation as read', function () {
    $conversation = WhatsAppConversation::factory()->withUnread(5)->create();

    expect($conversation->unread_count)->toBe(5);

    $conversation->markAsRead();
    $conversation->refresh();

    expect($conversation->unread_count)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| WhatsAppMessage Model Tests
|--------------------------------------------------------------------------
*/

it('creates a message via factory', function () {
    $message = WhatsAppMessage::factory()->create();

    expect($message)->toBeInstanceOf(WhatsAppMessage::class)
        ->and($message->direction)->toBe('inbound')
        ->and($message->type)->toBe('text');
});

it('message belongs to conversation', function () {
    $conversation = WhatsAppConversation::factory()->create();
    $message = WhatsAppMessage::factory()->create(['conversation_id' => $conversation->id]);

    expect($message->conversation)->toBeInstanceOf(WhatsAppConversation::class)
        ->and($message->conversation->id)->toBe($conversation->id);
});

it('message belongs to sent by user', function () {
    $user = User::factory()->create();
    $message = WhatsAppMessage::factory()->outbound()->create(['sent_by_user_id' => $user->id]);

    expect($message->sentBy)->toBeInstanceOf(User::class)
        ->and($message->sentBy->id)->toBe($user->id);
});

it('filters inbound messages', function () {
    $conversation = WhatsAppConversation::factory()->create();
    WhatsAppMessage::factory()->inbound()->create(['conversation_id' => $conversation->id]);
    WhatsAppMessage::factory()->outbound()->create(['conversation_id' => $conversation->id]);
    WhatsAppMessage::factory()->inbound()->create(['conversation_id' => $conversation->id]);

    expect(WhatsAppMessage::inbound()->count())->toBe(2);
});

it('filters outbound messages', function () {
    $conversation = WhatsAppConversation::factory()->create();
    WhatsAppMessage::factory()->inbound()->create(['conversation_id' => $conversation->id]);
    WhatsAppMessage::factory()->outbound()->create(['conversation_id' => $conversation->id]);

    expect(WhatsAppMessage::outbound()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| WhatsAppTemplate Model Tests
|--------------------------------------------------------------------------
*/

it('creates a template via factory', function () {
    $template = WhatsAppTemplate::factory()->create();

    expect($template)->toBeInstanceOf(WhatsAppTemplate::class)
        ->and($template->status)->toBe('APPROVED')
        ->and($template->language)->toBe('ms')
        ->and($template->category)->toBe('utility');
});

it('filters approved templates', function () {
    WhatsAppTemplate::factory()->approved()->create();
    WhatsAppTemplate::factory()->pending()->create();
    WhatsAppTemplate::factory()->rejected()->create();

    expect(WhatsAppTemplate::approved()->count())->toBe(1);
});

it('filters templates by category', function () {
    WhatsAppTemplate::factory()->marketing()->create();
    WhatsAppTemplate::factory()->utility()->create();
    WhatsAppTemplate::factory()->utility()->create();
    WhatsAppTemplate::factory()->authentication()->create();

    expect(WhatsAppTemplate::byCategory('utility')->count())->toBe(2);
    expect(WhatsAppTemplate::byCategory('marketing')->count())->toBe(1);
});

it('casts components to array', function () {
    $template = WhatsAppTemplate::factory()->create([
        'components' => [
            ['type' => 'BODY', 'text' => 'Hello {{1}}'],
        ],
    ]);

    expect($template->components)->toBeArray()
        ->and($template->components[0]['type'])->toBe('BODY');
});
