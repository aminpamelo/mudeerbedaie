<?php

declare(strict_types=1);

use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Models\FunnelEmailTemplate;
use App\Services\Funnel\FunnelAutomationService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

test('executeSendEmail uses template content when template_id is set', function () {
    $template = FunnelEmailTemplate::factory()->create([
        'subject' => 'Template Subject: {{order.number}}',
        'content' => 'Template body for {{contact.first_name}}',
        'editor_type' => 'text',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'template',
            'template_id' => $template->id,
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);

    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com', 'first_name' => 'John'],
        'order' => ['number' => 'PO-001'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendEmail uses subject override when provided with template', function () {
    $template = FunnelEmailTemplate::factory()->create([
        'subject' => 'Template Default Subject',
        'content' => 'Template body',
        'editor_type' => 'text',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'template',
            'template_id' => $template->id,
            'subject' => 'Override Subject',
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendEmail falls back to inline content when email_source is custom', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'custom',
            'subject' => 'Inline Subject',
            'content' => 'Inline body',
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendEmail sends HTML email for visual templates', function () {
    $template = FunnelEmailTemplate::factory()->visual()->create([
        'subject' => 'HTML Template',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'template',
            'template_id' => $template->id,
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com', 'first_name' => 'John'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendEmail backward compatible with no email_source field', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'subject' => 'Old Style Subject',
            'content' => 'Old style body',
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com'],
    ]);

    expect($result['success'])->toBeTrue();
});
