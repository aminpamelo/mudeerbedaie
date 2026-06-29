<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->course = Course::factory()->create(['created_by' => $this->admin->id]);

    $teacher = createTeacher();

    $this->class = ClassModel::factory()->create([
        'course_id' => $this->course->id,
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
        'whatsapp_group_link' => null,
    ]);
});

test('the whatsapp group field shows an empty-state indicator when no link exists', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->assertSee('WhatsApp Group')
        ->assertSee('No link added yet')
        ->assertSee('Add Link')
        ->assertDontSee('Join WhatsApp');
});

test('admin can add a whatsapp group link inline', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('startEditWhatsappLink')
        ->assertSet('editingWhatsappLink', true)
        ->set('whatsappLinkInput', 'https://chat.whatsapp.com/AbCdEf123456')
        ->call('saveWhatsappLink')
        ->assertHasNoErrors()
        ->assertSet('editingWhatsappLink', false)
        ->assertSee('Join WhatsApp');

    expect($this->class->fresh()->whatsapp_group_link)->toBe('https://chat.whatsapp.com/AbCdEf123456');
});

test('start editing pre-fills the input with the existing link', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/ExistingLink']);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('startEditWhatsappLink')
        ->assertSet('whatsappLinkInput', 'https://chat.whatsapp.com/ExistingLink');
});

test('admin can update an existing whatsapp group link', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/OldLink']);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('startEditWhatsappLink')
        ->set('whatsappLinkInput', 'https://chat.whatsapp.com/NewLink')
        ->call('saveWhatsappLink')
        ->assertHasNoErrors();

    expect($this->class->fresh()->whatsapp_group_link)->toBe('https://chat.whatsapp.com/NewLink');
});

test('saving an empty value removes the link', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/ToRemove']);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('startEditWhatsappLink')
        ->set('whatsappLinkInput', '')
        ->call('saveWhatsappLink')
        ->assertHasNoErrors()
        ->assertSee('No link added yet');

    expect($this->class->fresh()->whatsapp_group_link)->toBeNull();
});

test('an invalid url is rejected with a validation error', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('startEditWhatsappLink')
        ->set('whatsappLinkInput', 'not-a-valid-url')
        ->call('saveWhatsappLink')
        ->assertHasErrors(['whatsappLinkInput' => 'url']);

    expect($this->class->fresh()->whatsapp_group_link)->toBeNull();
});

test('non-http(s) schemes are rejected', function (string $url) {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('startEditWhatsappLink')
        ->set('whatsappLinkInput', $url)
        ->call('saveWhatsappLink')
        ->assertHasErrors(['whatsappLinkInput' => 'url']);

    expect($this->class->fresh()->whatsapp_group_link)->toBeNull();
})->with([
    'ftp' => 'ftp://example.com/file',
    'javascript' => 'javascript://alert(1)',
]);

test('cancelling editing discards changes', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/Keep']);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('startEditWhatsappLink')
        ->set('whatsappLinkInput', 'https://chat.whatsapp.com/Discarded')
        ->call('cancelEditWhatsappLink')
        ->assertSet('editingWhatsappLink', false);

    expect($this->class->fresh()->whatsapp_group_link)->toBe('https://chat.whatsapp.com/Keep');
});
