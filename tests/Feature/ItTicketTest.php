<?php

use App\Models\ItTicket;
use App\Models\ItTicketType;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->student = User::factory()->create(['role' => 'student']);
    $this->teacher = User::factory()->create(['role' => 'teacher']);
});

// --- Model Tests ---

it('generates unique ticket numbers', function () {
    $number1 = ItTicket::generateTicketNumber();
    $number2 = ItTicket::generateTicketNumber();

    expect($number1)->toStartWith('IT-');
    expect($number1)->not->toBe($number2);
});

it('detects overdue tickets', function () {
    $overdue = ItTicket::factory()->create([
        'due_date' => now()->subDay(),
        'status' => 'in_progress',
    ]);

    $notOverdue = ItTicket::factory()->create([
        'due_date' => now()->addDay(),
        'status' => 'in_progress',
    ]);

    $done = ItTicket::factory()->create([
        'due_date' => now()->subDay(),
        'status' => 'done',
    ]);

    expect($overdue->isOverdue())->toBeTrue();
    expect($notOverdue->isOverdue())->toBeFalse();
    expect($done->isOverdue())->toBeFalse();
});

it('shows the explicit due date on the deadline chip for every state', function () {
    $today = ItTicket::factory()->create(['due_date' => now(), 'status' => 'in_progress']);
    $soon = ItTicket::factory()->create(['due_date' => now()->addDays(2), 'status' => 'in_progress']);
    $later = ItTicket::factory()->create(['due_date' => now()->addDays(10), 'status' => 'in_progress']);
    $overdue = ItTicket::factory()->create(['due_date' => now()->subDay(), 'status' => 'in_progress']);
    $done = ItTicket::factory()->create(['due_date' => now()->subDay(), 'status' => 'done']);

    expect($today->deadlineMeta()['short'])->toBe(now()->format('j M'));
    expect($soon->deadlineMeta()['short'])->toBe(now()->addDays(2)->format('j M'));
    expect($later->deadlineMeta()['short'])->toBe(now()->addDays(10)->format('j M'));
    expect($overdue->deadlineMeta()['short'])->toBe(now()->subDay()->format('j M'));
    expect($done->deadlineMeta()['short'])->toBe(now()->subDay()->format('j M'));
});

it('falls back to a placeholder when a ticket has no due date', function () {
    $ticket = ItTicket::factory()->create(['due_date' => null, 'status' => 'in_progress']);

    expect($ticket->deadlineMeta()['key'])->toBe('none');
    expect($ticket->deadlineMeta()['short'])->toBe('—');
});

it('returns the type hex color and priority color', function () {
    $type = ItTicketType::factory()->create(['name' => 'Bug', 'color' => '#ef4444']);
    $ticket = ItTicket::factory()->ofType($type)->create(['priority' => 'urgent']);

    expect($ticket->getTypeColor())->toBe('#ef4444');
    expect($ticket->getTypeLabel())->toBe('Bug');
    expect($ticket->getPriorityColor())->toBe('red');
});

it('falls back to a neutral type color when a ticket has no type', function () {
    $ticket = ItTicket::factory()->create(['type_id' => null]);

    expect($ticket->getTypeColor())->toBe('#71717a');
    expect($ticket->getTypeLabel())->toBe('No type');
});

// --- IT Request Submission (All Users) ---

it('allows any authenticated user to access the IT request form', function () {
    $this->actingAs($this->student)
        ->get(route('it-request.create'))
        ->assertSuccessful();
});

it('allows students to submit IT requests', function () {
    $this->actingAs($this->student);

    Volt::test('it-request.create')
        ->set('title', 'Login page is broken')
        ->set('description', 'Cannot login after update')
        ->set('typeId', ItTicketType::where('name', 'Bug')->value('id'))
        ->set('priority', 'high')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(ItTicket::where('title', 'Login page is broken')->exists())->toBeTrue();
    expect(ItTicket::first()->reporter_id)->toBe($this->student->id);
    expect(ItTicket::first()->status)->toBe('backlog');
});

it('validates required fields on IT request form', function () {
    $this->actingAs($this->student);

    Volt::test('it-request.create')
        ->set('title', '')
        ->call('submit')
        ->assertHasErrors(['title']);
});

it('allows teachers to submit IT requests', function () {
    $this->actingAs($this->teacher);

    Volt::test('it-request.create')
        ->set('title', 'Need grade export feature')
        ->set('typeId', ItTicketType::where('name', 'Feature')->value('id'))
        ->set('priority', 'medium')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ItTicket::where('title', 'Need grade export feature')->exists())->toBeTrue();
});

// --- My IT Requests Page ---

it('shows only the users own requests', function () {
    ItTicket::factory()->create(['reporter_id' => $this->student->id, 'title' => 'My Bug']);
    ItTicket::factory()->create(['reporter_id' => $this->admin->id, 'title' => 'Admin Bug']);

    $this->actingAs($this->student)
        ->get(route('it-request.index'))
        ->assertSee('My Bug')
        ->assertDontSee('Admin Bug');
});

// --- Admin Board Access ---

it('allows admin to access the IT board', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.it-board.index'))
        ->assertSuccessful();
});

it('denies non-admin users access to IT board', function () {
    $this->actingAs($this->student)
        ->get(route('admin.it-board.index'))
        ->assertForbidden();
});

// --- Admin Kanban Operations ---

