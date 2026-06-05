<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\Reports\TaskMonitoringReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->has('tasks.kpis', 6)
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
});
