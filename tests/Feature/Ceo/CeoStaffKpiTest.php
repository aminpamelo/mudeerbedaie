<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use App\Services\Ceo\CeoDashboardService;
use App\Services\Ceo\Reports\StaffKpiReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

describe('access control', function () {
    it('forbids non-executive roles', function () {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get('/ceo/kpi')->assertForbidden();
    });

    it('allows ceo and admins', function () {
        $this->actingAs(User::factory()->create(['role' => 'ceo']))->get('/ceo/kpi')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/ceo/kpi')->assertOk();
    });
});

describe('payload', function () {
    it('renders the StaffKpi component with Jan–Dec and a row per active employee', function () {
        Employee::factory()->count(3)->create();

        $this->actingAs(User::factory()->create(['role' => 'ceo']))
            ->get('/ceo/kpi?year=2026')
            ->assertInertia(fn (Assert $page) => $page
                ->component('StaffKpi', false)
                ->where('report.year', 2026)
                ->has('report.months', 12)
                ->has('report.rows', 3)
                ->has('report.summary.trend', 12)
                ->has('report.columns')
            );
    });
});

describe('staff kpi metrics', function () {
    it('builds a per-staff monthly completed + on-time matrix', function () {
        $emp = Employee::factory()->create(['full_name' => 'Anita KPI']);

        // January: 2 completed (1 on-time, 1 late). March: 1 completed (on-time).
        Task::factory()->create(['assigned_to' => $emp->id, 'status' => 'completed', 'completed_at' => '2026-01-10 09:00:00', 'deadline' => '2026-01-15']);
        Task::factory()->create(['assigned_to' => $emp->id, 'status' => 'completed', 'completed_at' => '2026-01-20 09:00:00', 'deadline' => '2026-01-15']);
        Task::factory()->create(['assigned_to' => $emp->id, 'status' => 'completed', 'completed_at' => '2026-03-05 09:00:00', 'deadline' => '2026-03-10']);

        $report = app(StaffKpiReport::class)->build(2026);
        $row = collect($report['rows'])->firstWhere('label', 'Anita KPI');

        expect($row['display'][0])->toBe('2 · 50%');   // Jan: 2 done, 1 on-time
        expect($row['display'][1])->toBe('');           // Feb: nothing
        expect($row['display'][2])->toBe('1 · 100%');   // Mar: 1 done, on-time
        expect($row['ytdTotal'])->toBe('3');
        expect($row['ytdOnTime'])->toBe('67%');         // 2 of 3 on-time
        expect($row['bestIndex'])->toBe(0);             // Jan most completed
        expect($row['worstIndex'])->toBe(2);            // Mar least completed
        expect(count($row['tasks']))->toBeGreaterThanOrEqual(3);
    });

    it('includes a no-task employee as an empty row', function () {
        Employee::factory()->create(['full_name' => 'Idle Staff']);

        $report = app(StaffKpiReport::class)->build(2026);
        $row = collect($report['rows'])->firstWhere('label', 'Idle Staff');

        expect($row['ytdTotal'])->toBe('0');
        expect($row['bestIndex'])->toBeNull();
        expect($row['tasks'])->toBe([]);
    });

    it('reflects a task change immediately by busting the cached report', function () {
        $emp = Employee::factory()->create();
        $service = app(CeoDashboardService::class);

        // Warm the cache: no completions yet.
        expect($service->staffKpi(2026)['summary']['heroValue'])->toBe('0');

        // A completed task created from any path fires the model hook that busts
        // the CEO cache, so the next read rebuilds instead of serving stale data.
        Task::factory()->create(['assigned_to' => $emp->id, 'status' => 'completed', 'completed_at' => '2026-02-10 09:00:00', 'deadline' => '2026-02-15']);

        expect($service->staffKpi(2026)['summary']['heroValue'])->toBe('1');
    });
});
