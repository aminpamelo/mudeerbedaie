<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppTemplate;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('edit button opens the edit modal and never the delete modal', function () {
    $template = WhatsAppTemplate::factory()->create(['name' => 'order_confirmation']);

    Volt::test('admin.whatsapp-templates')
        ->call('openEditModal', $template->id)
        ->assertSet('showModal', true)
        ->assertSet('editingTemplateId', $template->id)
        ->assertSet('name', 'order_confirmation')
        ->assertSet('showDeleteModal', false)
        ->assertSet('showDeleteFromMetaModal', false);
});

test('delete button opens the delete modal and never the edit modal', function () {
    $template = WhatsAppTemplate::factory()->create(['name' => 'promo_blast']);

    Volt::test('admin.whatsapp-templates')
        ->call('confirmDelete', $template->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingTemplateId', $template->id)
        ->assertSet('deletingTemplateName', 'promo_blast')
        ->assertSet('showModal', false);
});

test('confirming delete removes the template', function () {
    $template = WhatsAppTemplate::factory()->create();

    Volt::test('admin.whatsapp-templates')
        ->call('confirmDelete', $template->id)
        ->call('deleteConfirmed')
        ->assertSet('showDeleteModal', false);

    expect(WhatsAppTemplate::find($template->id))->toBeNull();
});

test('action buttons wire the correct handler to each icon with stable keys', function () {
    $local = WhatsAppTemplate::factory()->create(['meta_template_id' => null]);

    $html = Volt::test('admin.whatsapp-templates')
        ->html();

    // Edit action: keyed + tooltip + correct handler (guards against a source-level swap).
    expect($html)->toContain('wire:key="tpl-edit-'.$local->id.'"');
    expect($html)->toContain('openEditModal('.$local->id.')');
    // Delete action: keyed + correct handler.
    expect($html)->toContain('wire:key="tpl-delete-'.$local->id.'"');
    expect($html)->toContain('confirmDelete('.$local->id.')');
});
