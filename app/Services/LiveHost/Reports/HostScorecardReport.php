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
            rows: [],
            trend: [],
        );
    }
}