it('allows admin to create tickets via the create modal', function () {
    $this->actingAs($this->admin);
    $type = ItTicketType::where('name', 'Bug')->first();

    Volt::test('admin.it-board.index')
        ->call('openCreate', 'todo')
        ->assertSet('showCreateModal', true)
        ->assertSet('cStatus', 'todo')
        ->set('cTitle', 'Fix API endpoint')
        ->set('cTypeId', $type->id)
        ->set('cPriority', 'high')
        ->call('createTicket')
        ->assertSet('showCreateModal', false);

    $ticket = ItTicket::where('title', 'Fix API endpoint')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->status)->toBe('todo');
    expect($ticket->type_id)->toBe($type->id);
    expect($ticket->type->name)->toBe('Bug');
});

it('allows admin to update ticket status via drag', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->create(['status' => 'backlog', 'position' => 0]);

    Volt::test('admin.it-board.index')
        ->call('moveTicket', $ticket->id, 'in_progress', 0, [$ticket->id]);

    expect($ticket->fresh()->status)->toBe('in_progress');
});

it('builds type tabs with per-type counts and a No type bucket', function () {
    $this->actingAs($this->admin);

    $kelasify = ItTicketType::factory()->create(['name' => 'Kelasify']);
    $infra = ItTicketType::factory()->create(['name' => 'INFRA']);

    ItTicket::factory()->count(2)->create(['type_id' => $kelasify->id]);
    ItTicket::factory()->create(['type_id' => $infra->id]);
    ItTicket::factory()->create(['type_id' => null]);

    $tabs = collect(Volt::test('admin.it-board.index')->instance()->typeTabs)->keyBy('name');

    expect($tabs['All']['count'])->toBe(4);
    expect($tabs['Kelasify']['count'])->toBe(2);
    expect($tabs['INFRA']['count'])->toBe(1);
    expect($tabs['No type']['count'])->toBe(1);
    expect($tabs['No type']['key'])->toBe('none');
});

it('omits the No type tab when every ticket has a type', function () {
    $this->actingAs($this->admin);

    $type = ItTicketType::factory()->create(['name' => 'Kelasify']);
    ItTicket::factory()->create(['type_id' => $type->id]);

    $names = collect(Volt::test('admin.it-board.index')->instance()->typeTabs)->pluck('name');

    expect($names)->toContain('All', 'Kelasify');
    expect($names)->not->toContain('No type');
});

it('filters the board to a single type when a type tab is active', function () {
    $this->actingAs($this->admin);

    $kelasify = ItTicketType::factory()->create(['name' => 'Kelasify']);
    $infra = ItTicketType::factory()->create(['name' => 'INFRA']);

    ItTicket::factory()->create(['type_id' => $kelasify->id, 'title' => 'Kelasify ticket', 'status' => 'backlog']);
    ItTicket::factory()->create(['type_id' => $infra->id, 'title' => 'Infra ticket', 'status' => 'backlog']);

    Volt::test('admin.it-board.index')
        ->set('typeFilter', (string) $kelasify->id)
        ->assertSee('Kelasify ticket')
        ->assertDontSee('Infra ticket');
});

it('filters the board to untyped tickets via the No type tab', function () {
    $this->actingAs($this->admin);

    $type = ItTicketType::factory()->create(['name' => 'Kelasify']);
    ItTicket::factory()->create(['type_id' => $type->id, 'title' => 'Typed ticket', 'status' => 'backlog']);
    ItTicket::factory()->create(['type_id' => null, 'title' => 'Untyped ticket', 'status' => 'backlog']);

    Volt::test('admin.it-board.index')
        ->set('typeFilter', 'none')
        ->assertSee('Untyped ticket')
        ->assertDontSee('Typed ticket');
});

it('sets completed_at when moving to done', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->create(['status' => 'testing']);

    Volt::test('admin.it-board.index')
        ->call('moveTicket', $ticket->id, 'done', 0, [$ticket->id]);

    expect($ticket->fresh()->completed_at)->not->toBeNull();
});

it('clears completed_at when moving out of done', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->done()->create();

    Volt::test('admin.it-board.index')
        ->call('moveTicket', $ticket->id, 'review', 0, [$ticket->id]);

    expect($ticket->fresh()->completed_at)->toBeNull();
});

// --- Ticket Detail Page ---

it('allows admin to view ticket details', function () {
    $ticket = ItTicket::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.it-board.show', $ticket))
        ->assertSuccessful()
        ->assertSee($ticket->title);
});

it('allows admin to add comments', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->create();

    Volt::test('admin.it-board.show', ['itTicket' => $ticket])
        ->set('commentBody', 'This is a test comment')
        ->call('addComment')
        ->assertHasNoErrors();

    expect($ticket->comments()->count())->toBe(1);
    expect($ticket->fresh()->comments->first()->body)->toBe('This is a test comment');
});

it('allows admin to update ticket fields from detail page', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->create(['priority' => 'low']);

    Volt::test('admin.it-board.show', ['itTicket' => $ticket])
        ->set('priority', 'urgent')
        ->call('updateField', 'priority');

    expect($ticket->fresh()->priority)->toBe('urgent');
});

// --- Admin Ticket Create Page ---

it('allows admin to create tickets with full details', function () {
    $this->actingAs($this->admin);
    $assignee = User::factory()->create(['role' => 'admin']);

    $type = ItTicketType::where('name', 'Feature')->first();

    Volt::test('admin.it-board.create')
        ->set('title', 'Implement dark mode')
        ->set('description', 'Add dark mode support to all pages')
        ->set('typeId', $type->id)
        ->set('priority', 'medium')
        ->set('status', 'todo')
        ->set('assigneeId', $assignee->id)
        ->set('dueDate', '2026-04-01')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $ticket = ItTicket::where('title', 'Implement dark mode')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->assignee_id)->toBe($assignee->id);
    expect($ticket->type_id)->toBe($type->id);
    expect($ticket->due_date->format('Y-m-d'))->toBe('2026-04-01');
});
