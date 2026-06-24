<?php

declare(strict_types=1);

use App\Models\WhatsAppTemplate;
use App\Services\SettingsService;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Support\Facades\Http;

it('syncs templates from Meta API', function () {
    Http::fake([
        'graph.facebook.com/*/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tmpl_001',
                    'name' => 'welcome_message',
                    'language' => 'ms',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Selamat datang {{1}}!'],
                    ],
                ],
                [
                    'id' => 'tmpl_002',
                    'name' => 'payment_reminder',
                    'language' => 'ms',
                    'category' => 'MARKETING',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Peringatan pembayaran untuk {{1}}'],
                    ],
                ],
            ],
        ]),
    ]);

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')->with('meta_waba_id')->andReturn('test-waba-id');
    $settings->shouldReceive('get')->with('meta_access_token')->andReturn('test-token');
    $settings->shouldReceive('get')->with('meta_api_version', 'v21.0')->andReturn('v21.0');

    $service = new TemplateService($settings);
    $count = $service->syncFromMeta();

    expect($count)->toBe(2);
    expect(WhatsAppTemplate::count())->toBe(2);

    $welcomeTemplate = WhatsAppTemplate::where('name', 'welcome_message')->first();
    expect($welcomeTemplate)->not->toBeNull()
        ->and($welcomeTemplate->status)->toBe('APPROVED')
        ->and($welcomeTemplate->category)->toBe('utility')
        ->and($welcomeTemplate->meta_template_id)->toBe('tmpl_001')
        ->and($welcomeTemplate->last_synced_at)->not->toBeNull();
});

it('requests explicit fields and follows pagination across pages', function () {
    $nextUrl = 'https://graph.facebook.com/v21.0/test-waba-id/message_templates?fields=name,status,category,language,components,id&limit=100&after=CURSOR2';

    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push([
                'data' => [
                    ['id' => 'p1', 'name' => 'tpl_page_one', 'language' => 'ms', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'a']]],
                ],
                'paging' => ['next' => $nextUrl],
            ])
            ->push([
                'data' => [
                    ['id' => 'p2', 'name' => 'tpl_page_two', 'language' => 'ms', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'b']]],
                ],
            ]),
    ]);

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')->with('meta_waba_id')->andReturn('test-waba-id');
    $settings->shouldReceive('get')->with('meta_access_token')->andReturn('test-token');
    $settings->shouldReceive('get')->with('meta_api_version', 'v21.0')->andReturn('v21.0');

    $count = (new TemplateService($settings))->syncFromMeta();

    expect($count)->toBe(2)
        ->and(WhatsAppTemplate::pluck('name')->all())->toContain('tpl_page_one', 'tpl_page_two');

    // The first request asks Meta for the fields + page size explicitly.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'fields=')
        && str_contains($request->url(), 'message_templates'));
});

it('normalises template status to uppercase so approved() matches', function () {
    Http::fake([
        'graph.facebook.com/*/message_templates*' => Http::response([
            'data' => [
                ['id' => 'low', 'name' => 'lower_status', 'language' => 'ms', 'category' => 'UTILITY', 'status' => 'approved', 'components' => [['type' => 'BODY', 'text' => 'x']]],
            ],
        ]),
    ]);

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')->with('meta_waba_id')->andReturn('test-waba-id');
    $settings->shouldReceive('get')->with('meta_access_token')->andReturn('test-token');
    $settings->shouldReceive('get')->with('meta_api_version', 'v21.0')->andReturn('v21.0');

    (new TemplateService($settings))->syncFromMeta();

    expect(WhatsAppTemplate::approved()->where('name', 'lower_status')->exists())->toBeTrue();
});

it('throws exception when WABA ID is missing', function () {
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')->with('meta_waba_id')->andReturn(null);
    $settings->shouldReceive('get')->with('meta_access_token')->andReturn('test-token');
    $settings->shouldReceive('get')->with('meta_api_version', 'v21.0')->andReturn('v21.0');

    $service = new TemplateService($settings);
    $service->syncFromMeta();
})->throws(\RuntimeException::class, 'Meta WABA ID and access token are required');

it('throws exception when API returns error', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token'],
        ], 400),
    ]);

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')->with('meta_waba_id')->andReturn('test-waba-id');
    $settings->shouldReceive('get')->with('meta_access_token')->andReturn('bad-token');
    $settings->shouldReceive('get')->with('meta_api_version', 'v21.0')->andReturn('v21.0');

    $service = new TemplateService($settings);
    $service->syncFromMeta();
})->throws(\RuntimeException::class, 'Failed to sync templates');

it('updates existing templates on re-sync', function () {
    WhatsAppTemplate::factory()->create([
        'name' => 'existing_template',
        'language' => 'ms',
        'status' => 'PENDING',
        'category' => 'utility',
    ]);

    Http::fake([
        'graph.facebook.com/*/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tmpl_updated',
                    'name' => 'existing_template',
                    'language' => 'ms',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [['type' => 'BODY', 'text' => 'Updated body']],
                ],
            ],
        ]),
    ]);

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')->with('meta_waba_id')->andReturn('test-waba-id');
    $settings->shouldReceive('get')->with('meta_access_token')->andReturn('test-token');
    $settings->shouldReceive('get')->with('meta_api_version', 'v21.0')->andReturn('v21.0');

    $service = new TemplateService($settings);
    $count = $service->syncFromMeta();

    expect($count)->toBe(1);
    expect(WhatsAppTemplate::count())->toBe(1);

    $template = WhatsAppTemplate::where('name', 'existing_template')->first();
    expect($template->status)->toBe('APPROVED')
        ->and($template->meta_template_id)->toBe('tmpl_updated');
});

it('returns approved templates', function () {
    WhatsAppTemplate::factory()->approved()->count(2)->create();
    WhatsAppTemplate::factory()->pending()->create();

    $settings = $this->mock(SettingsService::class);
    $service = new TemplateService($settings);

    $approved = $service->getApproved();

    expect($approved)->toHaveCount(2);
});
