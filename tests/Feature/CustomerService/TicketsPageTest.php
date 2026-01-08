<?php

declare(strict_types=1);

use App\Models\Ticket;
use App\Models\User;

test('admin can access tickets index page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.customer-service.tickets.index'));

    $response->assertSuccessful();
    $response->assertSee('Support Tickets');
});

test('admin can access tickets create page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.customer-service.tickets.create'));

    $response->assertSuccessful();
    $response->assertSee('Create New Ticket');
});

test('admin can view ticket details', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $ticket = Ticket::create([
        'ticket_number' => Ticket::generateTicketNumber(),
        'subject' => 'Test Ticket Subject',
        'description' => 'Test ticket description for testing purposes.',
        'category' => 'inquiry',
        'status' => 'open',
        'priority' => 'medium',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.customer-service.tickets.show', $ticket));

    $response->assertSuccessful();
    $response->assertSee($ticket->ticket_number);
    $response->assertSee('Test Ticket Subject');
});

test('tickets index shows status tabs with counts', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.customer-service.tickets.index'));

    $response->assertSuccessful();
    $response->assertSee('All');
    $response->assertSee('Open');
    $response->assertSee('In Progress');
    $response->assertSee('Resolved');
    $response->assertSee('Closed');
});

test('non-admin cannot access tickets page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)
        ->get(route('admin.customer-service.tickets.index'));

    $response->assertForbidden();
});
