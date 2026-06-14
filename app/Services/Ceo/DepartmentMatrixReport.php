<?php

namespace App\Services\Ceo;

use App\Models\AttendanceLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\ProductOrder;
use App\Models\SessionReplacementRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the "metric × time" matrix that headlines each CEO department detail
 * page: a row per additive flow metric (counts / amounts) spread across the
 * period's buckets, with a period total, per-bucket average, a trend sparkline
 * and a vs-previous-period change — mirroring the yearly MonthlyReport matrix
 * but scoped to the dashboard period (daily buckets for short windows, weekly
 * for longer ones).
 *
 * Only additive metrics live here; rates/ratios stay as point-in-time KPI tiles.
 * Each department supplies a per-day aggregate pass (reusing the same query
 * shapes as its health report) plus its metric definitions.
 */
class DepartmentMatrixReport
{
    private const DAILY_MAX_DAYS = 14;

    private const LIVE_ENDED = ['ended', 'completed'];

    /**
     * @return array<string, mixed>|null
     */
    public function build(string $department, CeoPeriod $period): ?array
    {
        $spec = match ($department) {
            'livehost' => $this->liveHost($period),
            'education' => $this->education($period),
            'ecommerce' => $this->ecommerce($period),
            'hr' => $this->hr($period),
            'sales' => $this->sales($period),
            default => null,
        };

        if ($spec === null) {
            return null;
        }

        return $this->compose($period, $spec['perDay'], $spec['prior'], $spec['metrics']);
    }

