<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Services\Ceo\CeoDashboardService;
use App\Services\Ceo\CeoPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function show(Request $request, string $department, CeoDashboardService $service): Response
    {
        $period = CeoPeriod::fromRequest($request);
        $detail = $service->departmentDetail($department, $period);

        abort_if($detail === null, 404);

        return Inertia::render('DepartmentDetail', [
            'period' => $period->toPayload(),
            'department' => $detail,
        ]);
    }
}
