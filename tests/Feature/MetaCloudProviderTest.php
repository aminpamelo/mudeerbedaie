<?php

use App\Contracts\WhatsAppProviderInterface;
use App\Services\WhatsApp\MetaCloudProvider;
use Illuminate\Support\Facades\Http;

test('MetaCloudProvider implements WhatsAppProviderInterface', function () {
    $provider = new MetaCloudProvider('123456', 'test-access-token');

    expect($provider)->toBeInstanceOf(WhatsAppProviderInterface::class);
});

test('is not configured without access token', function () {
    $provider = new MetaCloudProvider('123456', '');

    expect($provider->isConfigured())->toBeFalse();
});

test('is not configured without phone number id', function () {
    $provider = new MetaCloudProvider('', 'test-access-token');

    expect($provider->isConfigured())->toBeFalse();
});

test('is configured with both phone number id and access token', function () {
    $provider = new MetaCloudProvider('123456', 'test-access-token');

    expect($provider->isConfigured())->toBeTrue();
});

test('sends text message successfully', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'messages' => [['id' => 'wamid.abc123']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->send('60123456789', 'Hello World');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'wamid.abc123')
        ->toHaveKey('message', 'Message sent');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '60123456789'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Hello World'
            && $request->hasHeader('Authorization', 'Bearer test-access-token');
    });
});

test('sends image with caption', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'messages' => [['id' => 'wamid.img456']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->sendImage('60123456789', 'https://example.com/image.jpg', 'Check this out');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'wamid.img456');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '60123456789'
            && $request['type'] === 'image'
            && $request['image']['link'] === 'https://example.com/image.jpg'
            && $request['image']['caption'] === 'Check this out';
    });
});

test('sends image without caption omits caption field', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'messages' => [['id' => 'wamid.img789']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->sendImage('60123456789', 'https://example.com/image.jpg');

    expect($result)->toHaveKey('success', true);

    Http::assertSent(function ($request) {
        return $request['type'] === 'image'
            && $request['image']['link'] === 'https://example.com/image.jpg'
            && ! isset($request['image']['caption']);
    });
});

test('sends document with filename', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'messages' => [['id' => 'wamid.doc001']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->sendDocument(
        '60123456789',
        'https://example.com/document.pdf',
        'application/pdf',
        'invoice.pdf'
    );

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'wamid.doc001');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '60123456789'
            && $request['type'] === 'document'
            && $request['document']['link'] === 'https://example.com/document.pdf'
            && $request['document']['filename'] === 'invoice.pdf';
    });
});

test('sends document without filename uses default', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'messages' => [['id' => 'wamid.doc002']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->sendDocument(
        '60123456789',
        'https://example.com/document.pdf',
        'application/pdf'
    );

    expect($result)->toHaveKey('success', true);

    Http::assertSent(function ($request) {
        return $request['document']['filename'] === 'document';
    });
});

test('sends template message', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'messages' => [['id' => 'wamid.tpl001']],
        ], 200),
    ]);

    $components = [
        [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'Ahmad'],
            ],
        ],
    ];

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->sendTemplate('60123456789', 'welcome', 'en', $components);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'wamid.tpl001');

    Http::assertSent(function ($request) use ($components) {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '60123456789'
            && $request['type'] === 'template'
            && $request['template']['name'] === 'welcome'
            && $request['template']['language']['code'] === 'en'
            && $request['template']['components'] === $components;
    });
});

test('handles Meta API 401 error response', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'error' => [
                'message' => 'Invalid OAuth access token.',
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ], 401),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->send('60123456789', 'Hello');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'Invalid OAuth access token.');
});

test('handles Meta API 400 error with error object', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => Http::response([
            'error' => [
                'message' => '(#131030) Recipient phone number not in allowed list',
                'type' => 'OAuthException',
                'code' => 131030,
            ],
        ], 400),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->send('60123456789', 'Hello');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', '(#131030) Recipient phone number not in allowed list');
});

test('handles connection exception', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456/messages' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->send('60123456789', 'Hello');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'Connection timed out');
});

test('returns meta as provider name', function () {
    $provider = new MetaCloudProvider('123456', 'test-access-token');

    expect($provider->getName())->toBe('meta');
});

test('checkStatus returns connected on success', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456' => Http::response([
            'id' => '123456',
            'display_phone_number' => '+60123456789',
            'verified_name' => 'Test Business',
        ], 200),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->checkStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('status', 'connected')
        ->toHaveKey('message', 'Meta Cloud API connected');
});

test('checkStatus returns not_configured when not configured', function () {
    $provider = new MetaCloudProvider('', '');
    $result = $provider->checkStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('status', 'not_configured')
        ->toHaveKey('message', 'Meta Cloud API credentials not configured');
});

test('checkStatus handles API error', function () {
    Http::fake([
        'graph.facebook.com/v21.0/123456' => Http::response([
            'error' => [
                'message' => 'Invalid token',
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ], 401),
    ]);

    $provider = new MetaCloudProvider('123456', 'test-access-token');
    $result = $provider->checkStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('status', 'error');
});
