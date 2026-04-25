<?php

namespace App\Http\Controllers\LiveHost\Reports;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\GmvReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GmvController extends Controller
{
    public function index(Request $request, GmvReport $report): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);
        $prior = $report->run($filters->priorPeriod());

        return Inertia::render('reports/Gmv', [
            'kpis' => [
                'current' => $result->kpis,
                'prior' => $prior->kpis,
            ],
            'trendByAccount' => $result->trendByAccount,
            'accountSeries' => $result->accountSeries,
            'hostRows' => $result->hostRows,
            'topSessions' => $result->topSessions,
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

    public function export(Request $request, GmvReport $report): StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);

        $filename = sprintf(
            'gmv-performance_%s_%s.csv',
            $filters->dateFrom->toDateString(),
            $filters->dateTo->toDateString(),
        );

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Host', 'Sessions', 'GMV (MYR)', 'Avg GMV/Session (MYR)']);
            foreach ($result->hostRows as $row) {
                fputcsv($out, [
                    $row['hostName'],
                    $row['sessions'],
                    $row['gmv'],
                    $row['avgGmvPerSession'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
