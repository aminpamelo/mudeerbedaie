<?php

declare(strict_types=1);

use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Models\WhatsAppTemplate;
use App\Services\Funnel\FunnelAutomationService;
use App\Services\WhatsApp\MetaCloudProvider;
use App\Services\WhatsApp\WhatsAppManager;

beforeEach(function () {
    // Mock the MetaCloudProvider so we don't hit real APIs
    $this->metaProvider = Mockery::mock(MetaCloudProvider::class);
    $this->metaProvider->shouldReceive('getName')->andReturn('meta');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($this->metaProvider);
    app()->instance(WhatsAppManager::class, $manager);

    // Also bind the mock as MetaCloudProvider so the instanceof fallback resolves it
    app()->instance(MetaCloudProvider::class, $this->metaProvider);
});

test('executeSendWhatsApp sends via onsend when provider is onsend', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'onsend',
            'message' => 'Hello {{contact.name}}!',
            'phone_field' => 'contact.phone',
        ],
    ]);

    // Mock WhatsAppService send
    $whatsAppService = Mockery::mock(\App\Services\WhatsAppService::class);
    $whatsAppService->shouldReceive('send')
        ->once()
        ->andReturn(['success' => true, 'message_id' => 'msg_123']);
    app()->instance(\App\Services\WhatsAppService::class, $whatsAppService);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['name' => 'Ahmad', 'phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['provider'])->toBe('onsend');
});

test('executeSendWhatsApp defaults to onsend when no provider set (backward compatible)', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'message' => 'Hello!',
            'phone_field' => 'contact.phone',
        ],
    ]);

    $whatsAppService = Mockery::mock(\App\Services\WhatsAppService::class);
    $whatsAppService->shouldReceive('send')
        ->once()
        ->andReturn(['success' => true, 'message_id' => 'msg_456']);
    app()->instance(\App\Services\WhatsAppService::class, $whatsAppService);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['provider'])->toBe('onsend');
});

test('executeSendWhatsApp sends WABA template when provider is waba', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name' => 'order_confirmation',
        'language' => 'ms',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => 'Terima kasih {{1}}! Pesanan {{2}} berjumlah {{3}}.'],
        ],
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => $template->id,
            'template_name' => 'order_confirmation',
            'template_language' => 'ms',
            'template_variables' => [
                'body' => [
                    '1' => '{{contact.name}}',
                    '2' => '{{order.number}}',
                    '3' => '{{order.total}}',
                ],
            ],
            'phone_field' => 'contact.phone',
        ],
    ]);

    $this->metaProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function ($phone, $name, $lang, $components) {
            return $phone === '60123456789'
                && $name === 'order_confirmation'
                && $lang === 'ms'
                && $components[0]['type'] === 'body'
                && $components[0]['parameters'][0]['text'] === 'Ahmad'
                && $components[0]['parameters'][1]['text'] === 'PO-001'
                && $components[0]['parameters'][2]['text'] === 'RM 99.00';
        })
        ->andReturn(['success' => true, 'message_id' => 'wamid_789']);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['name' => 'Ahmad', 'phone' => '60123456789'],
        'order' => ['number' => 'PO-001', 'total' => 'RM 99.00'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['provider'])->toBe('waba');
    expect($result['template_name'])->toBe('order_confirmation');
});

test('executeSendWhatsApp falls back to template variable_mappings when action config has no template_variables', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name' => 'order_shipped',
        'language' => 'ms',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => 'Hi {{1}}, pesanan {{2}} telah dihantar. Tracking: {{3}}.'],
        ],
        'variable_mappings' => [
            'body' => [
                '1' => 'contact.name',
                '2' => 'order.number',
                '3' => 'order.tracking_number',
            ],
        ],
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => $template->id,
            'phone_field' => 'contact.phone',
        ],
    ]);

    $this->metaProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function ($phone, $name, $lang, $components) {
            return $phone === '60123456789'
                && $name === 'order_shipped'
                && $components[0]['type'] === 'body'
                && $components[0]['parameters'][0]['text'] === 'Siti'
                && $components[0]['parameters'][1]['text'] === 'PO-999'
                && $components[0]['parameters'][2]['text'] === 'TRK-42';
        })
        ->andReturn(['success' => true, 'message_id' => 'wamid_mapping']);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['name' => 'Siti', 'phone' => '60123456789'],
        'order' => ['number' => 'PO-999', 'tracking_number' => 'TRK-42'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['provider'])->toBe('waba');
});

test('executeSendWhatsApp skips custom sentinel in template variable_mappings', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name' => 'thank_you',
        'language' => 'ms',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => 'Hi {{1}}, terima kasih!'],
        ],
        'variable_mappings' => [
            'body' => [
                '1' => 'custom',
            ],
        ],
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => $template->id,
            'phone_field' => 'contact.phone',
        ],
    ]);

    $this->metaProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(fn ($phone, $name, $lang, $components) => $components === [])
        ->andReturn(['success' => true, 'message_id' => 'wamid_custom']);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendWhatsApp fails when waba template is not approved', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name' => 'pending_template',
        'language' => 'ms',
        'status' => 'PENDING',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => $template->id,
            'phone_field' => 'contact.phone',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not approved');
});

test('executeSendWhatsApp fails when no template configured for waba', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'phone_field' => 'contact.phone',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('No WhatsApp template configured');
});

test('executeSendWhatsApp fails when no phone number in context', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => 1,
            'phone_field' => 'contact.phone',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['name' => 'Ahmad'],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('No phone number');
});

test('WABA template falls back to template_name when template_id not found', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => 99999,
            'template_name' => 'fallback_template',
            'template_language' => 'en',
            'template_variables' => [],
            'phone_field' => 'contact.phone',
        ],
    ]);

    $this->metaProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(fn ($phone, $name, $lang) => $name === 'fallback_template' && $lang === 'en')
        ->andReturn(['success' => true, 'message_id' => 'wamid_fallback']);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['template_name'])->toBe('fallback_template');
});

test('whatsapp templates API returns only approved templates', function () {
    WhatsAppTemplate::factory()->create(['status' => 'APPROVED', 'name' => 'approved_one']);
    WhatsAppTemplate::factory()->create(['status' => 'PENDING', 'name' => 'pending_one']);
    WhatsAppTemplate::factory()->create(['status' => 'REJECTED', 'name' => 'rejected_one']);

    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/funnel-builder/whatsapp-templates');

    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('approved_one');
});
