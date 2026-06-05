<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Services\Ceo\CeoDashboardService;
use App\Services\Ceo\CeoPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskMonitoringController extends Controller
{
    public function index(Request $request, CeoDashboardService $service): Response
    {
        $period = CeoPeriod::fromRequest($request);

        return Inertia::render('TaskMonitoring', [
            'period' => $period->toPayload(),
            'tasks' => $service->taskMonitoring($period),
        ]);
    }
}
