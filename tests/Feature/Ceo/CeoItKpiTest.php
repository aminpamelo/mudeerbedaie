<?php

declare(strict_types=1);

use App\Models\ItTicket;
use App\Models\ItTicketType;
use App\Models\User;
use App\Services\Ceo\CeoDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('renders the ItKpi page for a ceo user', function () {
    $this->actingAs(User::factory()->create(['role' => 'ceo']))
        ->get('/ceo/it-kpi')
        ->assertInertia(fn (Assert $page) => $page
            ->component('ItKpi', false)
            ->has('report.months', 12)
            ->has('report.rows')
            ->has('report.columns.staff')
            ->has('report.summary.heroValue')
            ->where('report.accent', 'violet')
        );
});

it('forbids users without the admin or ceo role', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']))
        ->get('/ceo/it-kpi')
        ->assertForbidden();
});

it('credits a resolved ticket to its assignee with an on-time rate', function () {
    $year = (int) now()->year;
    $engineer = User::factory()->create(['role' => 'admin', 'name' => 'Engineer One']);
    $type = ItTicketType::where('name', 'Bug')->first();

    // Two resolved on-time + one resolved late, all in the current month.
    ItTicket::factory()->count(2)->ofType($type)->create([
        'assignee_id' => $engineer->id,
        'status' => 'done',
        'completed_at' => now()->startOfMonth()->addDays(2),
        'due_date' => now()->startOfMonth()->addDays(5),
    ]);
    ItTicket::factory()->ofType($type)->create([
        'assignee_id' => $engineer->id,
        'status' => 'done',
        'completed_at' => now()->startOfMonth()->addDays(10),
        'due_date' => now()->startOfMonth()->addDays(3),
    ]);

    $report = app(CeoDashboardService::class)->itKpi($year);
    $row = collect($report['rows'])->firstWhere('key', $engineer->id);

    expect($row)->not->toBeNull();
    expect($row['ytdTotal'])->toBe('3');
    expect($row['ytdOnTime'])->toBe('67%');
    expect($row['display'][now()->month - 1])->toBe('3 · 67%');
    expect($report['summary']['heroValue'])->toBe('3');
});

it('excludes unassigned and not-done tickets from resolved totals', function () {
    $year = (int) now()->year;
    $engineer = User::factory()->create(['role' => 'admin']);

    ItTicket::factory()->create(['assignee_id' => null, 'status' => 'done', 'completed_at' => now()]);
    ItTicket::factory()->create(['assignee_id' => $engineer->id, 'status' => 'in_progress', 'completed_at' => null]);

    $report = app(CeoDashboardService::class)->itKpi($year);

    expect($report['summary']['heroValue'])->toBe('0');

    $row = collect($report['rows'])->firstWhere('key', $engineer->id);
    expect($row)->not->toBeNull();
    expect($row['ytdTotal'])->toBe('0');
});

it('lists an open assigned ticket in the staff drill-down', function () {
    $year = (int) now()->year;
    $engineer = User::factory()->create(['role' => 'admin']);
    $type = ItTicketType::where('name', 'Feature')->first();

    ItTicket::factory()->ofType($type)->create([
        'assignee_id' => $engineer->id,
        'status' => 'todo',
        'title' => 'Wire up SSO',
        'completed_at' => null,
    ]);

    $report = app(CeoDashboardService::class)->itKpi($year);
    $row = collect($report['rows'])->firstWhere('key', $engineer->id);
    $ticket = collect($row['tasks'])->firstWhere('title', 'Wire up SSO');

    expect($ticket)->not->toBeNull();
    expect($ticket['status'])->toBe('todo');
    expect($ticket['statusLabel'])->toBe('To Do');
    expect($ticket['category']['name'])->toBe('Feature');
});

it('clamps a future year back to the current year', function () {
    $future = (int) now()->addYears(5)->year;

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get("/ceo/it-kpi?year={$future}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.year', (int) now()->year)
        );
});

it('measures on-time only over resolved tickets that had a due date', function () {
    $year = (int) now()->year;
    $engineer = User::factory()->create(['role' => 'admin']);
    $type = ItTicketType::where('name', 'Task')->first();

    // One resolved on time (had a deadline) + one resolved with no deadline.
    ItTicket::factory()->ofType($type)->create([
        'assignee_id' => $engineer->id,
        'status' => 'done',
        'completed_at' => now()->startOfMonth()->addDays(2),
        'due_date' => now()->startOfMonth()->addDays(5),
    ]);
    ItTicket::factory()->ofType($type)->create([
        'assignee_id' => $engineer->id,
        'status' => 'done',
        'completed_at' => now()->startOfMonth()->addDays(3),
        'due_date' => null,
    ]);

    $report = app(CeoDashboardService::class)->itKpi($year);
    $row = collect($report['rows'])->firstWhere('key', $engineer->id);

    expect($row['ytdTotal'])->toBe('2');      // both resolved tickets counted
    expect($row['ytdOnTime'])->toBe('100%');  // 1/1 dated on time — the undated one is not "late"
    expect($row['display'][now()->month - 1])->toBe('2 · 100%');
});

it('shows a resolved count with no rate when no resolved ticket had a due date', function () {
    $year = (int) now()->year;
    $engineer = User::factory()->create(['role' => 'admin']);

    ItTicket::factory()->create([
        'assignee_id' => $engineer->id,
        'status' => 'done',
        'completed_at' => now()->startOfMonth()->addDays(1),
        'due_date' => null,
    ]);

    $report = app(CeoDashboardService::class)->itKpi($year);
    $row = collect($report['rows'])->firstWhere('key', $engineer->id);

    expect($row['ytdTotal'])->toBe('1');
    expect($row['ytdOnTime'])->toBe('—');
    expect($row['display'][now()->month - 1])->toBe('1');
});

it('still counts the resolved work of a soft-deleted assignee', function () {
    $year = (int) now()->year;
    $engineer = User::factory()->create(['role' => 'admin', 'name' => 'Former Engineer']);
    ItTicket::factory()->count(3)->create([
        'assignee_id' => $engineer->id,
        'status' => 'done',
        'completed_at' => now()->startOfMonth()->addDays(2),
        'due_date' => now()->startOfMonth()->addDays(5),
    ]);

    $engineer->delete(); // soft delete — tickets keep pointing at them

    $report = app(CeoDashboardService::class)->itKpi($year);
    $row = collect($report['rows'])->firstWhere('key', $engineer->id);

    // Hero total stays consistent with the visible rows.
    expect($report['summary']['heroValue'])->toBe('3');
    expect($row)->not->toBeNull();
    expect($row['label'])->toBe('Former Engineer');
    expect($row['ytdTotal'])->toBe('3');
});
