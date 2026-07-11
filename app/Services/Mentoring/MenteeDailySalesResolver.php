<?php

namespace App\Services\Mentoring;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyComment;
use App\Models\LiveHostMenteeDailyMetric;
use App\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves a mentee's daily and monthly sales for the mentoring performance
 * grid. Daily sales are auto-derived from the host's live-session GMV
 * (effective GMV = gmv_amount + gmv_adjustment on ended sessions), and the PIC
 * may override any single day via live_host_mentee_daily_metrics.sales_override.
 *
 * Effective daily sales = override ?? auto. Effective monthly sales = the SUM
 * of effective daily sales across the month — this replaces the old hand-keyed
 * monthly sales_quantity as the source of the Sales KPI.
 */
class MenteeDailySalesResolver
{
    /**
     * Effective monthly sales totals for a set of mentees over the given months.
     *
     * @param  Collection<int, LiveHostMentee>  $mentees  each must expose id + mentee_user_id
     * @param  array<int, array{year: int, month: int}>  $periods
     * @return array<int, array<string, float|null>> [menteeId][ 'YYYY-MM' ] => effective total (null when no data)
     */
    public function monthlyTotals(Collection $mentees, array $periods): array
    {
        if ($mentees->isEmpty() || $periods === []) {
            return [];
        }

        [$from, $to] = $this->rangeFor($periods);

        $hostIds = $mentees->pluck('mentee_user_id')->filter()->unique()->values()->all();
        $auto = $this->autoDailyGmv($hostIds, $from, $to);          // [hostId][Y-m-d] => gmv
        $overrides = $this->overrideDailyMap($mentees->pluck('id')->all(), $from, $to); // [menteeId][Y-m-d] => override

        $totals = [];
        foreach ($mentees as $mentee) {
            $hostId = $mentee->mentee_user_id;
            foreach ($periods as $p) {
                $key = sprintf('%04d-%02d', $p['year'], $p['month']);
                $totals[$mentee->id][$key] = $this->effectiveMonthTotal(
                    $auto[$hostId] ?? [],
                    $overrides[$mentee->id] ?? [],
                    $p['year'],
                    $p['month'],
                );
            }
        }

        return $totals;
    }

    /**
     * Ended live-session counts per mentee per month — the "how many lives did
     * they run" actual behind the monthly Live KPI. A live is one ended session
     * (a host may run several a day), so this counts sessions, not days. Months
     * with no ended sessions return 0 (a countable KPI wants a number, not null).
     *
     * @param  Collection<int, LiveHostMentee>  $mentees  each must expose id + mentee_user_id
     * @param  array<int, array{year: int, month: int}>  $periods
     * @return array<int, array<string, int>> [menteeId][ 'YYYY-MM' ] => ended-session count
     */
    public function monthlyLiveCounts(Collection $mentees, array $periods): array
    {
        if ($mentees->isEmpty() || $periods === []) {
            return [];
        }

        [$from, $to] = $this->rangeFor($periods);

        $hostIds = $mentees->pluck('mentee_user_id')->filter()->unique()->values()->all();
        $auto = $this->autoDailyGmv($hostIds, $from, $to); // [hostId][Y-m-d] => ['gmv', 'sessions']

        $counts = [];
        foreach ($mentees as $mentee) {
            $autoForHost = $auto[$mentee->mentee_user_id] ?? [];
            foreach ($periods as $p) {
                $prefix = sprintf('%04d-%02d-', $p['year'], $p['month']);
                $key = sprintf('%04d-%02d', $p['year'], $p['month']);
                $total = 0;
                foreach ($autoForHost as $dateKey => $day) {
                    if (str_starts_with($dateKey, $prefix)) {
                        $total += (int) ($day['sessions'] ?? 0);
                    }
                }
                $counts[$mentee->id][$key] = $total;
            }
        }

        return $counts;
    }

    /**
     * Effective total sales per mentee over an arbitrary date range. Powers the
     * cohort leaderboard, where "this month" and "all time" are just two ranges.
     * A mentee with no ended sessions and no overrides in the range totals 0.0
     * (unlike {@see monthlyTotals}, which returns null for blank months so the
     * grid can render an empty cell — a leaderboard wants a comparable number).
     *
     * @param  Collection<int, LiveHostMentee>  $mentees  each must expose id + mentee_user_id
     * @return array<int, float> [menteeId] => effective total
     */
    public function rangeTotals(Collection $mentees, Carbon|CarbonImmutable $from, Carbon|CarbonImmutable $to): array
    {
        if ($mentees->isEmpty()) {
            return [];
        }

        $hostIds = $mentees->pluck('mentee_user_id')->filter()->unique()->values()->all();
        $auto = $this->autoDailyGmv($hostIds, $from, $to);
        $overrides = $this->overrideDailyMap($mentees->pluck('id')->all(), $from, $to);

        $totals = [];
        foreach ($mentees as $mentee) {
            $autoForHost = $auto[$mentee->mentee_user_id] ?? [];
            $overridesForMentee = $overrides[$mentee->id] ?? [];

            $dateKeys = array_unique(array_merge(
                array_keys($autoForHost),
                array_keys($overridesForMentee),
            ));

            $total = 0.0;
            foreach ($dateKeys as $dateKey) {
                $autoGmv = (float) ($autoForHost[$dateKey]['gmv'] ?? 0);
                $override = $overridesForMentee[$dateKey] ?? null;
                $total += $override ?? $autoGmv;
            }

            $totals[$mentee->id] = round($total, 2);
        }

        return $totals;
    }

