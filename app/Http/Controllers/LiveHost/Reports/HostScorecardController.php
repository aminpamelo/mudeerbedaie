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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(Request $request, HostScorecardReport $report): StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);

        $filename = sprintf(
            'host-scorecard_%s_%s.csv',
            $filters->dateFrom->toDateString(),
            $filters->dateTo->toDateString(),
        );

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Host', 'Sessions Scheduled', 'Sessions Ended', 'Hours Live',
                'GMV (MYR)', 'Avg GMV/Hr (MYR)', 'No-Shows', 'Late Starts', 'Attendance %',
            ]);
            foreach ($result->rows as $row) {
                fputcsv($out, [
                    $row['hostName'],
                    $row['sessionsScheduled'],
                    $row['sessionsEnded'],
                    $row['hoursLive'],
                    $row['gmv'],
                    $row['avgGmvPerHour'],
                    $row['noShows'],
                    $row['lateStarts'],
                    round($row['attendanceRate'] * 100, 1),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
