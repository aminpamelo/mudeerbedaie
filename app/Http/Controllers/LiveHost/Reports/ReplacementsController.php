<?php

namespace App\Http\Controllers\LiveHost\Reports;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use App\Services\LiveHost\Reports\ReplacementsReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReplacementsController extends Controller
{
    public function index(Request $request, ReplacementsReport $report): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);
        $prior = $report->run($filters->priorPeriod());

        return Inertia::render('reports/Replacements', [
            'kpis' => [
                'current' => $result->kpis,
                'prior' => $prior->kpis,
            ],
            'trend' => $result->trend,
            'topRequesters' => $result->topRequesters,
            'topCoverers' => $result->topCoverers,
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

    public function export(Request $request, ReplacementsReport $report): StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->run($filters);

        $filename = sprintf(
            'replacement-activity_%s_%s.csv',
            $filters->dateFrom->toDateString(),
            $filters->dateTo->toDateString(),
        );

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Type', 'Host', 'Count', 'Reasons']);

            foreach ($result->topRequesters as $row) {
                $reasonText = collect($row['reasons'])
                    ->filter(fn ($n) => $n > 0)
                    ->map(fn ($n, $reason) => "$reason: $n")
                    ->values()
                    ->implode('; ');

                fputcsv($out, [
                    'Requester',
                    $row['hostName'],
                    $row['requestCount'],
                    $reasonText,
                ]);
            }

            foreach ($result->topCoverers as $row) {
                fputcsv($out, [
                    'Coverer',
                    $row['hostName'],
                    $row['coverCount'],
                    '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
