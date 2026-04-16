<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\FunnelEmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('broadcast model returns effective content for text editor', function () {
    $broadcast = Broadcast::factory()->create([
        'content' => '<p>Text content</p>',
        'html_content' => null,
        'editor_type' => 'text',
    ]);

    expect($broadcast->getEffectiveContent())->toBe('<p>Text content</p>');
    expect($broadcast->isVisualEditor())->toBeFalse();
});

test('broadcast model returns effective content for visual editor', function () {
    $broadcast = Broadcast::factory()->create([
        'content' => '<p>Text fallback</p>',
        'html_content' => '<html><body>Visual content</body></html>',
        'editor_type' => 'visual',
    ]);

    expect($broadcast->getEffectiveContent())->toBe('<html><body>Visual content</body></html>');
    expect($broadcast->isVisualEditor())->toBeTrue();
});

test('broadcast create step 3 shows template picker', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    FunnelEmailTemplate::factory()->create([
        'name' => 'Welcome Template',
        'category' => 'welcome',
        'is_active' => true,
    ]);

    Volt::test('crm.broadcast-create')
        ->set('currentStep', 3)
        ->assertSee('Choose a Template')
        ->assertSee('Welcome Template');
});

test('broadcast create can select a funnel email template', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $template = FunnelEmailTemplate::factory()->visual()->create([
        'name' => 'Test Template',
        'subject' => 'Template Subject',
        'html_content' => '<html><body>Template HTML</body></html>',
        'is_active' => true,
    ]);

    Volt::test('crm.broadcast-create')
        ->set('currentStep', 3)
        ->call('selectTemplate', $template->id)
        ->assertSet('selectedTemplateId', $template->id)
        ->assertSet('editor_type', 'visual')
        ->assertSet('subject', 'Template Subject');
});

test('broadcast create can clear selected template', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $template = FunnelEmailTemplate::factory()->visual()->create([
        'name' => 'Test Template',
        'subject' => 'Template Subject',
        'html_content' => '<html><body>HTML</body></html>',
        'is_active' => true,
    ]);

    Volt::test('crm.broadcast-create')
        ->set('currentStep', 3)
        ->call('selectTemplate', $template->id)
        ->call('clearTemplate')
        ->assertSet('selectedTemplateId', null)
        ->assertSet('editor_type', 'text')
        ->assertSet('html_content', '');
});

test('broadcast builder page loads for draft broadcast', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $broadcast = Broadcast::factory()->create([
        'status' => 'draft',
        'name' => 'Test Broadcast',
    ]);

    $this->actingAs($admin)
        ->get(route('crm.broadcasts.builder', $broadcast))
        ->assertSuccessful();
});
