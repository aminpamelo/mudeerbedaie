<?php

use App\Contracts\WhatsAppProviderInterface;
use App\Services\WhatsApp\WahaProvider;
use Illuminate\Support\Facades\Http;

test('WahaProvider implements WhatsAppProviderInterface', function () {
    $provider = new WahaProvider('https://waha.test', 'test-key');

    expect($provider)->toBeInstanceOf(WhatsAppProviderInterface::class);
});

test('returns not configured when api key is empty', function () {
    $provider = new WahaProvider('https://waha.test', '');

    expect($provider->isConfigured())->toBeFalse();
});

test('returns not configured when url is empty', function () {
    $provider = new WahaProvider('', 'test-key');

    expect($provider->isConfigured())->toBeFalse();
});

test('returns configured when url and key are present', function () {
    $provider = new WahaProvider('https://waha.test', 'test-key');

    expect($provider->isConfigured())->toBeTrue();
});

test('reports provider name as waha', function () {
    $provider = new WahaProvider('https://waha.test', 'test-key');

    expect($provider->getName())->toBe('waha');
});

test('sends text message successfully', function () {
    Http::fake([
        'waha.test/api/sendText' => Http::response([
            'id' => 'true_60123456789@c.us_3EB05606F814D125',
            '_data' => ['Info' => ['IsFromMe' => true]],
        ], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key', 'mudeer');
    $result = $provider->send('60123456789', 'Hello World');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'true_60123456789@c.us_3EB05606F814D125');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://waha.test/api/sendText'
            && $request['session'] === 'mudeer'
            && $request['chatId'] === '60123456789@c.us'
            && $request['text'] === 'Hello World'
            && $request->hasHeader('X-Api-Key', 'test-key');
    });
});

test('tolerates a trailing slash on the server url', function () {
    Http::fake([
        'waha.test/api/sendText' => Http::response(['id' => 'msg-1'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test/', 'test-key');
    $provider->send('60123456789', 'Hi');

    Http::assertSent(fn ($request) => $request->url() === 'https://waha.test/api/sendText');
});

test('strips non numeric characters when building the chat id', function () {
    Http::fake([
        'waha.test/api/sendText' => Http::response(['id' => 'msg-1'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $provider->send('+60 12-345 6789', 'Hi');

    Http::assertSent(fn ($request) => $request['chatId'] === '60123456789@c.us');
});

test('sends image with a remote file object and caption', function () {
    Http::fake([
        'waha.test/api/sendImage' => Http::response(['id' => 'img-1'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->sendImage('60123456789', 'https://cdn.test/poster.png', 'Poster');

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://waha.test/api/sendImage'
            && $request['file']['url'] === 'https://cdn.test/poster.png'
            && $request['file']['mimetype'] === 'image/png'
            && $request['caption'] === 'Poster';
    });
});

test('omits caption when none is given', function () {
    Http::fake([
        'waha.test/api/sendImage' => Http::response(['id' => 'img-2'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $provider->sendImage('60123456789', 'https://cdn.test/photo.jpg');

    Http::assertSent(fn ($request) => ! isset($request['caption']));
});

test('sends document with the given mime type and filename', function () {
    Http::fake([
        'waha.test/api/sendFile' => Http::response(['id' => 'doc-1'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->sendDocument('60123456789', 'https://cdn.test/sijil.pdf', 'application/pdf', 'sijil.pdf');

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://waha.test/api/sendFile'
            && $request['file']['mimetype'] === 'application/pdf'
            && $request['file']['filename'] === 'sijil.pdf';
    });
});

test('template messages are not supported', function () {
    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->sendTemplate('60123456789', 'welcome', 'en');

    expect($result)
        ->toHaveKey('success', false)
        ->and($result['error'])->toContain('not supported');
});

test('surfaces the error message from a failed send', function () {
    Http::fake([
        'waha.test/api/sendText' => Http::response(['message' => 'session not found'], 404),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->send('60123456789', 'Hello');

    expect($result)
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'session not found');
});

test('falls back to a readable error when the body has none', function () {
    Http::fake([
        'waha.test/api/sendText' => Http::response('', 401),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->send('60123456789', 'Hello');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('API key');
});

test('reports connected when the session is working', function () {
    Http::fake([
        'waha.test/api/sessions/default' => Http::response([
            'name' => 'default',
            'status' => 'WORKING',
            'me' => ['id' => '60111503653@c.us'],
        ], 200),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->checkStatus();

    expect($result)
        ->toHaveKey('success', true)
        ->toHaveKey('status', 'connected')
        ->and($result['message'])->toContain('60111503653');
});

test('reports an error when the session still needs a qr scan', function () {
    Http::fake([
        'waha.test/api/sessions/default' => Http::response([
            'name' => 'default',
            'status' => 'SCAN_QR_CODE',
            'me' => null,
        ], 200),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->checkStatus();

    expect($result)
        ->toHaveKey('success', false)
        ->toHaveKey('status', 'error')
        ->and($result['message'])->toContain('QR');
});

test('reports a clear error when the session does not exist', function () {
    Http::fake([
        'waha.test/api/sessions/mudeer' => Http::response(['message' => 'Not found'], 404),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key', 'mudeer');
    $result = $provider->checkStatus();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain("'mudeer'");
});

test('reports not configured status without calling the server', function () {
    Http::fake();

    $provider = new WahaProvider('https://waha.test', '');
    $result = $provider->checkStatus();

    expect($result)
        ->toHaveKey('success', false)
        ->toHaveKey('status', 'not_configured');

    Http::assertNothingSent();
});

test('sends to a group chat id without mangling it', function () {
    Http::fake([
        'waha.test/api/sendText' => Http::response(['id' => 'grp-1'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $result = $provider->send('120363026078845432@g.us', 'Salam semua');

    expect($result['success'])->toBeTrue();

    Http::assertSent(fn ($request) => $request['chatId'] === '120363026078845432@g.us');
});

test('passes a contact chat id through untouched', function () {
    Http::fake([
        'waha.test/api/sendText' => Http::response(['id' => 'c-1'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $provider->send('60123456789@c.us', 'Hi');

    Http::assertSent(fn ($request) => $request['chatId'] === '60123456789@c.us');
});

test('sends an image to a group', function () {
    Http::fake([
        'waha.test/api/sendImage' => Http::response(['id' => 'grp-img'], 201),
    ]);

    $provider = new WahaProvider('https://waha.test', 'test-key');
    $provider->sendImage('120363026078845432@g.us', 'https://cdn.test/a.jpg', 'Poster');

    Http::assertSent(fn ($request) => $request['chatId'] === '120363026078845432@g.us');
});
