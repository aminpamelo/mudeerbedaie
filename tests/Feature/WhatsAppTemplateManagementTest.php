<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\TemplateService;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the templates page', function () {
    $this->get(route('admin.whatsapp.templates'))
        ->assertSuccessful()
        ->assertSeeLivewire('admin.whatsapp-templates');
});

it('lists existing templates', function () {
    $template = WhatsAppTemplate::factory()->create(['name' => 'order_confirm']);

    Volt::test('admin.whatsapp-templates')
        ->assertSee('order_confirm');
});

it('filters templates by status', function () {
    WhatsAppTemplate::factory()->approved()->create(['name' => 'approved_tpl']);
    WhatsAppTemplate::factory()->pending()->create(['name' => 'pending_tpl']);

    Volt::test('admin.whatsapp-templates')
        ->call('setStatusFilter', 'APPROVED')
        ->assertSee('approved_tpl')
        ->assertDontSee('pending_tpl');
});

it('filters templates by category', function () {
    WhatsAppTemplate::factory()->marketing()->create(['name' => 'marketing_tpl']);
    WhatsAppTemplate::factory()->utility()->create(['name' => 'utility_tpl']);

    Volt::test('admin.whatsapp-templates')
        ->set('categoryFilter', 'marketing')
        ->assertSee('marketing_tpl')
        ->assertDontSee('utility_tpl');
});

it('searches templates by name', function () {
    WhatsAppTemplate::factory()->create(['name' => 'welcome_message']);
    WhatsAppTemplate::factory()->create(['name' => 'order_shipped']);

    Volt::test('admin.whatsapp-templates')
        ->set('search', 'welcome')
        ->assertSee('welcome_message')
        ->assertDontSee('order_shipped');
});

it('creates a template', function () {
    Volt::test('admin.whatsapp-templates')
        ->call('openCreateModal')
        ->assertSet('showModal', true)
        ->set('name', 'new_template')
        ->set('language', 'en')
        ->set('category', 'marketing')
        ->set('status', 'PENDING')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    expect(WhatsAppTemplate::where('name', 'new_template')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    Volt::test('admin.whatsapp-templates')
        ->call('openCreateModal')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('validates name format must be lowercase with underscores', function () {
    Volt::test('admin.whatsapp-templates')
        ->call('openCreateModal')
        ->set('name', 'Invalid Name')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('validates unique name per language', function () {
    WhatsAppTemplate::factory()->create(['name' => 'existing_tpl', 'language' => 'ms']);

    Volt::test('admin.whatsapp-templates')
        ->call('openCreateModal')
        ->set('name', 'existing_tpl')
        ->set('language', 'ms')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('allows same name with different language', function () {
    WhatsAppTemplate::factory()->create(['name' => 'welcome', 'language' => 'ms']);

    Volt::test('admin.whatsapp-templates')
        ->call('openCreateModal')
        ->set('name', 'welcome')
        ->set('language', 'en')
        ->call('save')
        ->assertHasNoErrors();

    expect(WhatsAppTemplate::where('name', 'welcome')->count())->toBe(2);
});

it('edits a template', function () {
    $template = WhatsAppTemplate::factory()->create(['name' => 'old_name', 'category' => 'utility']);

    Volt::test('admin.whatsapp-templates')
        ->call('openEditModal', $template->id)
        ->assertSet('showModal', true)
        ->assertSet('name', 'old_name')
        ->set('category', 'marketing')
        ->call('save')
        ->assertHasNoErrors();

    expect($template->fresh()->category)->toBe('marketing');
});

it('marks meta-synced templates as read-only for name and language', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name' => 'meta_tpl',
        'meta_template_id' => 'meta-123',
    ]);

    Volt::test('admin.whatsapp-templates')
        ->call('openEditModal', $template->id)
        ->assertSet('isMetaSynced', true);
});

it('deletes a template', function () {
    $template = WhatsAppTemplate::factory()->create();

    Volt::test('admin.whatsapp-templates')
        ->call('confirmDelete', $template->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingTemplateId', $template->id)
        ->call('deleteConfirmed');

    expect(WhatsAppTemplate::find($template->id))->toBeNull();
});

it('adds and removes components', function () {
    Volt::test('admin.whatsapp-templates')
        ->call('openCreateModal')
        ->assertSet('components', [])
        ->call('addComponent')
        ->assertCount('components', 1)
        ->call('addComponent')
        ->assertCount('components', 2)
        ->call('removeComponent', 0)
        ->assertCount('components', 1);
});

it('creates a template with components', function () {
    Volt::test('admin.whatsapp-templates')
        ->call('openCreateModal')
        ->set('name', 'tpl_with_body')
        ->set('language', 'ms')
        ->set('category', 'utility')
        ->set('status', 'PENDING')
        ->set('components', [
            ['type' => 'HEADER', 'text' => 'Hello'],
            ['type' => 'BODY', 'text' => 'Welcome {{1}}'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $template = WhatsAppTemplate::where('name', 'tpl_with_body')->first();
    expect($template->components)->toHaveCount(2);
    expect($template->components[0]['type'])->toBe('HEADER');
});

it('syncs templates from meta', function () {
    $mock = Mockery::mock(TemplateService::class);
    $mock->shouldReceive('syncFromMeta')->once()->andReturn(5);
    app()->instance(TemplateService::class, $mock);

    Volt::test('admin.whatsapp-templates')
        ->call('syncFromMeta')
        ->assertSee('Synced 5 templates from Meta.');
});

it('handles sync error gracefully', function () {
    $mock = Mockery::mock(TemplateService::class);
    $mock->shouldReceive('syncFromMeta')->once()->andThrow(new RuntimeException('WABA ID missing'));
    app()->instance(TemplateService::class, $mock);

    Volt::test('admin.whatsapp-templates')
        ->call('syncFromMeta')
        ->assertSee('WABA ID missing');
});

it('prevents non-admin access', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.whatsapp.templates'))
        ->assertForbidden();
});
