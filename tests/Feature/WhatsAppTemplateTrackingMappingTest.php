<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\WhatsAppTemplate;
use App\Services\MergeTag\MergeTagEngine;
use App\Services\Orders\TrackingNotificationService;

it('resolves order tracking fields via the merge-tag engine', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => 'EP12345MY',
        'shipping_provider' => 'jnt',
    ]);

    $engine = app(MergeTagEngine::class)->setContext(['product_order' => $order]);

    expect($engine->resolve('{{order.tracking_number}}'))->toBe('EP12345MY');
    expect($engine->resolve('{{order.courier}}'))->toBe('J&T Express');
    expect($engine->resolve('{{order.tracking_url}}'))->toContain('EP12345MY');
});

it('builds meta components from the template\'s own variable mapping', function () {
    $order = ProductOrder::factory()->create([
        'order_number' => 'PO-XYZ',
        'tracking_id' => 'EPABC999',
        'shipping_provider' => 'jnt',
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'track_tpl',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => "No. Tracking: {{1}}\nCourier: {{2}}"],
        ],
        'variable_mappings' => ['body' => [1 => 'order.tracking_number', 2 => 'order.courier']],
    ]);

    $components = app(TrackingNotificationService::class)->buildMetaComponents($template, $order);

    expect($components)->toHaveCount(1);
    expect($components[0]['type'])->toBe('body');
    expect($components[0]['parameters'][0]['text'])->toBe('EPABC999');
    expect($components[0]['parameters'][1]['text'])->toBe('J&T Express');
});

it('renders the meta preview with resolved values, not raw placeholders', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => 'EPZZZ1',
        'shipping_provider' => 'jnt',
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'track_tpl2',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => 'No. Tracking: {{1}}'],
        ],
        'variable_mappings' => ['body' => [1 => 'order.tracking_number']],
    ]);

    $preview = app(TrackingNotificationService::class)->renderMetaPreview($template, $order);

    expect($preview)->toContain('EPZZZ1')->not->toContain('{{1}}');
});

it('falls back to sensible order defaults when a template has no mapping', function () {
    $order = ProductOrder::factory()->create([
        'order_number' => 'PO-FALLBACK',
        'tracking_id' => 'EPFALL9',
        'customer_name' => 'Ali',
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'track_tpl3',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => '{{1}} {{2}} {{3}}'],
        ],
        'variable_mappings' => [],
    ]);

    $components = app(TrackingNotificationService::class)->buildMetaComponents($template, $order);

    // Defaults: {{1}} = contact.name, {{2}} = order.number, {{3}} = order.tracking_number
    expect($components[0]['parameters'][0]['text'])->toBe('Ali');
    expect($components[0]['parameters'][1]['text'])->toBe('PO-FALLBACK');
    expect($components[0]['parameters'][2]['text'])->toBe('EPFALL9');
});
