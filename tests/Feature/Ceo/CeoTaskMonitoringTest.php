<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\CeoTaskBoard;
use App\Services\Ceo\Reports\TaskMonitoringReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

describe('access control', function () {
    it('forbids non-executive roles', function () {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get('/ceo/tasks')->assertForbidden();
    });

    it('allows ceo and admins', function () {
        $this->actingAs(User::factory()->create(['role' => 'ceo']))->get('/ceo/tasks')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/ceo/tasks')->assertOk();
    });
});

describe('payload', function () {
    it('renders the TaskMonitoring component with the expected shape', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)
            ->get('/ceo/tasks')
            ->assertInertia(fn (Assert $page) => $page
                ->component('TaskMonitoring', false)
                ->where('period.key', 'today')
                ->where('period.label', 'Hari ini') // Malay default
                ->has('tasks.status')
                ->has('tasks.gauge')
                ->has('tasks.kpis', 7)
                ->has('tasks.breakdowns', 2)
                ->has('tasks.staff.rows')
                ->has('tasks.overdueList')
            );
    });
});

describe('task metrics', function () {
    it('counts open and overdue tasks and ranks laggards first', function () {
        $laggard = Employee::factory()->create(['full_name' => 'Laggard Staff']);
        $steady = Employee::factory()->create(['full_name' => 'Steady Staff']);

        // Laggard: 2 overdue + 1 upcoming open.
        Task::factory()->count(2)->create([
            'assigned_to' => $laggard->id,
            'status' => 'pending',
            'deadline' => now()->subDays(3)->toDateString(),
            'completed_at' => null,
        ]);
        Task::factory()->create([
            'assigned_to' => $laggard->id,
            'status' => 'in_progress',
            'deadline' => now()->addDays(5)->toDateString(),
            'completed_at' => null,
        ]);

        // Steady: 1 overdue + 1 completed on-time in the period.
        Task::factory()->create([
            'assigned_to' => $steady->id,
            'status' => 'pending',
            'deadline' => now()->subDay()->toDateString(),
            'completed_at' => null,
        ]);
        Task::factory()->create([
            'assigned_to' => $steady->id,
            'status' => 'completed',
            'deadline' => now()->toDateString(),
            'completed_at' => now()->subDays(2),
        ]);

        $report = app(TaskMonitoringReport::class)->build(CeoPeriod::fromKey('30d'));

        $open = collect($report['kpis'])->firstWhere('label', __('ceo.tasks.open'));
        $overdue = collect($report['kpis'])->firstWhere('label', __('ceo.tasks.overdue'));
        $completed = collect($report['kpis'])->firstWhere('label', __('ceo.tasks.completed'));

        expect($open['value'])->toBe('4');     // 3 laggard open + 1 steady open
        expect($overdue['value'])->toBe('3');   // 2 laggard + 1 steady
        expect($completed['value'])->toBe('1'); // steady completed in period

        // Laggard (more overdue) ranks first.
        expect($report['staff']['rows'][0]['name'])->toBe('Laggard Staff');
        expect($report['staff']['rows'][0]['overdue'])->toBe(2);

        // Status is red/amber given overdue tasks exist.
        expect($report['status'])->not->toBe('green');
    });

    it('reports an on-time completion rate for the period', function () {
        $emp = Employee::factory()->create();

        Task::factory()->create([
            'assigned_to' => $emp->id,
            'status' => 'completed',
            'deadline' => now()->addDay()->toDateString(),
            'completed_at' => now()->subDays(1), // before deadline -> on-time
        ]);
        Task::factory()->create([
            'assigned_to' => $emp->id,
            'status' => 'completed',
            'deadline' => now()->subDays(5)->toDateString(),
            'completed_at' => now()->subDays(2), // after deadline -> late
        ]);

        $report = app(TaskMonitoringReport::class)->build(CeoPeriod::fromKey('30d'));

        // 1 of 2 completed on time = 50%.
        expect($report['gauge']['value'])->toBe(50);
    });

    it('surfaces backlog staleness and a derived overdue share', function () {
        $emp = Employee::factory()->create();

        // Two overdue open tasks, 10 and 20 days late -> 15 days average.
        Task::factory()->create([
            'assigned_to' => $emp->id,
            'status' => 'pending',
            'deadline' => now()->subDays(10)->toDateString(),
            'completed_at' => null,
        ]);
        Task::factory()->create([
            'assigned_to' => $emp->id,
            'status' => 'in_progress',
            'deadline' => now()->subDays(20)->toDateString(),
            'completed_at' => null,
        ]);

        $kpis = collect(app(TaskMonitoringReport::class)->build(CeoPeriod::fromKey('30d'))['kpis']);

        expect($kpis)->toHaveCount(7);

        $avg = $kpis->firstWhere('label', __('ceo.tasks.avg_days_overdue'));
        expect($avg['value'])->toBe('15');
        expect($avg['tone'])->toBe('negative'); // >= 14 days late

        // Both open tasks are overdue -> "100% of open" context on the Overdue tile.
        $overdue = $kpis->firstWhere('label', __('ceo.tasks.overdue'));
        expect($overdue['hint'])->toBe(__('ceo.tasks.overdue_share', ['pct' => 100]));
    });

    it('shows a period-over-period delta on completed throughput', function () {
        $emp = Employee::factory()->create();

        // One task completed inside the current window, none in the prior window.
        Task::factory()->create([
            'assigned_to' => $emp->id,
            'status' => 'completed',
            'deadline' => now()->toDateString(),
            'completed_at' => now()->subDay(),
        ]);

        $completed = collect(app(TaskMonitoringReport::class)->build(CeoPeriod::fromKey('30d'))['kpis'])
            ->firstWhere('label', __('ceo.tasks.completed'));

        expect($completed['value'])->toBe('1');
        expect($completed['delta'])->toBe(['direction' => 'up', 'text' => '+1']);
    });
});

describe('task board grouping', function () {
    it('groups by category by default, returning every matching task unpaginated', function () {
        Task::factory()->count(20)->create([
            'status' => 'pending',
            'deadline' => now()->addDays(3)->toDateString(),
            'completed_at' => null,
        ]);

        // No group param -> grouped is the default view.
        $request = Request::create('/ceo/tasks', 'GET', ['status' => 'all']);
        $board = app(CeoTaskBoard::class)->build($request);

        expect($board['filters']['group'])->toBe('category');
        expect($board['meta']['last_page'])->toBe(1);
        expect($board['data'])->toHaveCount(20);
    });

    it('paginates the flat list when opted out with group=none', function () {
        Task::factory()->count(20)->create([
            'status' => 'pending',
            'deadline' => now()->addDays(3)->toDateString(),
            'completed_at' => null,
        ]);

        $request = Request::create('/ceo/tasks', 'GET', ['status' => 'all', 'group' => 'none']);
        $board = app(CeoTaskBoard::class)->build($request);

        expect($board['filters']['group'])->toBe('none');
        expect($board['meta']['last_page'])->toBeGreaterThan(1);
        expect($board['data'])->toHaveCount(12); // PER_PAGE
    });
});
