<?php

namespace App\Services\Mentoring;

use App\Models\LiveSession;
use Carbon\CarbonImmutable;

/**
 * Per-mentee performance snapshot. Reuses the exact metric definitions trusted
 * by HostScorecardReport (ended-session hours, net GMV, attendance) but scoped
 * to a single live-host user over a rolling window — the signals that feed both
 * the mentee KPI tab and the auto level suggestion.
 */
class MenteeKpiReport
{
    /**
     * @return array{
     *     sessions: int, ended: int, noShows: int, hours: float,
     *     gmv: float, attendancePct: int, avgGmvPerHour: float,
     *     from: string, to: string
     * }
     */
    public function forUser(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $agg = LiveSession::query()
            ->where('live_host_id', $userId)
            ->whereBetween('scheduled_start_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw("
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_sessions,
                SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as no_shows,
                COALESCE(SUM(CASE WHEN status = 'ended' THEN duration_minutes ELSE 0 END), 0) as ended_minutes,
                COALESCE(SUM(CASE WHEN status = 'ended' THEN gmv_amount + COALESCE(gmv_adjustment, 0) ELSE 0 END), 0) as gmv
            ")
            ->first();

        $total = (int) $agg->total_sessions;
        $ended = (int) $agg->ended_sessions;
        $hours = (float) $agg->ended_minutes / 60.0;
        $gmv = (float) $agg->gmv;
        $attendancePct = $total > 0 ? (int) round(($ended / $total) * 100) : 0;

        return [
            'sessions' => $total,
            'ended' => $ended,
            'noShows' => (int) $agg->no_shows,
            'hours' => round($hours, 1),
            'gmv' => round($gmv, 2),
            'attendancePct' => $attendancePct,
            'avgGmvPerHour' => $hours > 0 ? round($gmv / $hours, 2) : 0.0,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }
}
