<?php

namespace App\Http\Controllers\LiveHost\Reports;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\CoverageReport;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoverageController extends Controller
{
    public function index(Request $request, CoverageReport $report): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);
        $prior = $report->run($filters->priorPeriod());

        return Inertia::render('reports/Coverage', [
            'kpis' => [
                'current' => $result->kpis,
                'prior' => $prior->kpis,
            ],
            'weeklyTrend' => $result->weeklyTrend,
            'accountRows' => $result->accountRows,
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

    public function export(Request $request, CoverageReport $report): StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);

        $filename = sprintf(
            'schedule-coverage_%s_%s.csv',
            $filters->dateFrom->toDateString(),
            $filters->dateTo->toDateString(),
        );

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Account', 'Total Slots', 'Filled', 'Unassigned',
                'Replaced', 'Missed', 'Coverage %',
            ]);
            foreach ($result->accountRows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['totalSlots'],
                    $row['assigned'] + $row['replaced'],
                    $row['unassigned'],
                    $row['replaced'],
                    $row['missed'],
                    round($row['coverageRate'] * 100, 1),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
