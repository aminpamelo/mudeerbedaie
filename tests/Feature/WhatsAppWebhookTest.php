<?php

declare(strict_types=1);

use App\Jobs\ProcessWhatsAppWebhookJob;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| WhatsApp Webhook Controller Tests
|--------------------------------------------------------------------------
*/

it('verifies webhook with correct token', function () {
    config(['services.whatsapp.meta.verify_token' => 'my-verify-token']);

    $this->get('/api/whatsapp/webhook?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'my-verify-token',
        'hub_challenge' => 'challenge-string-123',
    ]))->assertOk()->assertSee('challenge-string-123');
});

it('rejects webhook with wrong token', function () {
    config(['services.whatsapp.meta.verify_token' => 'my-verify-token']);

    $this->get('/api/whatsapp/webhook?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong-token',
        'hub_challenge' => 'challenge-string-123',
    ]))->assertForbidden();
});

it('accepts valid webhook POST and dispatches job', function () {
    Queue::fake();

    config(['services.whatsapp.meta.app_secret' => 'test-secret']);

    $payload = json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => [['id' => '123', 'changes' => []]],
    ]);

    $signature = 'sha256='.hash_hmac('sha256', $payload, 'test-secret');

    $this->postJson('/api/whatsapp/webhook', json_decode($payload, true), [
        'X-Hub-Signature-256' => $signature,
    ])->assertOk();

    Queue::assertPushed(ProcessWhatsAppWebhookJob::class);
});

it('rejects webhook POST with invalid signature', function () {
    config(['services.whatsapp.meta.app_secret' => 'test-secret']);

    $this->postJson('/api/whatsapp/webhook', ['object' => 'test'], [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| ProcessWhatsAppWebhookJob Tests
|--------------------------------------------------------------------------
*/

it('updates notification log status to delivered', function () {
    $log = NotificationLog::factory()->create([
        'channel' => 'whatsapp',
        'message_id' => 'wamid.abc123',
        'status' => 'sent',
    ]);

    $payload = [
        'entry' => [[
            'id' => '123',
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.abc123',
                        'status' => 'delivered',
                        'timestamp' => (string) now()->timestamp,
                        'recipient_id' => '60123456789',
                    ]],
                ],
                'field' => 'messages',
            ]],
        ]],
    ];

    (new ProcessWhatsAppWebhookJob($payload))->handle();

    $log->refresh();
    expect($log->status)->toBe('delivered');
    expect($log->delivered_at)->not->toBeNull();
});

it('updates notification log status to read', function () {
    $log = NotificationLog::factory()->create([
        'channel' => 'whatsapp',
        'message_id' => 'wamid.def456',
        'status' => 'delivered',
    ]);

    $payload = [
        'entry' => [[
            'id' => '123',
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.def456',
                        'status' => 'read',
                        'timestamp' => (string) now()->timestamp,
                        'recipient_id' => '60123456789',
                    ]],
                ],
                'field' => 'messages',
            ]],
        ]],
    ];

    (new ProcessWhatsAppWebhookJob($payload))->handle();

    $log->refresh();
    expect($log->status)->toBe('read');
});

it('updates notification log status to failed', function () {
    $log = NotificationLog::factory()->create([
        'channel' => 'whatsapp',
        'message_id' => 'wamid.fail789',
        'status' => 'sent',
    ]);

    $payload = [
        'entry' => [[
            'id' => '123',
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.fail789',
                        'status' => 'failed',
                        'timestamp' => (string) now()->timestamp,
                        'recipient_id' => '60123456789',
                        'errors' => [[
                            'code' => 131047,
                            'title' => 'Re-engagement message',
                        ]],
                    ]],
                ],
                'field' => 'messages',
            ]],
        ]],
    ];

    (new ProcessWhatsAppWebhookJob($payload))->handle();

    $log->refresh();
    expect($log->status)->toBe('failed');
    expect($log->error_message)->toContain('131047');
});

it('skips unknown message ids gracefully', function () {
    $payload = [
        'entry' => [[
            'id' => '123',
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.unknown999',
                        'status' => 'delivered',
                        'timestamp' => (string) now()->timestamp,
                        'recipient_id' => '60123456789',
                    ]],
                ],
                'field' => 'messages',
            ]],
        ]],
    ];

    // Should not throw
    (new ProcessWhatsAppWebhookJob($payload))->handle();
    expect(true)->toBeTrue();
});

it('handles incoming message payload without error', function () {
    $payload = [
        'entry' => [[
            'id' => '123',
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'id' => 'wamid.incoming123',
                        'from' => '60123456789',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'Hello!'],
                    ]],
                    'contacts' => [[
                        'profile' => ['name' => 'Test User'],
                        'wa_id' => '60123456789',
                    ]],
                ],
                'field' => 'messages',
            ]],
        ]],
    ];

    // Should not throw - incoming messages will be handled in Task 4.3
    (new ProcessWhatsAppWebhookJob($payload))->handle();
    expect(true)->toBeTrue();
});
