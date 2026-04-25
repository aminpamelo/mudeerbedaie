<?php

namespace App\Services\LiveHost\Reports;

use App\Models\SessionReplacementRequest;
use App\Services\LiveHost\Reports\Filters\ReportFilters;
use Carbon\CarbonImmutable;

/**
 * Replacement Activity report — surfaces who's asking for replacements,
 * who's covering, and how fast we're filling them.
 *
 * Filter semantics:
 *   - Window scopes by `requested_at` (NOT `target_date`); the owner cares
 *     about request activity during the period, not the dates being covered.
 *   - `hostIds` filter applies to `original_host_id` for the global aggregates
 *     (KPIs, trend) AND the topRequesters list. The topCoverers list filters
 *     on `replacement_host_id` instead, since that's the perspective being
 *     reported on.
 *   - `platformAccountIds` is intentionally ignored: request rows do not
 *     carry a platform account directly (only the assignment they reference
 *     does), and joining `live_schedule_assignments` purely for filtering
 *     would add a query for marginal gain.
 *
 * Query budget: 5 (KPI counts, avg-time fetch, daily trend, top requesters,
 * top coverers).
 */
class ReplacementsReport
{
    public function run(ReportFilters $filters): ReplacementsResult
    {
        $kpis = $this->kpis($filters);
        $trend = $this->trend($filters);
        $topRequesters = $this->topRequesters($filters);
        $topCoverers = $this->topCoverers($filters);

        return new ReplacementsResult(
            kpis: $kpis,
            trend: $trend,
            topRequesters: $topRequesters,
            topCoverers: $topCoverers,
        );
    }

    /**
     * @return array{total: int, fulfilled: int, expired: int, avgTimeToAssignMinutes: ?float}
     */
    private function kpis(ReportFilters $filters): array
    {
        $counts = $this->baseQuery($filters)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as fulfilled,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as expired
            ', [SessionReplacementRequest::STATUS_ASSIGNED, SessionReplacementRequest::STATUS_EXPIRED])
            ->first();

        $assignedRows = $this->baseQuery($filters)
            ->where('status', SessionReplacementRequest::STATUS_ASSIGNED)
            ->whereNotNull('assigned_at')
            ->get(['requested_at', 'assigned_at']);

        $avgMinutes = null;
        if ($assignedRows->isNotEmpty()) {
            $sum = 0.0;
            foreach ($assignedRows as $row) {
                $requested = CarbonImmutable::parse($row->requested_at);
                $assigned = CarbonImmutable::parse($row->assigned_at);
                $sum += ($assigned->getTimestamp() - $requested->getTimestamp()) / 60.0;
            }
            $avgMinutes = round($sum / $assignedRows->count(), 2);
        }

        return [
            'total' => (int) ($counts->total ?? 0),
            'fulfilled' => (int) ($counts->fulfilled ?? 0),
            'expired' => (int) ($counts->expired ?? 0),
            'avgTimeToAssignMinutes' => $avgMinutes,
        ];
    }

    /**
     * @return array<int, array{date: string, requested: int, fulfilled: int}>
     */
    private function trend(ReportFilters $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->selectRaw('
                DATE(requested_at) as day,
                COUNT(*) as requested,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as fulfilled
            ', [SessionReplacementRequest::STATUS_ASSIGNED])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($r) => [
            'date' => (string) $r->day,
            'requested' => (int) $r->requested,
            'fulfilled' => (int) $r->fulfilled,
        ])->all();
    }

    /**
     * @return array<int, array{hostId: int, hostName: string, requestCount: int, reasons: array<string, int>}>
     */
    private function topRequesters(ReportFilters $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->join('users', 'users.id', '=', 'session_replacement_requests.original_host_id')
            ->groupBy('session_replacement_requests.original_host_id', 'users.name')
            ->selectRaw('
                session_replacement_requests.original_host_id as host_id,
                users.name as host_name,
                COUNT(*) as request_count,
                SUM(CASE WHEN reason_category = ? THEN 1 ELSE 0 END) as r_sick,
                SUM(CASE WHEN reason_category = ? THEN 1 ELSE 0 END) as r_family,
                SUM(CASE WHEN reason_category = ? THEN 1 ELSE 0 END) as r_personal,
                SUM(CASE WHEN reason_category = ? THEN 1 ELSE 0 END) as r_other
            ', ['sick', 'family', 'personal', 'other'])
            ->orderByDesc('request_count')
            ->orderBy('host_id')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'hostId' => (int) $r->host_id,
            'hostName' => (string) $r->host_name,
            'requestCount' => (int) $r->request_count,
            'reasons' => [
                'sick' => (int) $r->r_sick,
                'family' => (int) $r->r_family,
                'personal' => (int) $r->r_personal,
                'other' => (int) $r->r_other,
            ],
        ])->all();
    }

    /**
     * @return array<int, array{hostId: int, hostName: string, coverCount: int}>
     */
    private function topCoverers(ReportFilters $filters): array
    {
        $rows = SessionReplacementRequest::query()
            ->whereBetween('session_replacement_requests.requested_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->where('session_replacement_requests.status', SessionReplacementRequest::STATUS_ASSIGNED)
            ->whereNotNull('session_replacement_requests.replacement_host_id')
            ->join('users', 'users.id', '=', 'session_replacement_requests.replacement_host_id')
            ->groupBy('session_replacement_requests.replacement_host_id', 'users.name')
            ->selectRaw('
                session_replacement_requests.replacement_host_id as host_id,
                users.name as host_name,
                COUNT(*) as cover_count
            ')
            ->orderByDesc('cover_count')
            ->orderBy('host_id')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'hostId' => (int) $r->host_id,
            'hostName' => (string) $r->host_name,
            'coverCount' => (int) $r->cover_count,
        ])->all();
    }

    /**
     * Base query for global aggregates (KPIs, trend, topRequesters). Window
     * filter on `requested_at`; host filter on `original_host_id`.
     *
     * @return \Illuminate\Database\Eloquent\Builder<SessionReplacementRequest>
     */
    private function baseQuery(ReportFilters $filters): \Illuminate\Database\Eloquent\Builder
    {
        return SessionReplacementRequest::query()
            ->whereBetween('session_replacement_requests.requested_at', [
                $filters->dateFrom->startOfDay(),
                $filters->dateTo->endOfDay(),
            ])
            ->when(
                $filters->hostIds,
                fn ($q, $ids) => $q->whereIn('session_replacement_requests.original_host_id', $ids)
            );
    }
}
