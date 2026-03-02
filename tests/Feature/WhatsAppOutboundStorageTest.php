<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;

/*
|--------------------------------------------------------------------------
| WhatsApp Outbound Message Storage Tests
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    // Enable WhatsApp with meta provider
    Setting::updateOrCreate(['key' => 'whatsapp_enabled'], ['value' => '1', 'type' => 'string', 'group' => 'whatsapp']);
    Setting::updateOrCreate(['key' => 'whatsapp_provider'], ['value' => 'meta', 'type' => 'string', 'group' => 'whatsapp']);
    Setting::updateOrCreate(['key' => 'meta_phone_number_id'], ['value' => 'test-phone-id', 'type' => 'string', 'group' => 'whatsapp']);
    Setting::updateOrCreate(['key' => 'meta_access_token'], ['value' => 'test-token', 'type' => 'string', 'group' => 'whatsapp']);
});

it('stores outbound text message and creates conversation', function () {
    $service = app(WhatsAppService::class);

    $result = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'text',
        body: 'Hello from test',
        sendResult: ['success' => true, 'message_id' => 'wamid.test123'],
    );

    expect($result['conversation'])->toBeInstanceOf(WhatsAppConversation::class)
        ->and($result['message'])->toBeInstanceOf(WhatsAppMessage::class);

    $conversation = $result['conversation'];
    expect($conversation->phone_number)->toBe('60123456789')
        ->and($conversation->status)->toBe('active')
        ->and($conversation->last_message_preview)->toBe('Hello from test')
        ->and($conversation->last_message_at)->not->toBeNull();

    $message = $result['message'];
    expect($message->direction)->toBe('outbound')
        ->and($message->type)->toBe('text')
        ->and($message->body)->toBe('Hello from test')
        ->and($message->wamid)->toBe('wamid.test123')
        ->and($message->status)->toBe('sent')
        ->and($message->conversation_id)->toBe($conversation->id);
});

it('reuses existing conversation for same phone number', function () {
    $service = app(WhatsAppService::class);

    // First message
    $result1 = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'text',
        body: 'First message',
        sendResult: ['success' => true, 'message_id' => 'wamid.first'],
    );

    // Second message to same number
    $result2 = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'text',
        body: 'Second message',
        sendResult: ['success' => true, 'message_id' => 'wamid.second'],
    );

    expect($result1['conversation']->id)->toBe($result2['conversation']->id);
    expect(WhatsAppConversation::count())->toBe(1);
    expect(WhatsAppMessage::count())->toBe(2);

    $result2['conversation']->refresh();
    expect($result2['conversation']->last_message_preview)->toBe('Second message');
});

it('stores failed outbound message with error', function () {
    $service = app(WhatsAppService::class);

    $result = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'text',
        body: 'Failed message',
        sendResult: ['success' => false, 'message_id' => null, 'error' => 'Account not registered'],
    );

    $message = $result['message'];
    expect($message->status)->toBe('failed')
        ->and($message->error_message)->toBe('Account not registered')
        ->and($message->wamid)->toBeNull();
});

it('stores document message with media metadata', function () {
    $service = app(WhatsAppService::class);

    $result = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'document',
        body: 'certificate.pdf',
        sendResult: ['success' => true, 'message_id' => 'wamid.doc123'],
        mediaUrl: 'https://example.com/cert.pdf',
        mediaMimeType: 'application/pdf',
        mediaFilename: 'certificate.pdf',
    );

    $message = $result['message'];
    expect($message->type)->toBe('document')
        ->and($message->media_url)->toBe('https://example.com/cert.pdf')
        ->and($message->media_mime_type)->toBe('application/pdf')
        ->and($message->media_filename)->toBe('certificate.pdf');
});

it('stores sent_by_user_id when provided', function () {
    $user = User::factory()->create();
    $service = app(WhatsAppService::class);

    $result = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'text',
        body: 'Sent by admin',
        sendResult: ['success' => true, 'message_id' => 'wamid.admin1'],
        sentByUserId: $user->id,
    );

    expect($result['message']->sent_by_user_id)->toBe($user->id);
});

it('links conversation to student by phone number', function () {
    $user = User::factory()->create(['name' => 'Test Student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '0123456789',
    ]);

    $service = app(WhatsAppService::class);

    // Send to international format — should match local format student
    $result = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'text',
        body: 'Hello student',
        sendResult: ['success' => true, 'message_id' => 'wamid.student1'],
    );

    expect($result['conversation']->student_id)->toBe($student->id)
        ->and($result['conversation']->contact_name)->toBe('Test Student');
});

it('uses preview placeholder for non-text messages', function () {
    $service = app(WhatsAppService::class);

    $result = $service->storeOutboundMessage(
        phoneNumber: '60123456789',
        type: 'image',
        body: null,
        sendResult: ['success' => true, 'message_id' => 'wamid.img1'],
        mediaUrl: 'https://example.com/photo.jpg',
    );

    expect($result['conversation']->last_message_preview)->toBe('[image]');
});

/*
|--------------------------------------------------------------------------
| Webhook Verification With Database Settings Tests
|--------------------------------------------------------------------------
*/

it('verifies webhook using database verify_token', function () {
    Setting::updateOrCreate(
        ['key' => 'meta_verify_token'],
        ['value' => 'db-verify-token', 'type' => 'string', 'group' => 'whatsapp']
    );

    // Clear config to ensure it falls through to database
    config(['services.whatsapp.meta.verify_token' => '']);

    $this->get('/api/whatsapp/webhook?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'db-verify-token',
        'hub_challenge' => 'challenge-from-meta',
    ]))->assertOk()->assertSee('challenge-from-meta');
});

it('rejects webhook with wrong database verify_token', function () {
    Setting::updateOrCreate(
        ['key' => 'meta_verify_token'],
        ['value' => 'db-verify-token', 'type' => 'string', 'group' => 'whatsapp']
    );

    config(['services.whatsapp.meta.verify_token' => '']);

    $this->get('/api/whatsapp/webhook?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong-token',
        'hub_challenge' => 'challenge',
    ]))->assertForbidden();
});
