<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('pocket manifest is public and exposes the PWA fields', function () {
    $response = $this->get('/live-host/manifest.json');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/manifest+json');

    $manifest = $response->json();

    expect($manifest)
        ->toHaveKey('name', 'Hos Siaran Langsung')
        ->toHaveKey('short_name', 'Hos')
        ->toHaveKey('start_url', '/live-host')
        ->toHaveKey('scope', '/live-host')
        ->toHaveKey('display', 'standalone')
        ->toHaveKey('theme_color');

    expect($manifest['icons'])->toHaveCount(2);
});

test('pocket service worker ships push + offline handlers', function () {
    $swPath = public_path('pocket-sw.js');

    expect(file_exists($swPath))->toBeTrue();

    $content = file_get_contents($swPath);

    expect($content)
        ->toContain('mudeer-pocket-v1')
        ->toContain("addEventListener('push'")
        ->toContain("addEventListener('notificationclick'")
        ->toContain('/live-host');
});

test('pocket PWA icons exist', function () {
    expect(file_exists(public_path('icons/pocket-192.svg')))->toBeTrue();
    expect(file_exists(public_path('icons/pocket-512.svg')))->toBeTrue();
});

test('pocket page includes the PWA + push meta tags', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $response = $this->actingAs($host)->get('/live-host');

    $response->assertSuccessful();
    $response->assertSee('rel="manifest"', false);
    $response->assertSee('name="theme-color"', false);
    $response->assertSee('name="apple-mobile-web-app-capable"', false);
    $response->assertSee('apple-touch-icon', false);
    $response->assertSee('name="vapid-public-key"', false);
});

test('a live host can store then remove a push subscription', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $payload = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        'keys' => ['auth' => 'authsecret', 'p256dh' => 'p256dhkey'],
    ];

    $this->actingAs($host)
        ->postJson('/live-host/push-subscriptions', $payload)
        ->assertSuccessful();

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $host->id,
        'subscribable_type' => $host->getMorphClass(),
        'endpoint' => $payload['endpoint'],
    ]);

    $this->actingAs($host)
        ->deleteJson('/live-host/push-subscriptions', ['endpoint' => $payload['endpoint']])
        ->assertSuccessful();

    $this->assertDatabaseMissing('push_subscriptions', [
        'endpoint' => $payload['endpoint'],
    ]);
});

test('the push subscription endpoint rejects non-hosts', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/live-host/push-subscriptions', [
            'endpoint' => 'https://example.com/x',
            'keys' => ['auth' => 'a', 'p256dh' => 'b'],
        ])
        ->assertForbidden();
});

test('guests cannot store a push subscription', function () {
    $this->postJson('/live-host/push-subscriptions', [
        'endpoint' => 'https://example.com/x',
        'keys' => ['auth' => 'a', 'p256dh' => 'b'],
    ])->assertUnauthorized();
});
