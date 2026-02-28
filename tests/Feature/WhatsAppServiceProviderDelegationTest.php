<?php

use App\Contracts\WhatsAppProviderInterface;
use App\Models\WhatsAppSendLog;
use App\Services\SettingsService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helper to create a WhatsAppService with enabled config and a mocked manager.
 *
 * @return array{service: WhatsAppService, manager: WhatsAppManager, provider: Mockery\MockInterface&WhatsAppProviderInterface}
 */
function createEnabledWhatsAppService(string $providerName = 'onsend'): array
{
    // Set up SettingsService to return "enabled" config
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('getWhatsAppConfig')->andReturn([
        'enabled' => true,
        'api_token' => 'test-token',
        'min_delay_seconds' => 10,
        'max_delay_seconds' => 30,
        'batch_size' => 15,
        'batch_pause_minutes' => 1,
        'daily_limit' => 0,
        'time_restriction_enabled' => false,
        'send_hours_start' => 8,
        'send_hours_end' => 22,
        'message_variation_enabled' => false,
    ]);
    app()->instance(SettingsService::class, $settings);

    // Create a mock provider
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('getName')->andReturn($providerName);

    // Create a mock manager that returns our mock provider
    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);
    $manager->shouldReceive('getProviderName')->andReturn($providerName);

    $service = new WhatsAppService($manager);

    return ['service' => $service, 'manager' => $manager, 'provider' => $provider];
}

/**
 * Helper to create a disabled WhatsAppService.
 */
function createDisabledWhatsAppService(): WhatsAppService
{
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('getWhatsAppConfig')->andReturn([
        'enabled' => false,
        'api_token' => '',
        'min_delay_seconds' => 10,
        'max_delay_seconds' => 30,
        'batch_size' => 15,
        'batch_pause_minutes' => 1,
        'daily_limit' => 0,
        'time_restriction_enabled' => false,
        'send_hours_start' => 8,
        'send_hours_end' => 22,
        'message_variation_enabled' => false,
    ]);
    app()->instance(SettingsService::class, $settings);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(false);
    $provider->shouldReceive('getName')->andReturn('onsend');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);
    $manager->shouldReceive('getProviderName')->andReturn('onsend');

    return new WhatsAppService($manager);
}

// ── send() delegates to active provider ──────────────────────────

test('send() delegates to active provider', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('send')
        ->once()
        ->with('60123456789', 'Hello World')
        ->andReturn([
            'success' => true,
            'message_id' => 'msg-123',
            'message' => 'Message sent',
        ]);

    $result = $service->send('0123456789', 'Hello World');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'msg-123')
        ->toHaveKey('message', 'Message sent');
});

// ── sendImage() delegates to active provider ─────────────────────

test('sendImage() delegates to active provider', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('sendImage')
        ->once()
        ->with('60123456789', 'https://example.com/image.jpg', null)
        ->andReturn([
            'success' => true,
            'message_id' => 'img-456',
            'message' => 'Image sent',
        ]);

    $result = $service->sendImage('0123456789', 'https://example.com/image.jpg');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'img-456');
});

// ── sendDocument() delegates to active provider ──────────────────

test('sendDocument() delegates to active provider', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('sendDocument')
        ->once()
        ->with('60123456789', 'https://example.com/doc.pdf', 'application/pdf', 'cert.pdf')
        ->andReturn([
            'success' => true,
            'message_id' => 'doc-789',
            'message' => 'Document sent',
        ]);

    $result = $service->sendDocument('0123456789', 'https://example.com/doc.pdf', 'application/pdf', 'cert.pdf');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'doc-789');
});

// ── checkDeviceStatus() delegates to provider ────────────────────

test('checkDeviceStatus() delegates to provider', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('checkStatus')
        ->once()
        ->andReturn([
            'success' => true,
            'status' => 'connected',
            'message' => 'Device is connected',
        ]);

    $result = $service->checkDeviceStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('status', 'connected')
        ->toHaveKey('message', 'Device is connected');
});

// ── sendTemplate() delegates to provider ─────────────────────────

test('sendTemplate() delegates to active provider', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('sendTemplate')
        ->once()
        ->with('60123456789', 'welcome', 'en', [])
        ->andReturn([
            'success' => false,
            'error' => 'Template messages are not supported by Onsend provider',
        ]);

    $result = $service->sendTemplate('0123456789', 'welcome', 'en', []);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false);
});

// ── Returns error when not enabled ───────────────────────────────

test('send() returns error when service is not enabled', function () {
    $service = createDisabledWhatsAppService();

    $result = $service->send('0123456789', 'Hello');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'WhatsApp service is not enabled');
});

test('sendImage() returns error when service is not enabled', function () {
    $service = createDisabledWhatsAppService();

    $result = $service->sendImage('0123456789', 'https://example.com/image.jpg');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'WhatsApp service is not enabled');
});

test('sendDocument() returns error when service is not enabled', function () {
    $service = createDisabledWhatsAppService();

    $result = $service->sendDocument('0123456789', 'https://example.com/doc.pdf', 'application/pdf');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'WhatsApp service is not enabled');
});

