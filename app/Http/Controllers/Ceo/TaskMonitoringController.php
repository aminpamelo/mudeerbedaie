<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TaskCategory;
use App\Services\Ceo\CeoDashboardService;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\CeoTaskBoard;
use App\Services\Ceo\CeoTaskCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskMonitoringController extends Controller
{
    public function index(Request $request, CeoDashboardService $service, CeoTaskBoard $board, CeoTaskCalendar $calendar): Response
    {
        $period = CeoPeriod::fromRequest($request);

        // Props are closures so Inertia partial reloads stay cheap: filtering the
        // board reloads only `board`; editing a task reloads `board` + `tasks`;
        // navigating the calendar (month / basis) reloads only `calendar`.
        return Inertia::render('TaskMonitoring', [
            'period' => $period->toPayload(),
            'tasks' => fn () => $service->taskMonitoring($period),
            'board' => fn () => $board->build($request),
            'calendar' => fn () => $calendar->build($request),
            'employees' => fn () => Employee::query()
                ->whereNull('deleted_at')
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name])
                ->all(),
            'categories' => fn () => TaskCategory::query()
                ->active()
                ->ordered()
                ->get(['id', 'name', 'color'])
                ->all(),
        ]);
    }
}
