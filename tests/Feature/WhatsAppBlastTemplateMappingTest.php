<?php

declare(strict_types=1);

use App\Jobs\SendCampaignMessageJob;
use App\Models\ProductOrder;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppBlastService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Queue;

/**
 * @param  array<int, string>  $bodyMapping
 */
function blastTrackingTemplate(array $bodyMapping): WhatsAppTemplate
{
    return WhatsAppTemplate::create([
        'name' => 'blast_'.bin2hex(random_bytes(4)),
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => "Produk: {{1}}\nNo. Tracking: {{2}}\nCourier: {{3}}"],
        ],
        'variable_mappings' => ['body' => $bodyMapping],
    ]);
}

it('builds bulk-blast components from the template\'s own variable mapping', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => 'EPBULK1',
        'shipping_provider' => 'jnt',
    ]);

    $template = blastTrackingTemplate([1 => 'order.items_list', 2 => 'order.tracking_number', 3 => 'order.courier']);

    $components = app(WhatsAppBlastService::class)->buildTemplateComponents($template, $order);

    expect($components)->toHaveCount(1);
    expect($components[0]['parameters'][1]['text'])->toBe('EPBULK1');
    expect($components[0]['parameters'][2]['text'])->toBe('J&T Express');
});

it('renders the blast preview with resolved template values', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => 'EPPREV2',
        'shipping_provider' => 'jnt',
    ]);

    $template = blastTrackingTemplate([1 => 'order.items_list', 2 => 'order.tracking_number', 3 => 'order.courier']);

    $preview = app(WhatsAppBlastService::class)->renderTemplatePreview($template, $order);

    expect($preview)->toContain('EPPREV2')->toContain('J&T Express')->not->toContain('{{2}}');
});

it('never emits an empty param — falls back to an order field so blasts do not hit #132000', function () {
    $order = ProductOrder::factory()->create(['customer_name' => 'Ali', 'order_number' => 'PO-EMPTY']);

    // Mirrors kelas_solat_97_baru: {{1}} -> class_name, which has no value for order sends.
    $classTemplate = WhatsAppTemplate::create([
        'name' => 'blast_'.bin2hex(random_bytes(4)),
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [['type' => 'BODY', 'text' => 'Didaftarkan {{1}} ke Kelas Solat 97']],
        'variable_mappings' => ['body' => [1 => 'class_name']],
    ]);

    $components = app(WhatsAppBlastService::class)->buildTemplateComponents($classTemplate, $order);

    // {{1}} falls back to the customer name (never empty) — exactly the pre-regression behaviour.
    expect($components[0]['parameters'][0]['text'])->toBe('Ali');
    expect($components[0]['parameters'][0]['text'])->not->toBe('');
});

it('campaign job sends a class-mapped template to orders (regression: worked, then #132000, now fixed)', function () {
    Queue::fake();

    $order = ProductOrder::factory()->create([
        'customer_name' => 'Ahmad Amin',
        'customer_phone' => '60123456789',
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'blast_'.bin2hex(random_bytes(4)),
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [['type' => 'BODY', 'text' => 'Didaftarkan {{1}} ke Kelas Solat 97']],
        'variable_mappings' => ['body' => [1 => 'class_name']],
    ]);

    $campaign = app(WhatsAppBlastService::class)->createFromOrders([$order->id], $template, [], null);
    $recipient = $campaign->recipients()->first();

    $captured = null;
    $this->mock(WhatsAppService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('canSendNow')->andReturn(true);
        $mock->shouldReceive('shouldPauseBatch')->andReturn(false);
        $mock->shouldReceive('getBatchPauseDuration')->andReturn(0);
        $mock->shouldReceive('sendTemplate')->once()->andReturnUsing(function ($phone, $name, $lang, $components) use (&$captured) {
            $captured = $components;

            return ['success' => true, 'message_id' => 'wamid-1'];
        });
    });

    (new SendCampaignMessageJob($recipient->id))->handle(app(WhatsAppService::class), app(WhatsAppBlastService::class));

    // {{1}} falls back to the customer name (non-empty) -> Meta accepts -> sent (no #132000).
    expect($captured[0]['parameters'][0]['text'])->toBe('Ahmad Amin');
    expect($recipient->fresh()->status)->toBe('sent');
});

it('campaign job sends components resolved from the template mapping (not the old defaults)', function () {
    Queue::fake();

    $order = ProductOrder::factory()->create([
        'order_number' => 'PO-JOB9',
        'tracking_id' => 'EPJOB9',
        'shipping_provider' => 'jnt',
        'customer_phone' => '60123456789',
    ]);

    // {{2}} -> tracking number, {{3}} -> courier (exactly what the user configured).
    $template = blastTrackingTemplate([1 => 'order.number', 2 => 'order.tracking_number', 3 => 'order.courier']);

    $campaign = app(WhatsAppBlastService::class)->createFromOrders([$order->id], $template, [], null);
    $recipient = $campaign->recipients()->first();
    expect($recipient)->not->toBeNull();

    $captured = null;
    $this->mock(WhatsAppService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('canSendNow')->andReturn(true);
        $mock->shouldReceive('shouldPauseBatch')->andReturn(false);
        $mock->shouldReceive('getBatchPauseDuration')->andReturn(0);
        $mock->shouldReceive('sendTemplate')
            ->once()
            ->andReturnUsing(function ($phone, $name, $lang, $components) use (&$captured) {
                $captured = $components;

                return ['success' => true, 'message_id' => 'wamid-1'];
            });
    });

    (new SendCampaignMessageJob($recipient->id))->handle(app(WhatsAppService::class), app(WhatsAppBlastService::class));

    expect($captured[0]['parameters'][0]['text'])->toBe('PO-JOB9');      // {{1}}
    expect($captured[0]['parameters'][1]['text'])->toBe('EPJOB9');       // {{2}} tracking number
    expect($captured[0]['parameters'][2]['text'])->toBe('J&T Express');  // {{3}} courier
    expect($recipient->fresh()->status)->toBe('sent');
});
