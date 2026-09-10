<?php

use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.waha.api_url' => 'https://waha.test',
        'services.waha.api_key' => 'test-key',
        'cekbot.webhook_url' => 'https://app.test/api/cekbot/webhook',
        'cekbot.webhook_events' => ['message', 'session.status', 'message.ack'],
    ]);
});

it('auto-registers the webhook when creating a session', function () {
    Http::fake(['waha.test/api/sessions' => Http::response(['name' => 'default', 'status' => 'STARTING'], 201)]);

    app(WahaSessionManager::class)->createSession('default', ['metadata' => ['app' => 'cekbot']]);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/sessions')
        && $r->method() === 'POST'
        && data_get($r->data(), 'config.webhooks.0.url') === 'https://app.test/api/cekbot/webhook'
        && data_get($r->data(), 'config.webhooks.0.events') === ['message', 'session.status', 'message.ack']
        && data_get($r->data(), 'config.metadata.app') === 'cekbot');
});

it('does not add a webhook when no URL is configured', function () {
    config(['cekbot.webhook_url' => null]);
    Http::fake(['waha.test/api/sessions' => Http::response(['name' => 'x'], 201)]);

    app(WahaSessionManager::class)->createSession('x', []);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/sessions')
        && data_get($r->data(), 'config.webhooks') === null);
});

it('re-applies the webhook to an existing session, preserving metadata', function () {
    Http::fake([
        'waha.test/api/sessions/default' => Http::sequence()
            ->push(['name' => 'default', 'config' => ['metadata' => ['app' => 'cekbot', 'cekbot.session_id' => '6']]], 200)
            ->push(['name' => 'default', 'status' => 'WORKING'], 200),
    ]);

    expect(app(WahaSessionManager::class)->setWebhook('default'))->toBeTrue();

    Http::assertSent(fn ($r) => $r->method() === 'PUT'
        && str_contains($r->url(), '/api/sessions/default')
        && data_get($r->data(), 'config.webhooks.0.url') === 'https://app.test/api/cekbot/webhook'
        && data_get($r->data(), 'config.metadata.app') === 'cekbot'
        && ($r->data()['config']['metadata']['cekbot.session_id'] ?? null) === '6');
});

it('sync-webhooks command applies to every session', function () {
    Http::fake([
        'waha.test/api/sessions?all=true' => Http::response([['name' => 'default'], ['name' => 'chatbotbedaie']], 200),
        'waha.test/api/sessions/default' => Http::sequence()->push(['name' => 'default', 'config' => []], 200)->push(['ok' => true], 200),
        'waha.test/api/sessions/chatbotbedaie' => Http::sequence()->push(['name' => 'chatbotbedaie', 'config' => []], 200)->push(['ok' => true], 200),
    ]);

    $this->artisan('cekbot:sync-webhooks')
        ->expectsOutputToContain('Applied webhook to 2 session(s)')
        ->assertSuccessful();
});
