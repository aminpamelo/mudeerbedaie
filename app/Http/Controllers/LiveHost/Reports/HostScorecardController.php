<?php

namespace App\Http\Controllers\LiveHost\Reports;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\HostScorecardReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HostScorecardController extends Controller
{
    public function index(Request $request, HostScorecardReport $report): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);
        $prior = $report->run($filters->priorPeriod());

        return Inertia::render('reports/HostScorecard', [
            'kpis' => [
                'current' => $result->kpis,
                'prior' => $prior->kpis,
            ],
            'rows' => $result->rows,
            'trend' => $result->trend,
            'filters' => [
                'dateFrom' => $filters->dateFrom->toDateString(),
                'dateTo' => $filters->dateTo->toDateString(),
                'hostIds' => $filters->hostIds,
                'platformAccountIds' => $filters->platformAccountIds,
            ],
            'filterOptions' => [
                'hosts' => User::query()
                    ->where('role', 'live_host')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                    ->all(),
                'platformAccounts' => PlatformAccount::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
                    ->all(),
            ],
        ]);
    }
}
