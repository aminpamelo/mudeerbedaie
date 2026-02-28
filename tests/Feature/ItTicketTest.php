<?php

use App\Models\ItTicket;
use App\Models\ItTicketComment;
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

it('returns correct type and priority colors', function () {
    $ticket = new ItTicket(['type' => 'bug', 'priority' => 'urgent']);

    expect($ticket->getTypeColor())->toBe('red');
    expect($ticket->getPriorityColor())->toBe('red');
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
        ->set('type', 'bug')
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
        ->set('type', 'feature')
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

it('allows admin to create tickets via quick create', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.it-board.index')
        ->call('openQuickCreate', 'todo')
        ->assertSet('showCreateModal', true)
        ->assertSet('createInStatus', 'todo')
        ->set('newTitle', 'Fix API endpoint')
        ->set('newType', 'bug')
        ->set('newPriority', 'high')
        ->call('quickCreate')
        ->assertSet('showCreateModal', false);

    $ticket = ItTicket::where('title', 'Fix API endpoint')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->status)->toBe('todo');
    expect($ticket->type)->toBe('bug');
});

it('allows admin to update ticket status via drag', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->create(['status' => 'backlog', 'position' => 0]);

    Volt::test('admin.it-board.index')
        ->call('updateTicketStatus', $ticket->id, 'in_progress', 0);

    expect($ticket->fresh()->status)->toBe('in_progress');
});

it('sets completed_at when moving to done', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->create(['status' => 'testing']);

    Volt::test('admin.it-board.index')
        ->call('updateTicketStatus', $ticket->id, 'done', 0);

    expect($ticket->fresh()->completed_at)->not->toBeNull();
});

it('clears completed_at when moving out of done', function () {
    $this->actingAs($this->admin);
    $ticket = ItTicket::factory()->done()->create();

    Volt::test('admin.it-board.index')
        ->call('updateTicketStatus', $ticket->id, 'review', 0);

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

    Volt::test('admin.it-board.create')
        ->set('title', 'Implement dark mode')
        ->set('description', 'Add dark mode support to all pages')
        ->set('type', 'feature')
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
    expect($ticket->due_date->format('Y-m-d'))->toBe('2026-04-01');
});
