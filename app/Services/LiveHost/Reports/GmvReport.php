<?php

namespace App\Services\LiveHost\Reports;

use App\Models\LiveSession;
use App\Services\LiveHost\Reports\Filters\ReportFilters;

class GmvReport
{
    public function run(ReportFilters $filters): GmvResult
    {
        $accountSeriesRaw = $this->accountAggregates($filters);
        $totalGmv = 0.0;
        $totalSessions = 0;
        $accountSeries = [];
        $topAccountId = null;
        $topAccountGmv = 0.0;

        foreach ($accountSeriesRaw as $row) {
            $gmv = (float) $row->gmv;
            $totalGmv += $gmv;
            $totalSessions += (int) $row->sessions;
            $accountSeries[] = [
                'accountId' => (int) $row->account_id,
                'name' => (string) $row->account_name,
                'totalGmv' => round($gmv, 2),
            ];
            if ($gmv > $topAccountGmv) {
                $topAccountGmv = $gmv;
                $topAccountId = (int) $row->account_id;
            }
        }

        $hostRowsRaw = $this->hostAggregates($filters);
        $hostRows = [];
        $topHostId = null;
        $topHostGmv = 0.0;

        foreach ($hostRowsRaw as $row) {
            $gmv = (float) $row->gmv;
            $sessions = (int) $row->sessions;
            $hostRows[] = [
                'hostId' => (int) $row->host_id,
                'hostName' => (string) $row->host_name,
                'sessions' => $sessions,
                'gmv' => round($gmv, 2),
                'avgGmvPerSession' => $sessions > 0 ? round($gmv / $sessions, 2) : 0.0,
            ];
            if ($gmv > $topHostGmv) {
                $topHostGmv = $gmv;
                $topHostId = (int) $row->host_id;
            }
        }

        $trendByAccount = $this->trendByAccount($filters);
        $topSessions = $this->topSessions($filters);

        return new GmvResult(
            kpis: [
                'totalGmv' => round($totalGmv, 2),
                'gmvPerSession' => $totalSessions > 0 ? round($totalGmv / $totalSessions, 2) : 0.0,
                'topAccountId' => $topAccountId,
                'topAccountGmv' => round($topAccountGmv, 2),
                'topHostId' => $topHostId,
                'topHostGmv' => round($topHostGmv, 2),
            ],
            trendByAccount: $trendByAccount,
            accountSeries: $accountSeries,
            hostRows: $hostRows,
            topSessions: $topSessions,
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function accountAggregates(ReportFilters $filters): \Illuminate\Support\Collection
    {
        return $this->baseQuery($filters)
            ->join('platform_accounts', 'platform_accounts.id', '=', 'live_sessions.platform_account_id')
            ->where('live_sessions.status', 'ended')
            ->groupBy('live_sessions.platform_account_id', 'platform_accounts.name')
            ->selectRaw('
                live_sessions.platform_account_id as account_id,
                platform_accounts.name as account_name,
                COUNT(*) as sessions,
                COALESCE(SUM(live_sessions.gmv_amount + COALESCE(live_sessions.gmv_adjustment, 0)), 0) as gmv
            ')
            ->orderByDesc('gmv')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function hostAggregates(ReportFilters $filters): \Illuminate\Support\Collection
    {
        return $this->baseQuery($filters)
            ->join('users', 'users.id', '=', 'live_sessions.live_host_id')
            ->where('live_sessions.status', 'ended')
            ->whereNotNull('live_sessions.live_host_id')
            ->groupBy('live_sessions.live_host_id', 'users.name')
            ->selectRaw('
                live_sessions.live_host_id as host_id,
                users.name as host_name,
                COUNT(*) as sessions,
                COALESCE(SUM(live_sessions.gmv_amount + COALESCE(live_sessions.gmv_adjustment, 0)), 0) as gmv
            ')
            ->orderByDesc('gmv')
            ->get();
    }

    /**
     * @return array<int, array{date: string, series: array<int, float>}>
     */
    private function trendByAccount(ReportFilters $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->where('live_sessions.status', 'ended')
            ->groupBy('day', 'live_sessions.platform_account_id')
            ->selectRaw('
                DATE(live_sessions.scheduled_start_at) as day,
                live_sessions.platform_account_id as account_id,
                COALESCE(SUM(live_sessions.gmv_amount + COALESCE(live_sessions.gmv_adjustment, 0)), 0) as gmv
            ')
            ->orderBy('day')
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $date = (string) $row->day;
            if (! isset($byDate[$date])) {
                $byDate[$date] = ['date' => $date, 'series' => []];
            }
            $byDate[$date]['series'][(int) $row->account_id] = round((float) $row->gmv, 2);
        }

        return array_values($byDate);
    }

    /**
     * @return array<int, array{sessionId: int, date: string, hostName: ?string, accountName: ?string, gmv: float}>
     */
    private function topSessions(ReportFilters $filters): array
    {
        return $this->baseQuery($filters)
            ->leftJoin('users', 'users.id', '=', 'live_sessions.live_host_id')
            ->leftJoin('platform_accounts', 'platform_accounts.id', '=', 'live_sessions.platform_account_id')
            ->where('live_sessions.status', 'ended')
            ->selectRaw('
                live_sessions.id as session_id,
                live_sessions.scheduled_start_at as scheduled_start_at,
                users.name as host_name,
                platform_accounts.name as account_name,
                (live_sessions.gmv_amount + COALESCE(live_sessions.gmv_adjustment, 0)) as gmv
            ')
            ->orderByDesc('gmv')
            ->orderByDesc('live_sessions.id')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'sessionId' => (int) $r->session_id,
                'date' => substr((string) $r->scheduled_start_at, 0, 10),
                'hostName' => $r->host_name !== null ? (string) $r->host_name : null,
                'accountName' => $r->account_name !== null ? (string) $r->account_name : null,
                'gmv' => round((float) $r->gmv, 2),
            ])
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<LiveSession>
     */
    private function baseQuery(ReportFilters $filters): \Illuminate\Database\Eloquent\Builder
    {
        return LiveSession::query()
            ->whereBetween('live_sessions.scheduled_start_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_sessions.live_host_id', $ids))
            ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('live_sessions.platform_account_id', $ids));
    }
}
