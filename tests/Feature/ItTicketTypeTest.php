<?php

use App\Models\ItTicket;
use App\Models\ItTicketType;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('seeds the default ticket types', function () {
    expect(ItTicketType::orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Bug', 'Feature', 'Task', 'Improvement']);
});

it('lets an admin add a custom type from the board', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.it-board.index')
        ->call('openTypeManager')
        ->assertSet('showTypeModal', true)
        ->set('typeName', 'Research')
        ->set('typeColor', '#a855f7')
        ->call('addType')
        ->assertHasNoErrors();

    expect(ItTicketType::where('name', 'Research')->where('color', '#a855f7')->exists())->toBeTrue();
});

it('validates the custom type name and color', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.it-board.index')
        ->set('typeName', 'A')        // too short
        ->set('typeColor', 'purple')  // not a hex value
        ->call('addType')
        ->assertHasErrors(['typeName', 'typeColor']);
});

it('lets an admin rename and recolor a type', function () {
    $this->actingAs($this->admin);
    $type = ItTicketType::where('name', 'Task')->first();

    Volt::test('admin.it-board.index')
        ->call('startEditType', $type->id)
        ->assertSet('editTypeName', 'Task')
        ->set('editTypeName', 'Chore')
        ->set('editTypeColor', '#0ea5e9')
        ->call('updateType')
        ->assertHasNoErrors();

    $type->refresh();
    expect($type->name)->toBe('Chore');
    expect($type->color)->toBe('#0ea5e9');
});

it('nulls a ticket type when that type is deleted', function () {
    $this->actingAs($this->admin);
    $type = ItTicketType::factory()->create(['name' => 'Throwaway']);
    $ticket = ItTicket::factory()->ofType($type)->create();

    expect($ticket->type_id)->toBe($type->id);

    Volt::test('admin.it-board.index')
        ->call('deleteType', $type->id);

    expect(ItTicketType::find($type->id))->toBeNull();
    expect($ticket->fresh()->type_id)->toBeNull();
});

it('filters the board by type', function () {
    $this->actingAs($this->admin);
    $bug = ItTicketType::where('name', 'Bug')->first();
    $feature = ItTicketType::where('name', 'Feature')->first();

    ItTicket::factory()->ofType($bug)->create(['title' => 'A bug ticket', 'status' => 'backlog']);
    ItTicket::factory()->ofType($feature)->create(['title' => 'A feature ticket', 'status' => 'backlog']);

    Volt::test('admin.it-board.index')
        ->set('typeFilter', (string) $bug->id)
        ->assertSee('A bug ticket')
        ->assertDontSee('A feature ticket');
});

it('assigns a type to a ticket and exposes it through the relationship', function () {
    $type = ItTicketType::where('name', 'Improvement')->first();
    $ticket = ItTicket::factory()->ofType($type)->create();

    expect($ticket->type)->not->toBeNull();
    expect($ticket->type->name)->toBe('Improvement');
    expect($type->tickets()->count())->toBe(1);
});
