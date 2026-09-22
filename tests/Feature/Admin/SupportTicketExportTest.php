<?php

declare(strict_types=1);

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/**
 * Build the exact CSV bytes the component is expected to stream, mirroring its
 * BOM + fputcsv output so content comparisons stay robust to fputcsv quoting.
 */
function ticketCsvBytes(array $rows): string
{
    $out = fopen('php://temp', 'r+');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    rewind($out);

    return stream_get_contents($out);
}

it('downloads a CSV from the support tickets page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Ticket::create([
        'ticket_number' => 'TKT-TEST-0001',
        'subject' => 'Broken book',
        'description' => 'Book arrived damaged',
        'category' => 'complaint',
        'status' => 'open',
        'priority' => 'high',
    ]);

    Volt::actingAs($admin)->test('admin.customer-service.tickets-index')
        ->call('export')
        ->assertFileDownloaded();
});

it('names the export file with a timestamp', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Carbon::setTestNow(Carbon::create(2026, 1, 2, 3, 4, 5));

    Volt::actingAs($admin)->test('admin.customer-service.tickets-index')
        ->call('export')
        ->assertFileDownloaded('support-tickets-20260102-030405.csv');

    Carbon::setTestNow();
});

it('respects the active filters and exports the expected columns', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Carbon::setTestNow(Carbon::create(2026, 1, 2, 3, 4, 5));

    $open = Ticket::create([
        'ticket_number' => 'TKT-OPEN-0001',
        'subject' => 'Open issue',
        'description' => 'Still open',
        'category' => 'complaint',
        'status' => 'open',
        'priority' => 'urgent',
    ]);

    Ticket::create([
        'ticket_number' => 'TKT-CLOSED-0001',
        'subject' => 'Closed issue',
        'description' => 'Already closed',
        'category' => 'inquiry',
        'status' => 'closed',
        'priority' => 'low',
    ]);

    $expected = ticketCsvBytes([
        ['Ticket Number', 'Subject', 'Order', 'Customer', 'Category', 'Priority', 'Status', 'Assigned To', 'Created'],
        ['TKT-OPEN-0001', 'Open issue', '', 'Unknown', 'Complaint', 'Urgent', 'Open', 'Unassigned', $open->created_at->format('Y-m-d H:i')],
    ]);

    Volt::actingAs($admin)->test('admin.customer-service.tickets-index')
        ->set('statusFilter', 'open')
        ->call('export')
        ->assertFileDownloaded('support-tickets-20260102-030405.csv', $expected);

    Carbon::setTestNow();
});