    /**
     * Full day-by-day breakdown for one mentee in one month, for the daily strip.
     * Comments are grouped by author in their own table; here we only surface the
     * per-day count and presence (the strip renders a single "commented" dot).
     *
     * @return array<int, array{date: string, day: int, auto: float, override: float|null, effective: float, sessions: int, comment_count: int, has_comment: bool}>
     */
    public function dailyBreakdown(LiveHostMentee $mentee, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $auto = $this->autoDailyGmv([$mentee->mentee_user_id], $start, $end)[$mentee->mentee_user_id] ?? [];

        $rows = LiveHostMenteeDailyMetric::query()
            ->where('mentee_id', $mentee->id)
            ->whereBetween('metric_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (LiveHostMenteeDailyMetric $m) => $m->metric_date->toDateString());

        $commentCounts = LiveHostMenteeDailyComment::query()
            ->where('mentee_id', $mentee->id)
            ->whereBetween('metric_date', [$start->toDateString(), $end->toDateString()])
            ->get(['metric_date'])
            ->countBy(fn (LiveHostMenteeDailyComment $c) => $c->metric_date->toDateString());

        $days = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $dateKey = $d->toDateString();
            $autoGmv = (float) ($auto[$dateKey]['gmv'] ?? 0);
            $sessions = (int) ($auto[$dateKey]['sessions'] ?? 0);
            $row = $rows->get($dateKey);
            $override = $row && $row->sales_override !== null ? (float) $row->sales_override : null;
            $commentCount = (int) ($commentCounts[$dateKey] ?? 0);

            $days[] = [
                'date' => $dateKey,
                'day' => (int) $d->format('j'),
                'auto' => round($autoGmv, 2),
                'override' => $override,
                'effective' => round($override ?? $autoGmv, 2),
                'sessions' => $sessions,
                'comment_count' => $commentCount,
                'has_comment' => $commentCount > 0,
            ];
        }

        return $days;
    }

    /**
     * Auto GMV + ended-session count per host per day over a date range.
     *
     * @param  array<int, int>  $hostUserIds
     * @return array<int, array<string, array{gmv: float, sessions: int}>> [hostId][Y-m-d]
     */
    public function autoDailyGmv(array $hostUserIds, Carbon|CarbonImmutable $from, Carbon|CarbonImmutable $to): array
    {
        if ($hostUserIds === []) {
            return [];
        }

        $rows = LiveSession::query()
            ->whereIn('live_host_id', $hostUserIds)
            ->whereBetween('scheduled_start_at', [
                CarbonImmutable::parse($from)->startOfDay(),
                CarbonImmutable::parse($to)->endOfDay(),
            ])
            ->where('status', 'ended')
            ->selectRaw('
                live_host_id as host_id,
                DATE(scheduled_start_at) as day,
                COALESCE(SUM(gmv_amount + COALESCE(gmv_adjustment, 0)), 0) as gmv,
                COUNT(*) as sessions
            ')
            ->groupBy('host_id', 'day')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->host_id][(string) $r->day] = [
                'gmv' => (float) $r->gmv,
                'sessions' => (int) $r->sessions,
            ];
        }

        return $map;
    }

    /**
     * PIC sales overrides per mentee per day over a date range.
     *
     * @param  array<int, int>  $menteeIds
     * @return array<int, array<string, float>> [menteeId][Y-m-d] => override
     */
    private function overrideDailyMap(array $menteeIds, Carbon|CarbonImmutable $from, Carbon|CarbonImmutable $to): array
    {
        if ($menteeIds === []) {
            return [];
        }

        return LiveHostMenteeDailyMetric::query()
            ->whereIn('mentee_id', $menteeIds)
            ->whereNotNull('sales_override')
            ->whereBetween('metric_date', [
                CarbonImmutable::parse($from)->toDateString(),
                CarbonImmutable::parse($to)->toDateString(),
            ])
            ->get(['mentee_id', 'metric_date', 'sales_override'])
            ->reduce(function (array $carry, LiveHostMenteeDailyMetric $m) {
                $carry[$m->mentee_id][$m->metric_date->toDateString()] = (float) $m->sales_override;

                return $carry;
            }, []);
    }

    /**
     * @param  array<string, array{gmv: float, sessions: int}>  $autoForHost
     * @param  array<string, float>  $overridesForMentee
     */
    private function effectiveMonthTotal(array $autoForHost, array $overridesForMentee, int $year, int $month): ?float
    {
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $total = 0.0;
        $contributed = false;

        // Every day with an ended live session or a PIC override contributes; a
        // month with neither returns null so the grid renders a blank cell (not RM 0).
        $dateKeys = array_unique(array_merge(
            array_keys($autoForHost),
            array_keys($overridesForMentee),
        ));

        foreach ($dateKeys as $dateKey) {
            if (! str_starts_with($dateKey, $prefix)) {
                continue;
            }
            $contributed = true;
            $auto = (float) ($autoForHost[$dateKey]['gmv'] ?? 0);
            $override = $overridesForMentee[$dateKey] ?? null;
            $total += $override ?? $auto;
        }

        return $contributed ? round($total, 2) : null;
    }

    /**
     * The min→max calendar span covering all requested periods.
     *
     * @param  array<int, array{year: int, month: int}>  $periods
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rangeFor(array $periods): array
    {
        $starts = array_map(
            fn ($p) => CarbonImmutable::create($p['year'], $p['month'], 1)->startOfMonth(),
            $periods,
        );

        $min = collect($starts)->min();
        $max = collect($starts)->max()->endOfMonth();

        return [$min, $max];
    }
}