    /**
     * Generic matrix assembly shared by every department.
     *
     * @param  array<string, array<string, int|float>>  $perDay  raw aggregate keyed by Y-m-d
     * @param  array<string, int|float>  $prior  summed raw aggregate for the prior period
     * @param  array<int, array<string, mixed>>  $metrics  metric definitions
     * @return array<string, mixed>
     */
    private function compose(CeoPeriod $period, array $perDay, array $prior, array $metrics): array
    {
        $buckets = $this->buckets($period);
        $todayDate = CarbonImmutable::now()->toDateString();

        $bucketDicts = [];
        $active = [];
        foreach ($buckets as $bucket) {
            $bucketDicts[] = $this->sumDays($perDay, $bucket['days']);
            $active[] = $bucket['days'][0] <= $todayDate;
        }

        $allDays = array_merge(...array_column($buckets, 'days'));
        $periodTotal = $this->sumDays($perDay, $allDays);

        // Average is per elapsed day (not per bucket), so it stays comparable
        // whether the period renders as daily or weekly columns.
        $activeDays = count(array_filter($allDays, fn (string $d) => $d <= $todayDate));

        $rows = array_map(
            fn (array $metric) => $this->buildRow($metric, $bucketDicts, $active, $periodTotal, $prior, $activeDays, $perDay, $allDays, $todayDate),
            $metrics
        );

        return [
            'title' => __('ceo.matrix.title'),
            'subtitle' => __('ceo.matrix.subtitle'),
            'months' => array_column($buckets, 'label'),
            'columns' => [
                'metric' => __('ceo.matrix.col_metric'),
                'ytdTotal' => __('ceo.matrix.col_total'),
                'ytdAvg' => __('ceo.matrix.col_avg'),
                'trend' => __('ceo.matrix.col_trend'),
                'mom' => __('ceo.matrix.col_change'),
            ],
            'rows' => $rows,
            'empty' => $perDay === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $metric
     * @param  array<int, array<string, int|float>>  $bucketDicts
     * @param  array<int, bool>  $active
     * @param  array<string, int|float>  $periodTotal
     * @param  array<string, int|float>  $prior
     * @param  int  $activeDays  number of elapsed days in the period (avg denominator)
     * @param  array<string, array<string, int|float>>  $perDay  raw aggregate keyed by Y-m-d
     * @param  array<int, string>  $allDays  every Y-m-d in the period, in order
     * @return array<string, mixed>
     */
    private function buildRow(array $metric, array $bucketDicts, array $active, array $periodTotal, array $prior, int $activeDays, array $perDay, array $allDays, string $todayDate): array
    {
        $valueFn = $metric['value'];

        $values = [];
        $display = [];
        $trend = [];
        foreach ($bucketDicts as $i => $dict) {
            $v = $active[$i] ? $valueFn($dict) : null;
            $values[] = $v;
            $display[] = $active[$i] ? $this->format($metric['type'], $v) : '';
            $trend[] = is_numeric($v) ? (float) $v : 0;
        }

        $total = (float) $valueFn($periodTotal);
        $avg = $activeDays > 0 ? $total / $activeDays : null;

        return [
            'key' => $metric['key'],
            'label' => $metric['label'],
            'polarity' => $metric['polarity'],
            'display' => $display,
            'trend' => $trend,
            'daily' => $this->dailySeries($valueFn, $metric['type'], $perDay, $allDays, $todayDate),
            'ytdTotal' => $this->format($metric['type'], $total),
            'ytdAvg' => $avg === null ? __('ceo.matrix.na') : $this->format($metric['type'], $avg),
            'mom' => $this->change($total, (float) $valueFn($prior), $metric['polarity']),
            'bestIndex' => $this->extremeIndex($values, true, $metric['polarity']),
            'worstIndex' => $this->extremeIndex($values, false, $metric['polarity']),
        ];
    }

    /**
     * Per-day breakdown for one metric across the elapsed days of the period —
     * powers the inline row-expansion drill-down (always daily, regardless of
     * whether the columns rendered daily or weekly).
     *
     * @param  callable(array<string, int|float>): (int|float)  $valueFn
     * @param  array<string, array<string, int|float>>  $perDay
     * @param  array<int, string>  $allDays
     * @return array<int, array{label: string, value: float, display: string}>
     */
    private function dailySeries(callable $valueFn, string $type, array $perDay, array $allDays, string $todayDate): array
    {
        $series = [];
        foreach ($allDays as $day) {
            if ($day > $todayDate) {
                continue;
            }
            $v = $valueFn($perDay[$day] ?? []);
            $series[] = [
                'label' => CarbonImmutable::parse($day)->format('j/n'),
                'value' => is_numeric($v) ? (float) $v : 0.0,
                'display' => $this->format($type, $v),
            ];
        }

        return $series;
    }

    /**
     * Period-over-period change vs the equal-length prior window.
     *
     * @return array{text: string, tone: string}|null
     */
    private function change(float $current, float $prior, string $polarity): ?array
    {
        if ($prior == 0.0) {
            return null;
        }

        $pct = ($current - $prior) / abs($prior) * 100;
        $improved = $polarity === 'down' ? $pct < 0 : $pct > 0;
        $tone = abs($pct) < 0.5 ? 'muted' : ($improved ? 'positive' : 'negative');

        return ['text' => sprintf('%+d%%', (int) round($pct)), 'tone' => $tone];
    }

    /**
     * @param  array<int, int|float|null>  $values
     */
    private function extremeIndex(array $values, bool $best, string $polarity): ?int
    {
        $candidates = [];
        foreach ($values as $i => $v) {
            if (is_numeric($v)) {
                $candidates[$i] = $v;
            }
        }

        if (count($candidates) < 2 || count(array_unique($candidates)) < 2) {
            return null;
        }

        $wantHigh = $best ? ($polarity !== 'down') : ($polarity === 'down');
        $target = $wantHigh ? max($candidates) : min($candidates);

        return array_search($target, $candidates, true);
    }

    private function format(string $type, int|float|null $value): string
    {
        if ($value === null) {
            return __('ceo.matrix.na');
        }

        return match ($type) {
            'currency' => 'RM '.number_format((float) $value),
            default => number_format((float) $value),
        };
    }

    /**
     * Period buckets: one per day for short windows, one per (up to) 7-day chunk
     * for longer ones, so the column count stays readable.
     *
     * @return array<int, array{label: string, days: array<int, string>}>
     */
    private function buckets(CeoPeriod $period): array
    {
        $days = [];
        $cursor = $period->from->startOfDay();
        $end = $period->to->startOfDay();
        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
            if (count($days) > 370) {
                break;
            }
        }

        if (count($days) <= self::DAILY_MAX_DAYS) {
            return array_map(fn (string $d) => [
                'label' => CarbonImmutable::parse($d)->format('j'),
                'days' => [$d],
            ], $days);
        }

        $buckets = [];
        foreach (array_chunk($days, 7) as $chunk) {
            $buckets[] = [
                'label' => CarbonImmutable::parse($chunk[0])->format('j/n'),
                'days' => $chunk,
            ];
        }

        return $buckets;
    }

    /**
     * Component-wise sum of the per-day raw dicts for the given days.
     *
     * @param  array<string, array<string, int|float>>  $perDay
     * @param  array<int, string>  $days
     * @return array<string, int|float>
     */
    private function sumDays(array $perDay, array $days): array
    {
        $sum = [];
        foreach ($days as $day) {
            foreach ($perDay[$day] ?? [] as $key => $value) {
                $sum[$key] = ($sum[$key] ?? 0) + $value;
            }
        }

        return $sum;
    }

    /**
     * @param  array<string, array<string, int|float>>  $perDay
     * @return array<string, int|float>
     */
    private function sumAll(array $perDay): array
    {
        return $this->sumDays($perDay, array_keys($perDay));
    }

    // ───────────────────────── Department specs ─────────────────────────

    /**
     * @return array{perDay: array<string, array<string, int|float>>, prior: array<string, int|float>, metrics: array<int, array<string, mixed>>}
     */
    private function liveHost(CeoPeriod $period): array
    {
        $prior = $period->priorPeriod();

        return [
            'perDay' => $this->liveHostAgg($period->from, $period->to),
            'prior' => $this->sumAll($this->liveHostAgg($prior->from, $prior->to)),
            'metrics' => [
                ['key' => 'completed', 'label' => __('ceo.matrix.m_completed_sessions'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['completed'] ?? 0)],
                ['key' => 'gmv', 'label' => __('ceo.matrix.m_gmv'), 'type' => 'currency', 'polarity' => 'up', 'value' => fn ($d) => (float) ($d['gmv'] ?? 0)],
                ['key' => 'sessions', 'label' => __('ceo.matrix.m_sessions'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['sessions'] ?? 0)],
                ['key' => 'replacements', 'label' => __('ceo.matrix.m_replacements'), 'type' => 'int', 'polarity' => 'down', 'value' => fn ($d) => (int) ($d['replacements'] ?? 0)],
            ],
        ];
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private function liveHostAgg(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ended = "'".implode("','", self::LIVE_ENDED)."'";

        $sessions = LiveSession::query()
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->selectRaw("DATE(scheduled_start_at) as day,
                COUNT(*) as sessions,
                SUM(CASE WHEN status IN ($ended) THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status IN ($ended) THEN gmv_amount + COALESCE(gmv_adjustment, 0) ELSE 0 END) as gmv")
            ->groupBy('day')
            ->get();

        $map = [];
        foreach ($sessions as $r) {
            $map[$r->day] = ['sessions' => (int) $r->sessions, 'completed' => (int) $r->completed, 'gmv' => (float) $r->gmv, 'replacements' => 0];
        }

        $replacements = SessionReplacementRequest::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        foreach ($replacements as $day => $count) {
            $map[$day] ??= ['sessions' => 0, 'completed' => 0, 'gmv' => 0.0, 'replacements' => 0];
            $map[$day]['replacements'] = (int) $count;
        }

        return $map;
    }

    /**
     * @return array{perDay: array<string, array<string, int|float>>, prior: array<string, int|float>, metrics: array<int, array<string, mixed>>}
     */
    private function education(CeoPeriod $period): array
    {
        $prior = $period->priorPeriod();

        return [
            'perDay' => $this->educationAgg($period->from, $period->to),
            'prior' => $this->sumAll($this->educationAgg($prior->from, $prior->to)),
            'metrics' => [
                ['key' => 'completed', 'label' => __('ceo.matrix.m_completed_sessions'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['completed'] ?? 0)],
                ['key' => 'enrollments', 'label' => __('ceo.matrix.m_new_enrollments'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['enrollments'] ?? 0)],
                ['key' => 'no_shows', 'label' => __('ceo.matrix.m_no_shows'), 'type' => 'int', 'polarity' => 'down', 'value' => fn ($d) => (int) ($d['no_show'] ?? 0)],
                ['key' => 'cancelled', 'label' => __('ceo.matrix.m_cancelled'), 'type' => 'int', 'polarity' => 'down', 'value' => fn ($d) => (int) ($d['cancelled'] ?? 0)],
            ],
        ];
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private function educationAgg(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sessions = ClassSession::query()
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE(session_date) as day,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->groupBy('day')
            ->get();

        $map = [];
        foreach ($sessions as $r) {
            $map[$r->day] = ['completed' => (int) $r->completed, 'no_show' => (int) $r->no_show, 'cancelled' => (int) $r->cancelled, 'enrollments' => 0];
        }

        $enrollments = Enrollment::query()
            ->whereBetween('enrollment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DATE(enrollment_date) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        foreach ($enrollments as $day => $count) {
            $map[$day] ??= ['completed' => 0, 'no_show' => 0, 'cancelled' => 0, 'enrollments' => 0];
            $map[$day]['enrollments'] = (int) $count;
        }

        return $map;
    }

    /**
     * @return array{perDay: array<string, array<string, int|float>>, prior: array<string, int|float>, metrics: array<int, array<string, mixed>>}
     */
    private function ecommerce(CeoPeriod $period): array
    {
        $prior = $period->priorPeriod();

        return [
            'perDay' => $this->ecommerceAgg($period->from, $period->to),
            'prior' => $this->sumAll($this->ecommerceAgg($prior->from, $prior->to)),
            'metrics' => [
                ['key' => 'revenue', 'label' => __('ceo.matrix.m_revenue'), 'type' => 'currency', 'polarity' => 'up', 'value' => fn ($d) => (float) ($d['revenue'] ?? 0)],
                ['key' => 'orders', 'label' => __('ceo.matrix.m_orders'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['orders'] ?? 0)],
                ['key' => 'paid', 'label' => __('ceo.matrix.m_paid'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['paid'] ?? 0)],
                ['key' => 'failed', 'label' => __('ceo.matrix.m_failed'), 'type' => 'int', 'polarity' => 'down', 'value' => fn ($d) => (int) ($d['failed'] ?? 0)],
            ],
        ];
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private function ecommerceAgg(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = ProductOrder::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE(created_at) as day,
                COUNT(*) as orders,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue")
            ->groupBy('day')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->day] = ['orders' => (int) $r->orders, 'paid' => (int) $r->paid, 'failed' => (int) $r->failed, 'revenue' => (float) $r->revenue];
        }

        return $map;
    }

    /**
     * @return array{perDay: array<string, array<string, int|float>>, prior: array<string, int|float>, metrics: array<int, array<string, mixed>>}
     */
    private function hr(CeoPeriod $period): array
    {
        $prior = $period->priorPeriod();

        return [
            'perDay' => $this->hrAgg($period->from, $period->to),
            'prior' => $this->sumAll($this->hrAgg($prior->from, $prior->to)),
            'metrics' => [
                ['key' => 'present', 'label' => __('ceo.matrix.m_present'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['present'] ?? 0)],
                ['key' => 'late', 'label' => __('ceo.matrix.m_late'), 'type' => 'int', 'polarity' => 'down', 'value' => fn ($d) => (int) ($d['late'] ?? 0)],
                ['key' => 'absent', 'label' => __('ceo.matrix.m_absent'), 'type' => 'int', 'polarity' => 'down', 'value' => fn ($d) => (int) ($d['absent'] ?? 0)],
            ],
        ];
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private function hrAgg(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = AttendanceLog::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE(date) as day,
                SUM(CASE WHEN status IN ('present', 'wfh') THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->groupBy('day')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->day] = ['present' => (int) $r->present, 'late' => (int) $r->late, 'absent' => (int) $r->absent];
        }

        return $map;
    }

    /**
     * @return array{perDay: array<string, array<string, int|float>>, prior: array<string, int|float>, metrics: array<int, array<string, mixed>>}
     */
    private function sales(CeoPeriod $period): array
    {
        $prior = $period->priorPeriod();

        return [
            'perDay' => $this->salesAgg($period->from, $period->to),
            'prior' => $this->sumAll($this->salesAgg($prior->from, $prior->to)),
            'metrics' => [
                ['key' => 'revenue', 'label' => __('ceo.matrix.m_revenue'), 'type' => 'currency', 'polarity' => 'up', 'value' => fn ($d) => (float) ($d['revenue'] ?? 0)],
                ['key' => 'orders', 'label' => __('ceo.matrix.m_orders'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['orders'] ?? 0)],
                ['key' => 'paid', 'label' => __('ceo.matrix.m_paid'), 'type' => 'int', 'polarity' => 'up', 'value' => fn ($d) => (int) ($d['paid'] ?? 0)],
                ['key' => 'pending', 'label' => __('ceo.matrix.m_pending'), 'type' => 'int', 'polarity' => 'down', 'value' => fn ($d) => (int) ($d['pending'] ?? 0)],
            ],
        ];
    }

    /**
     * Mirrors SalesHealthReport's scope: orders attributed to a salesperson, or
     * unassigned POS orders.
     *
     * @return array<string, array<string, int|float>>
     */
    private function salesAgg(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = ProductOrder::query()
            ->where(function (Builder $q): void {
                $q->whereRaw("json_extract(metadata, '$.salesperson_id') IS NOT NULL")
                    ->orWhere(function (Builder $sub): void {
                        $sub->where('source', 'pos')
                            ->whereRaw("json_extract(metadata, '$.salesperson_id') IS NULL");
                    });
            })
            ->whereBetween('order_date', [$from, $to])
            ->selectRaw("DATE(order_date) as day,
                SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as orders,
                SUM(CASE WHEN paid_time IS NOT NULL THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN paid_time IS NULL AND status != 'cancelled' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN paid_time IS NOT NULL THEN total_amount ELSE 0 END) as revenue")
            ->groupBy('day')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->day] = ['orders' => (int) $r->orders, 'paid' => (int) $r->paid, 'pending' => (int) $r->pending, 'revenue' => (float) $r->revenue];
        }

        return $map;
    }
}
