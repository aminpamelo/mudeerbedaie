<?php

use App\Contracts\WhatsAppProviderInterface;
use App\Services\WhatsApp\OnsendProvider;
use Illuminate\Support\Facades\Http;

test('OnsendProvider implements WhatsAppProviderInterface', function () {
    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');

    expect($provider)->toBeInstanceOf(WhatsAppProviderInterface::class);
});

test('returns not configured when token is empty', function () {
    $provider = new OnsendProvider('https://onsend.io/api/v1', '');

    expect($provider->isConfigured())->toBeFalse();
});

test('returns configured when token is present', function () {
    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');

    expect($provider->isConfigured())->toBeTrue();
});

test('sends text message successfully', function () {
    Http::fake([
        'onsend.io/api/v1/send' => Http::response([
            'success' => true,
            'message_id' => 'msg-123',
            'message' => 'Message sent successfully',
        ], 200),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->send('60123456789', 'Hello World');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'msg-123')
        ->toHaveKey('message', 'Message sent successfully');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://onsend.io/api/v1/send'
            && $request['phone_number'] === '60123456789'
            && $request['message'] === 'Hello World'
            && $request['type'] === 'text'
            && $request->hasHeader('Authorization', 'Bearer test-token');
    });
});

test('sends image message successfully', function () {
    Http::fake([
        'onsend.io/api/v1/send' => Http::response([
            'success' => true,
            'message_id' => 'img-456',
            'message' => 'Image sent',
        ], 200),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->sendImage('60123456789', 'https://example.com/image.jpg', 'Check this out');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'img-456');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://onsend.io/api/v1/send'
            && $request['phone_number'] === '60123456789'
            && $request['type'] === 'image'
            && $request['url'] === 'https://example.com/image.jpg'
            && $request['message'] === 'Check this out';
    });
});

test('sends image message without caption', function () {
    Http::fake([
        'onsend.io/api/v1/send' => Http::response([
            'success' => true,
            'message_id' => 'img-789',
        ], 200),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->sendImage('60123456789', 'https://example.com/image.jpg');

    expect($result)->toHaveKey('success', true);

    Http::assertSent(function ($request) {
        return $request['type'] === 'image'
            && ! isset($request['message']);
    });
});

test('sends document message successfully', function () {
    Http::fake([
        'onsend.io/api/v1/send' => Http::response([
            'success' => true,
            'message_id' => 'doc-789',
            'message' => 'Document sent',
        ], 200),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->sendDocument(
        '60123456789',
        'https://example.com/document.pdf',
        'application/pdf',
        'invoice.pdf'
    );

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'doc-789');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://onsend.io/api/v1/send'
            && $request['phone_number'] === '60123456789'
            && $request['type'] === 'document'
            && $request['url'] === 'https://example.com/document.pdf'
            && $request['mimetype'] === 'application/pdf'
            && $request['filename'] === 'invoice.pdf';
    });
});

test('sends document message without filename', function () {
    Http::fake([
        'onsend.io/api/v1/send' => Http::response([
            'success' => true,
            'message_id' => 'doc-000',
        ], 200),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->sendDocument(
        '60123456789',
        'https://example.com/document.pdf',
        'application/pdf'
    );

    expect($result)->toHaveKey('success', true);

    Http::assertSent(function ($request) {
        return $request['type'] === 'document'
            && ! isset($request['filename']);
    });
});

test('returns unsupported for sendTemplate', function () {
    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->sendTemplate('60123456789', 'welcome', 'en', []);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'Template messages are not supported by Onsend provider');
});

test('handles API failure gracefully', function () {
    Http::fake([
        'onsend.io/api/v1/send' => Http::response([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->send('60123456789', 'Hello');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error');
});

test('handles API exception gracefully', function () {
    Http::fake([
        'onsend.io/api/v1/send' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->send('60123456789', 'Hello');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error');
});

test('returns onsend as provider name', function () {
    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');

    expect($provider->getName())->toBe('onsend');
});

test('checkStatus returns correct shape on success', function () {
    Http::fake([
        'onsend.io/api/v1/status' => Http::response([
            'status' => 'connected',
            'message' => 'Device is connected',
        ], 200),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->checkStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('status', 'connected')
        ->toHaveKey('message', 'Device is connected');
});

test('checkStatus returns failure when not configured', function () {
    $provider = new OnsendProvider('https://onsend.io/api/v1', '');
    $result = $provider->checkStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('status', 'not_configured');
});

test('checkStatus handles API error', function () {
    Http::fake([
        'onsend.io/api/v1/status' => Http::response([], 500),
    ]);

    $provider = new OnsendProvider('https://onsend.io/api/v1', 'test-token');
    $result = $provider->checkStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('status', 'error');
});