test('sendTemplate() returns error when service is not enabled', function () {
    $service = createDisabledWhatsAppService();

    $result = $service->sendTemplate('0123456789', 'welcome', 'en');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'WhatsApp service is not enabled');
});

// ── Logs send attempt on success ─────────────────────────────────

test('logs send attempt on success', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('send')
        ->once()
        ->andReturn([
            'success' => true,
            'message_id' => 'msg-123',
            'message' => 'Message sent',
        ]);

    $service->send('0123456789', 'Hello');

    $log = WhatsAppSendLog::where('send_date', today())->first();

    expect($log)->not->toBeNull()
        ->and($log->message_count)->toBe(1)
        ->and($log->success_count)->toBe(1)
        ->and($log->failure_count)->toBe(0);
});

// ── Logs send attempt on failure ─────────────────────────────────

test('logs send attempt on failure', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('send')
        ->once()
        ->andReturn([
            'success' => false,
            'error' => 'Unauthorized',
        ]);

    $service->send('0123456789', 'Hello');

    $log = WhatsAppSendLog::where('send_date', today())->first();

    expect($log)->not->toBeNull()
        ->and($log->message_count)->toBe(1)
        ->and($log->success_count)->toBe(0)
        ->and($log->failure_count)->toBe(1);
});

// ── Logs send attempt on exception ───────────────────────────────

test('logs send attempt and returns error on provider exception', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return $message === 'WhatsApp send exception'
                && str_contains($context['error'], 'Connection failed');
        });

    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('send')
        ->once()
        ->andThrow(new \RuntimeException('Connection failed'));

    $result = $service->send('0123456789', 'Hello');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('error', 'Connection failed');

    $log = WhatsAppSendLog::where('send_date', today())->first();
    expect($log)->not->toBeNull()
        ->and($log->failure_count)->toBe(1);
});

// ── Message variation applied only for onsend provider ───────────

test('message variation applied only for onsend provider', function () {
    // Enable message variation
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('getWhatsAppConfig')->andReturn([
        'enabled' => true,
        'api_token' => 'test-token',
        'min_delay_seconds' => 10,
        'max_delay_seconds' => 30,
        'batch_size' => 15,
        'batch_pause_minutes' => 1,
        'daily_limit' => 0,
        'time_restriction_enabled' => false,
        'send_hours_start' => 8,
        'send_hours_end' => 22,
        'message_variation_enabled' => true,
    ]);
    app()->instance(SettingsService::class, $settings);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('getName')->andReturn('onsend');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);
    $manager->shouldReceive('getProviderName')->andReturn('onsend');

    $service = new WhatsAppService($manager);

    // For onsend, the message should have invisible chars appended
    $provider->shouldReceive('send')
        ->once()
        ->withArgs(function ($phone, $message) {
            // The original "Hello" should be at the start, but the message should
            // be longer because of the zero-width characters
            return $phone === '60123456789'
                && str_starts_with($message, 'Hello')
                && strlen($message) > strlen('Hello');
        })
        ->andReturn(['success' => true, 'message_id' => 'msg-1', 'message' => 'Sent']);

    $service->send('0123456789', 'Hello');
});

test('message variation NOT applied for non-onsend provider', function () {
    // Enable message variation
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('getWhatsAppConfig')->andReturn([
        'enabled' => true,
        'api_token' => 'test-token',
        'min_delay_seconds' => 10,
        'max_delay_seconds' => 30,
        'batch_size' => 15,
        'batch_pause_minutes' => 1,
        'daily_limit' => 0,
        'time_restriction_enabled' => false,
        'send_hours_start' => 8,
        'send_hours_end' => 22,
        'message_variation_enabled' => true,
    ]);
    app()->instance(SettingsService::class, $settings);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('getName')->andReturn('meta');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);
    $manager->shouldReceive('getProviderName')->andReturn('meta');

    $service = new WhatsAppService($manager);

    // For non-onsend, the message should be sent as-is (no variation)
    $provider->shouldReceive('send')
        ->once()
        ->with('60123456789', 'Hello')
        ->andReturn(['success' => true, 'message_id' => 'msg-2', 'message' => 'Sent']);

    $service->send('0123456789', 'Hello');
});

// ── Phone number formatting applied before delegation ────────────

test('phone number formatting applied before delegation', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    // Provider should receive the formatted phone number (60-prefixed), not the raw input
    $provider->shouldReceive('send')
        ->once()
        ->withArgs(function ($phone, $message) {
            return $phone === '60123456789';
        })
        ->andReturn(['success' => true, 'message_id' => 'msg-fmt', 'message' => 'Sent']);

    $service->send('0123456789', 'Hello');
});

test('phone number formatting for sendImage before delegation', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('sendImage')
        ->once()
        ->withArgs(function ($phone, $imageUrl, $caption) {
            return $phone === '60123456789';
        })
        ->andReturn(['success' => true, 'message_id' => 'img-fmt', 'message' => 'Sent']);

    $service->sendImage('0123456789', 'https://example.com/image.jpg');
});

