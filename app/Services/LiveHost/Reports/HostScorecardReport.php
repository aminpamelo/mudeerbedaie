<?php

namespace App\Services\LiveHost\Reports;

use App\Models\LiveHostPayrollItem;
use App\Models\LiveSession;
use App\Services\LiveHost\Reports\Filters\ReportFilters;

class HostScorecardReport
{
    public function run(ReportFilters $filters): HostScorecardResult
    {
        $sessionsQuery = LiveSession::query()
            ->whereBetween('scheduled_start_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
            ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('platform_account_id', $ids));

        $aggregates = (clone $sessionsQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'ended' THEN duration_minutes ELSE 0 END), 0) as total_minutes,
                COALESCE(SUM(CASE WHEN status = 'ended' THEN gmv_amount + COALESCE(gmv_adjustment, 0) ELSE 0 END), 0) as total_gmv,
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_sessions
            ")
            ->first();

        $totalHours = (float) $aggregates->total_minutes / 60.0;
        $totalGmv = (float) $aggregates->total_gmv;
        $totalSessions = (int) $aggregates->total_sessions;
        $endedSessions = (int) $aggregates->ended_sessions;
        $attendance = $totalSessions > 0 ? $endedSessions / $totalSessions : 0.0;

        $totalCommission = (float) LiveHostPayrollItem::query()
            ->whereHas('payrollRun', function ($q) use ($filters) {
                $q->where('period_start', '<=', $filters->dateTo->toDateString())
                    ->where('period_end', '>=', $filters->dateFrom->toDateString());
            })
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('user_id', $ids))
            ->sum('gross_total_myr');

        return new HostScorecardResult(
            kpis: [
                'totalHours' => round($totalHours, 2),
                'totalGmv' => round($totalGmv, 2),
                'totalCommission' => round($totalCommission, 2),
                'attendanceRate' => round($attendance, 4),
            ],
            rows: $this->rowsFor($filters),
            trend: $this->trendFor($filters),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(ReportFilters $filters): array
    {
        $rowsRaw = LiveSession::query()
            ->whereBetween('scheduled_start_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
            ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('platform_account_id', $ids))
            ->whereNotNull('live_host_id')
            ->join('users', 'users.id', '=', 'live_sessions.live_host_id')
            ->groupBy('live_sessions.live_host_id', 'users.name')
            ->selectRaw("
                live_sessions.live_host_id as host_id,
                users.name as host_name,
                COUNT(*) as sessions_scheduled,
                SUM(CASE WHEN live_sessions.status = 'ended' THEN 1 ELSE 0 END) as sessions_ended,
                SUM(CASE WHEN live_sessions.status = 'missed' THEN 1 ELSE 0 END) as no_shows,
                COALESCE(SUM(CASE WHEN live_sessions.status = 'ended' THEN live_sessions.duration_minutes ELSE 0 END), 0) as ended_minutes,
                COALESCE(SUM(CASE WHEN live_sessions.status = 'ended' THEN live_sessions.gmv_amount + COALESCE(live_sessions.gmv_adjustment, 0) ELSE 0 END), 0) as gmv
            ")
            ->orderByDesc('gmv')
            ->get();

        $lateStarts = $this->lateStartsByHost($filters);

        return $rowsRaw->map(function ($r) use ($lateStarts) {
            $hours = (float) $r->ended_minutes / 60.0;
            $hostId = (int) $r->host_id;
            $sessionsScheduled = (int) $r->sessions_scheduled;
            $sessionsEnded = (int) $r->sessions_ended;

            return [
                'hostId' => $hostId,
                'hostName' => $r->host_name,
                'sessionsScheduled' => $sessionsScheduled,
                'sessionsEnded' => $sessionsEnded,
                'hoursLive' => round($hours, 2),
                'gmv' => round((float) $r->gmv, 2),
                'avgGmvPerHour' => $hours > 0 ? round((float) $r->gmv / $hours, 2) : 0.0,
                'noShows' => (int) $r->no_shows,
                'lateStarts' => $lateStarts[$hostId] ?? 0,
                'attendanceRate' => $sessionsScheduled > 0
                    ? round($sessionsEnded / $sessionsScheduled, 4)
                    : 0.0,
            ];
        })->all();
    }

    /**
     * @return array<int, int>
     */
    private function lateStartsByHost(ReportFilters $filters): array
    {
        return LiveSession::query()
            ->whereBetween('scheduled_start_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->where('status', 'ended')
            ->whereNotNull('actual_start_at')
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
            ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('platform_account_id', $ids))
            ->get(['live_host_id', 'scheduled_start_at', 'actual_start_at'])
            ->groupBy('live_host_id')
            ->map(fn ($group) => $group->filter(
                fn ($s) => $s->scheduled_start_at->diffInMinutes($s->actual_start_at, false) > 5
            )->count())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trendFor(ReportFilters $filters): array
    {
        return LiveSession::query()
            ->whereBetween('scheduled_start_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->when($filters->hostIds, fn ($q, $ids) => $q->whereIn('live_host_id', $ids))
            ->when($filters->platformAccountIds, fn ($q, $ids) => $q->whereIn('platform_account_id', $ids))
            ->selectRaw("
                DATE(scheduled_start_at) as day,
                SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended,
                SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
            ")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->day,
                'ended' => (int) $r->ended,
                'missed' => (int) $r->missed,
            ])
            ->all();
    }
}
