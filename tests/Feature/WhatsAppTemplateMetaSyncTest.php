<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\SettingsService;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    Http::preventStrayRequests();

    // Set up Meta credentials
    $settings = app(SettingsService::class);
    $settings->set('meta_waba_id', 'test-waba-123');
    $settings->set('meta_access_token', 'test-token-abc');
    $settings->set('meta_api_version', 'v21.0');
});

it('maps components for Meta with HEADER format field', function () {
    $service = app(TemplateService::class);

    $components = [
        ['type' => 'HEADER', 'text' => 'Hello {{1}}'],
        ['type' => 'BODY', 'text' => 'Your order is ready'],
        ['type' => 'FOOTER', 'text' => 'Thanks'],
    ];

    $reflection = new ReflectionMethod($service, 'mapComponentsForMeta');
    $mapped = $reflection->invoke($service, $components);

    expect($mapped[0])->toHaveKey('format', 'TEXT');
    expect($mapped[1])->not->toHaveKey('format');
    expect($mapped[2])->not->toHaveKey('format');
});

it('submits a local template to Meta successfully', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => 'meta-tpl-456',
            'status' => 'PENDING',
            'category' => 'UTILITY',
        ], 200),
    ]);

    $template = WhatsAppTemplate::factory()->create([
        'name' => 'test_submit',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'PENDING',
        'components' => [
            ['type' => 'HEADER', 'text' => 'Hello'],
            ['type' => 'BODY', 'text' => 'Welcome {{1}}'],
        ],
    ]);

    app(TemplateService::class)->submitToMeta($template);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'test-waba-123/message_templates')
            && $request['name'] === 'test_submit'
            && $request['language'] === 'ms'
            && $request['category'] === 'UTILITY'
            && $request['components'][0]['format'] === 'TEXT';
    });

    $template->refresh();
    expect($template->meta_template_id)->toBe('meta-tpl-456');
    expect($template->status)->toBe('PENDING');
    expect($template->last_synced_at)->not->toBeNull();
});

it('throws exception when submitting to Meta fails', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Template name already exists'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->create();

    app(TemplateService::class)->submitToMeta($template);
})->throws(RuntimeException::class, 'Failed to submit template to Meta: Template name already exists');

it('throws exception when Meta credentials are missing for submit', function () {
    $settings = app(SettingsService::class);
    $settings->set('meta_waba_id', '');
    $settings->set('meta_access_token', '');

    $template = WhatsAppTemplate::factory()->create();

    app(TemplateService::class)->submitToMeta($template);
})->throws(RuntimeException::class, 'Meta WABA ID and access token are required');

it('updates a template on Meta successfully', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'name' => 'test_update',
        'category' => 'utility',
        'meta_template_id' => 'meta-789',
        'components' => [
            ['type' => 'BODY', 'text' => 'Updated body {{1}}'],
        ],
    ]);

    app(TemplateService::class)->updateOnMeta($template);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'meta-789')
            && $request['category'] === 'UTILITY';
    });

    expect($template->fresh()->last_synced_at)->not->toBeNull();
});

it('throws exception when updating template without meta_template_id', function () {
    $template = WhatsAppTemplate::factory()->create(['meta_template_id' => null]);

    app(TemplateService::class)->updateOnMeta($template);
})->throws(RuntimeException::class, 'Template has not been submitted to Meta yet');

it('throws exception when Meta update fails', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid parameter'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-fail-123',
    ]);

    app(TemplateService::class)->updateOnMeta($template);
})->throws(RuntimeException::class, 'Failed to update template on Meta: Invalid parameter');

it('deletes a template from Meta successfully', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'name' => 'test_delete',
        'meta_template_id' => 'meta-del-123',
    ]);
    $templateId = $template->id;

    app(TemplateService::class)->deleteFromMeta($template);

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), 'message_templates')
            && $request['name'] === 'test_delete';
    });

    expect(WhatsAppTemplate::find($templateId))->toBeNull();
});

it('throws exception when deleting template without meta_template_id', function () {
    $template = WhatsAppTemplate::factory()->create(['meta_template_id' => null]);

    app(TemplateService::class)->deleteFromMeta($template);
})->throws(RuntimeException::class, 'Template has not been submitted to Meta yet');

it('throws exception when Meta delete fails', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Template not found'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-notfound',
    ]);

    app(TemplateService::class)->deleteFromMeta($template);
})->throws(RuntimeException::class, 'Failed to delete template from Meta: Template not found');

it('submits template to Meta from Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => 'meta-volt-123',
            'status' => 'PENDING',
            'category' => 'UTILITY',
        ], 200),
    ]);

    $template = WhatsAppTemplate::factory()->create([
        'meta_template_id' => null,
        'components' => [['type' => 'BODY', 'text' => 'Hello']],
    ]);

    Livewire\Volt\Volt::test('admin.whatsapp-templates')
        ->call('submitTemplateToMeta', $template->id)
        ->assertSee('Template submitted to Meta for approval.');

    expect($template->fresh()->meta_template_id)->toBe('meta-volt-123');
});

it('updates template on Meta from Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-update-volt',
    ]);

    Livewire\Volt\Volt::test('admin.whatsapp-templates')
        ->call('updateTemplateOnMeta', $template->id)
        ->assertSee('Template updated on Meta.');
});

it('deletes template from Meta via Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-del-volt',
    ]);
    $templateId = $template->id;

    Livewire\Volt\Volt::test('admin.whatsapp-templates')
        ->call('deleteFromMeta', $templateId);

    expect(WhatsAppTemplate::find($templateId))->toBeNull();
});

it('handles Meta submit error in Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Name conflict'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->create([
        'meta_template_id' => null,
        'components' => [['type' => 'BODY', 'text' => 'Test']],
    ]);

    Livewire\Volt\Volt::test('admin.whatsapp-templates')
        ->call('submitTemplateToMeta', $template->id)
        ->assertSee('Name conflict');
});