test('phone number formatting for sendDocument before delegation', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('sendDocument')
        ->once()
        ->withArgs(function ($phone) {
            return $phone === '60123456789';
        })
        ->andReturn(['success' => true, 'message_id' => 'doc-fmt', 'message' => 'Sent']);

    $service->sendDocument('0123456789', 'https://example.com/doc.pdf', 'application/pdf');
});

// ── isEnabled() checks config AND provider isConfigured() ────────

test('isEnabled() returns true when config enabled and provider configured', function () {
    ['service' => $service] = createEnabledWhatsAppService();

    expect($service->isEnabled())->toBeTrue();
});

test('isEnabled() returns false when config disabled', function () {
    $service = createDisabledWhatsAppService();

    expect($service->isEnabled())->toBeFalse();
});

test('isEnabled() returns false when provider not configured', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('getWhatsAppConfig')->andReturn([
        'enabled' => true,
        'api_token' => 'test-token',
        'min_delay_seconds' => 10,
        'max_delay_seconds' => 30,
        'batch_size' => 15,
        'batch_pause_minutes' => 1,
        'daily_limit' => 0,
        'time_restriction_enabled' => false,
        'send_hours_start' => 8,
        'send_hours_end' => 22,
        'message_variation_enabled' => false,
    ]);
    app()->instance(SettingsService::class, $settings);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(false);
    $provider->shouldReceive('getName')->andReturn('onsend');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);

    $service = new WhatsAppService($manager);

    expect($service->isEnabled())->toBeFalse();
});

// ── Anti-ban methods still work ──────────────────────────────────

test('shouldPauseBatch() still works correctly', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    // Provider returns success for each send
    $provider->shouldReceive('send')
        ->andReturn(['success' => true, 'message_id' => 'msg', 'message' => 'Sent']);

    // Default batch_size is 15; after 15 calls, shouldPauseBatch should return true
    for ($i = 1; $i < 15; $i++) {
        expect($service->shouldPauseBatch())->toBeFalse();
    }
    expect($service->shouldPauseBatch())->toBeTrue();
});

test('getBatchPauseDuration() returns correct value', function () {
    ['service' => $service] = createEnabledWhatsAppService();

    // Default batch_pause_minutes is 1, so 1 * 60 = 60 seconds
    expect($service->getBatchPauseDuration())->toBe(60);
});

// ── Integration test with Http::fake through OnsendProvider ──────

test('end-to-end: send() works through OnsendProvider with Http::fake', function () {
    Http::fake([
        'onsend.io/api/v1/send' => Http::response([
            'success' => true,
            'message_id' => 'e2e-msg-001',
            'message' => 'Message sent successfully',
        ], 200),
    ]);

    // Set up real SettingsService mock to return proper config
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('getWhatsAppConfig')->andReturn([
        'enabled' => true,
        'api_token' => 'test-token',
        'api_url' => 'https://onsend.io/api/v1',
        'min_delay_seconds' => 10,
        'max_delay_seconds' => 30,
        'batch_size' => 15,
        'batch_pause_minutes' => 1,
        'daily_limit' => 0,
        'time_restriction_enabled' => false,
        'send_hours_start' => 8,
        'send_hours_end' => 22,
        'message_variation_enabled' => false,
    ]);
    $settings->shouldReceive('get')
        ->with('whatsapp_provider', 'onsend')
        ->andReturn('onsend');
    app()->instance(SettingsService::class, $settings);

    $manager = new WhatsAppManager($settings);
    $service = new WhatsAppService($manager);

    $result = $service->send('0123456789', 'End-to-end test');

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message_id', 'e2e-msg-001');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://onsend.io/api/v1/send'
            && $request['phone_number'] === '60123456789'
            && $request['message'] === 'End-to-end test';
    });
});

// ── Logs send attempt for sendImage on success ───────────────────

test('logs send attempt for sendImage on success', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('sendImage')
        ->once()
        ->andReturn([
            'success' => true,
            'message_id' => 'img-log',
            'message' => 'Image sent',
        ]);

    $service->sendImage('0123456789', 'https://example.com/image.jpg');

    $log = WhatsAppSendLog::where('send_date', today())->first();

    expect($log)->not->toBeNull()
        ->and($log->success_count)->toBe(1);
});

// ── Logs send attempt for sendDocument on success ────────────────

test('logs send attempt for sendDocument on success', function () {
    ['service' => $service, 'provider' => $provider] = createEnabledWhatsAppService();

    $provider->shouldReceive('sendDocument')
        ->once()
        ->andReturn([
            'success' => true,
            'message_id' => 'doc-log',
            'message' => 'Document sent',
        ]);

    $service->sendDocument('0123456789', 'https://example.com/doc.pdf', 'application/pdf');

    $log = WhatsAppSendLog::where('send_date', today())->first();

    expect($log)->not->toBeNull()
        ->and($log->success_count)->toBe(1);
});
