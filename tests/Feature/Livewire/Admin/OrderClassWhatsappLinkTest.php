<?php

declare(strict_types=1);

use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->student = Student::factory()->create();
    $this->order = ProductOrder::factory()->create(['student_id' => $this->student->id]);
    $this->class = ClassModel::factory()->create([
        'status' => 'active',
        'whatsapp_group_link' => null,
    ]);
    $this->approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'product_order_id' => $this->order->id,
    ]);

    $this->actingAs($this->admin);
});

test('shows a "no WhatsApp group" indicator when the assigned class has no link', function () {
    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->assertSee('No WhatsApp group')
        ->assertSee('Add Link')
        ->assertDontSee('WhatsApp group linked');
});

test('shows a "linked" indicator when the assigned class has a link', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/Existing']);

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->assertSee('WhatsApp group linked')
        ->assertDontSee('No WhatsApp group');
});

test('admin can add a class WhatsApp link inline from the order page', function () {
    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->call('startEditClassWhatsapp', $this->approval->id)
        ->assertSet('editingWhatsappApprovalId', $this->approval->id)
        ->assertSet('whatsappLinkInput', '')
        ->set('whatsappLinkInput', 'https://chat.whatsapp.com/AbCdEf123456')
        ->call('saveClassWhatsapp')
        ->assertHasNoErrors()
        ->assertSet('editingWhatsappApprovalId', null)
        ->assertSee('WhatsApp group linked');

    expect($this->class->fresh()->whatsapp_group_link)->toBe('https://chat.whatsapp.com/AbCdEf123456');
});

test('start editing pre-fills the input with the existing class link', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/Existing']);

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->call('startEditClassWhatsapp', $this->approval->id)
        ->assertSet('whatsappLinkInput', 'https://chat.whatsapp.com/Existing');
});

test('saving an empty value removes the class link', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/ToRemove']);

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->call('startEditClassWhatsapp', $this->approval->id)
        ->set('whatsappLinkInput', '')
        ->call('saveClassWhatsapp')
        ->assertHasNoErrors()
        ->assertSee('No WhatsApp group');

    expect($this->class->fresh()->whatsapp_group_link)->toBeNull();
});

test('an invalid or non-http(s) link is rejected', function (string $url) {
    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->call('startEditClassWhatsapp', $this->approval->id)
        ->set('whatsappLinkInput', $url)
        ->call('saveClassWhatsapp')
        ->assertHasErrors(['whatsappLinkInput' => 'url']);

    expect($this->class->fresh()->whatsapp_group_link)->toBeNull();
})->with([
    'plain text' => 'not-a-valid-url',
    'ftp' => 'ftp://example.com/file',
    'javascript' => 'javascript://alert(1)',
]);

test('cancelling discards inline edits', function () {
    $this->class->update(['whatsapp_group_link' => 'https://chat.whatsapp.com/Keep']);

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->call('startEditClassWhatsapp', $this->approval->id)
        ->set('whatsappLinkInput', 'https://chat.whatsapp.com/Discarded')
        ->call('cancelEditClassWhatsapp')
        ->assertSet('editingWhatsappApprovalId', null);

    expect($this->class->fresh()->whatsapp_group_link)->toBe('https://chat.whatsapp.com/Keep');
});

test('editing is keyed per assignment row, so a duplicate class only opens one editor', function () {
    // Same class can appear in two rows for one order (e.g. the order's linked
    // student changed between assignments), each as a distinct approval.
    $secondStudent = Student::factory()->create();
    $secondApproval = ClassAssignmentApproval::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $secondStudent->id,
        'product_order_id' => $this->order->id,
    ]);

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->call('startEditClassWhatsapp', $this->approval->id)
        ->assertSet('editingWhatsappApprovalId', $this->approval->id)
        ->assertSet('editingWhatsappApprovalId', fn ($v) => $v !== $secondApproval->id);
});

test('cannot edit a class via an approval that belongs to another order', function () {
    $otherOrder = ProductOrder::factory()->create(['student_id' => $this->student->id]);
    $foreignApproval = ClassAssignmentApproval::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'product_order_id' => $otherOrder->id,
    ]);

    Volt::test('admin.orders.order-show', ['order' => $this->order])
        ->call('startEditClassWhatsapp', $foreignApproval->id)
        ->assertSet('editingWhatsappApprovalId', null);
});
