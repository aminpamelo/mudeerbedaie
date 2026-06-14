<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use App\Services\Ceo\CeoTaskCalendar;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    // Freeze "now" mid-month so the grid window and overdue maths are deterministic.
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
    Carbon::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function buildCalendar(array $query = []): array
{
    return app(CeoTaskCalendar::class)->build(Request::create('/ceo/tasks', 'GET', $query));
}

/**
 * @param  array<string, mixed>  $calendar
 * @return array<string, mixed>|null
 */
function cellFor(array $calendar, string $date): ?array
{
    return collect($calendar['weeks'])->flatten(1)->firstWhere('date', $date);
}

describe('payload', function () {
    it('ships a calendar prop with the month-grid shape', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)
            ->get('/ceo/tasks')
            ->assertInertia(fn (Assert $page) => $page
                ->component('TaskMonitoring', false)
                ->where('calendar.basis', 'deadline')
                ->has('calendar.month.label')
                ->has('calendar.month.prev')
                ->has('calendar.month.next')
                ->has('calendar.weekdays', 7)
                ->has('calendar.weeks')
                ->has('calendar.summary.stats', 4)
                ->has('calendar.legend.priority', 4)
            );
    });

    it('pads the month out to whole weeks', function () {
        $calendar = buildCalendar();

        // Every week row has 7 cells; the grid is 4–6 whole weeks.
        expect(collect($calendar['weeks'])->every(fn ($week) => count($week) === 7))->toBeTrue();
        expect(count($calendar['weeks']))->toBeGreaterThanOrEqual(4);
    });
});

describe('deadline basis', function () {
    it('plots a task on its deadline day with assignee + priority', function () {
        $emp = Employee::factory()->create(['full_name' => 'Aiman']);
        Task::factory()->create([
            'title' => 'Ship report',
            'assigned_to' => $emp->id,
            'status' => 'pending',
            'priority' => 'high',
            'deadline' => '2026-06-10',
            'completed_at' => null,
        ]);

        $cell = cellFor(buildCalendar(), '2026-06-10');

        expect($cell['total'])->toBe(1)
            ->and($cell['tasks'][0]['title'])->toBe('Ship report')
            ->and($cell['tasks'][0]['priority'])->toBe('high')
            ->and($cell['tasks'][0]['assignees'])->toBe(['Aiman']);
    });

    it('flags open tasks past their deadline as overdue alerts', function () {
        Task::factory()->create([
            'status' => 'pending',
            'deadline' => '2026-06-05', // before frozen today (06-15)
            'completed_at' => null,
        ]);

        $cell = cellFor(buildCalendar(), '2026-06-05');

        expect($cell['alert'])->toBe(1)
            ->and($cell['tasks'][0]['overdue'])->toBeTrue();
    });

    it('excludes cancelled tasks from the grid', function () {
        Task::factory()->create([
            'status' => 'cancelled',
            'deadline' => '2026-06-10',
            'completed_at' => null,
        ]);

        expect(cellFor(buildCalendar(), '2026-06-10')['total'])->toBe(0);
    });

    it('summarises due, completed and completion-rate for the month', function () {
        Task::factory()->create([
            'status' => 'completed',
            'deadline' => '2026-06-09',
            'completed_at' => '2026-06-09 10:00:00',
        ]);
        Task::factory()->create([
            'status' => 'pending',
            'deadline' => '2026-06-20',
            'completed_at' => null,
        ]);

        $stats = collect(buildCalendar()['summary']['stats']);

        expect($stats->firstWhere('label', __('ceo.ui.cal_due'))['value'])->toBe('2')
            ->and($stats->firstWhere('label', __('ceo.tasks.completed'))['value'])->toBe('1')
            ->and($stats->firstWhere('label', __('ceo.tasks.completion_rate'))['value'])->toBe('50%');
    });
});

describe('completed basis', function () {
    it('groups completed tasks by completion date and flags late ones', function () {
        // Completed after its deadline -> late.
        Task::factory()->create([
            'status' => 'completed',
            'deadline' => '2026-06-08',
            'completed_at' => '2026-06-12 10:00:00',
        ]);

        $calendar = buildCalendar(['basis' => 'completed']);
        $cell = cellFor($calendar, '2026-06-12');

        expect($calendar['basis'])->toBe('completed')
            ->and($cell['total'])->toBe(1)
            ->and($cell['alert'])->toBe(1)
            ->and($cell['tasks'][0]['late'])->toBeTrue();
    });

    it('does not plot still-open tasks in the completed view', function () {
        Task::factory()->create([
            'status' => 'pending',
            'deadline' => '2026-06-10',
            'completed_at' => null,
        ]);

        $calendar = buildCalendar(['basis' => 'completed']);

        expect(collect($calendar['weeks'])->flatten(1)->sum('total'))->toBe(0);
    });
});

describe('navigation', function () {
    it('steps to another month via the month param', function () {
        $calendar = buildCalendar(['month' => '2026-05']);

        expect($calendar['month']['key'])->toBe('2026-05')
            ->and($calendar['month']['isCurrent'])->toBeFalse()
            ->and($calendar['month']['next'])->toBe('2026-06');
    });

    it('marks the current month and ignores a malformed month param', function () {
        $calendar = buildCalendar(['month' => 'not-a-month']);

        expect($calendar['month']['key'])->toBe('2026-06')
            ->and($calendar['month']['isCurrent'])->toBeTrue();
    });
});
