<?php

namespace App\Services\Ceo\Reports;

use App\Models\ProductOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds an industry-standard "monthly performance" scorecard: KPI rows ×
 * Jan–Dec columns for one year, with YTD totals/averages, a trend sparkline,
 * month-over-month change, and best/worst-month highlights. Each metric carries
 * a polarity (up = higher is better, down = lower is better) so colours read
 * correctly (e.g. revenue ▲ green, failed payments ▲ red).
 *
 * Scoped to E-commerce for now; the shape generalises to other departments.
 */
class MonthlyReportService
{
    public const DEPARTMENTS = ['ecommerce'];

    /**
     * @return array<string, mixed>|null
     */
    public function build(string $department, int $year): ?array
    {
        if ($department !== 'ecommerce') {
            return null;
        }

        $now = CarbonImmutable::now();
        $maxMonth = $year >= $now->year ? $now->month : 12;
        $locale = app()->getLocale();

        $monthly = $this->ecommerceMonthly($year);

        // Active month = had at least one order (and not in the future).
        $active = [];
        for ($m = 1; $m <= 12; $m++) {
            $active[$m] = $m <= $maxMonth && ($monthly[$m]['orders'] ?? 0) > 0;
        }

        $metrics = [
            ['key' => 'revenue', 'label' => __('ceo.monthly.m_revenue'), 'type' => 'currency', 'agg' => 'sum', 'polarity' => 'up', 'value' => fn ($d) => (float) $d['revenue']],
            ['key' => 'orders', 'label' => __('ceo.monthly.m_orders'), 'type' => 'int', 'agg' => 'sum', 'polarity' => 'up', 'value' => fn ($d) => (int) $d['orders']],
            ['key' => 'paid', 'label' => __('ceo.monthly.m_paid'), 'type' => 'int', 'agg' => 'sum', 'polarity' => 'up', 'value' => fn ($d) => (int) $d['paid']],
            ['key' => 'success', 'label' => __('ceo.monthly.m_success'), 'type' => 'percent', 'agg' => 'rate', 'polarity' => 'up', 'value' => fn ($d) => ($d['paid'] + $d['failed']) > 0 ? round($d['paid'] / ($d['paid'] + $d['failed']) * 100) : null],
            ['key' => 'aov', 'label' => __('ceo.monthly.m_aov'), 'type' => 'currency2', 'agg' => 'ratio', 'polarity' => 'up', 'value' => fn ($d) => $d['paid'] > 0 ? $d['revenue'] / $d['paid'] : null],
            ['key' => 'failed', 'label' => __('ceo.monthly.m_failed'), 'type' => 'int', 'agg' => 'sum', 'polarity' => 'down', 'value' => fn ($d) => (int) $d['failed']],
            ['key' => 'cancelled', 'label' => __('ceo.monthly.m_cancelled'), 'type' => 'int', 'agg' => 'sum', 'polarity' => 'down', 'value' => fn ($d) => (int) $d['cancelled']],
        ];

        $rows = array_map(fn ($metric) => $this->buildRow($metric, $monthly, $active), $metrics);

        $revenueTrend = [];
        for ($m = 1; $m <= 12; $m++) {
            $revenueTrend[] = $active[$m] ? (int) round($monthly[$m]['revenue']) : 0;
        }
        $totalRevenue = array_sum(array_map(fn ($m) => $monthly[$m]['revenue'], range(1, 12)));

        return [
            'department' => 'ecommerce',
            'label' => __('ceo.departments.ecommerce'),
            'moduleHref' => '/admin/orders',
            'moduleLabel' => __('ceo.modules.ecommerce'),
            'year' => $year,
            'prevYear' => $year - 1,
            'nextYear' => $year < $now->year ? $year + 1 : null,
            'months' => $this->monthHeaders($locale),
            'columns' => [
                'metric' => __('ceo.monthly.col_metric'),
                'ytdTotal' => __('ceo.monthly.col_ytd_total'),
                'ytdAvg' => __('ceo.monthly.col_ytd_avg'),
                'trend' => __('ceo.monthly.col_trend'),
                'mom' => __('ceo.monthly.col_mom'),
            ],
            'rows' => $rows,
            'summary' => [
                'totalRevenueLabel' => __('ceo.monthly.total_revenue'),
                'totalRevenue' => 'RM '.number_format($totalRevenue),
                'revenueTrend' => $revenueTrend,
            ],
        ];
    }

    /**
     * One per-month aggregate pass over the year's orders.
     *
     * @return array<int, array{orders: int, paid: int, failed: int, revenue: float, cancelled: int}>
     */
    private function ecommerceMonthly(int $year): array
    {
        $monthExpr = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        $rows = ProductOrder::query()
            ->whereYear('created_at', $year)
            ->selectRaw("$monthExpr as m,
                COUNT(*) as orders,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->groupBy('m')
            ->get()
            ->keyBy('m');

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $r = $rows->get($m);
            $monthly[$m] = [
                'orders' => (int) ($r->orders ?? 0),
                'paid' => (int) ($r->paid ?? 0),
                'failed' => (int) ($r->failed ?? 0),
                'revenue' => (float) ($r->revenue ?? 0),
                'cancelled' => (int) ($r->cancelled ?? 0),
            ];
        }

        return $monthly;
    }

    /**
     * @param  array<string, mixed>  $metric
     * @param  array<int, array<string, mixed>>  $monthly
     * @param  array<int, bool>  $active
     * @return array<string, mixed>
     */
    private function buildRow(array $metric, array $monthly, array $active): array
    {
        $valueFn = $metric['value'];

        $values = [];   // numeric or null, 0-indexed Jan..Dec
        $display = [];   // formatted strings ('' for inactive months)
        $trend = [];
        for ($m = 1; $m <= 12; $m++) {
            $v = $active[$m] ? $valueFn($monthly[$m]) : null;
            $values[] = $v;
            $display[] = $active[$m] ? $this->format($metric['type'], $v) : '';
            $trend[] = is_numeric($v) ? (float) $v : 0;
        }

        // YTD aggregates.
        $sum = 0.0;
        $count = 0;
        $totalPaid = 0;
        $totalSettled = 0;
        $totalRevenue = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            if (! $active[$m]) {
                continue;
            }
            $count++;
            $totalPaid += $monthly[$m]['paid'];
            $totalSettled += $monthly[$m]['paid'] + $monthly[$m]['failed'];
            $totalRevenue += $monthly[$m]['revenue'];
            $v = $valueFn($monthly[$m]);
            if (is_numeric($v)) {
                $sum += $v;
            }
        }

        [$ytdTotal, $ytdAvg] = match ($metric['agg']) {
            'sum' => [
                $this->format($metric['type'], $sum),
                $count > 0 ? $this->format($metric['type'], $sum / $count) : __('ceo.monthly.na'),
            ],
            'rate' => [
                __('ceo.monthly.na'),
                $totalSettled > 0 ? $this->format($metric['type'], round($totalPaid / $totalSettled * 100)) : __('ceo.monthly.na'),
            ],
            'ratio' => [
                __('ceo.monthly.na'),
                $totalPaid > 0 ? $this->format($metric['type'], $totalRevenue / $totalPaid) : __('ceo.monthly.na'),
            ],
            default => [__('ceo.monthly.na'), __('ceo.monthly.na')],
        };

        return [
            'key' => $metric['key'],
            'label' => $metric['label'],
            'polarity' => $metric['polarity'],
            'display' => $display,
            'trend' => $trend,
            'ytdTotal' => $ytdTotal,
            'ytdAvg' => $ytdAvg,
            'mom' => $this->momChange($values, $active, $metric['polarity']),
            'bestIndex' => $this->extremeIndex($values, $active, $metric['polarity'], true),
            'worstIndex' => $this->extremeIndex($values, $active, $metric['polarity'], false),
        ];
    }

    /**
     * Month-over-month change between the two most recent active months.
     *
     * @param  array<int, int|float|null>  $values  0-indexed
     * @param  array<int, bool>  $active  1-indexed
     * @return array{text: string, tone: string}|null
     */
    private function momChange(array $values, array $active, string $polarity): ?array
    {
        $activeIdx = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($active[$m] && is_numeric($values[$m - 1])) {
                $activeIdx[] = $m - 1;
            }
        }
        if (count($activeIdx) < 2) {
            return null;
        }

        $last = $values[$activeIdx[count($activeIdx) - 1]];
        $prev = $values[$activeIdx[count($activeIdx) - 2]];
        if ($prev == 0.0) {
            return null;
        }

        $change = ($last - $prev) / abs($prev) * 100;
        $improved = $polarity === 'down' ? $change < 0 : $change > 0;
        $tone = abs($change) < 0.5 ? 'muted' : ($improved ? 'positive' : 'negative');

        return ['text' => sprintf('%+d%%', (int) round($change)), 'tone' => $tone];
    }

    /**
     * Index (0-based) of the best or worst active month for the metric.
     *
     * @param  array<int, int|float|null>  $values
     * @param  array<int, bool>  $active
     */
    private function extremeIndex(array $values, array $active, string $polarity, bool $best): ?int
    {
        $candidates = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($active[$m] && is_numeric($values[$m - 1])) {
                $candidates[$m - 1] = $values[$m - 1];
            }
        }
        if (count($candidates) < 2) {
            return null;
        }

        $wantHigh = $best ? ($polarity !== 'down') : ($polarity === 'down');
        $target = $wantHigh ? max($candidates) : min($candidates);

        return array_search($target, $candidates, true);
    }

    private function format(string $type, int|float|null $value): string
    {
        if ($value === null) {
            return __('ceo.monthly.na');
        }

        return match ($type) {
            'currency' => 'RM '.number_format((float) $value),
            'currency2' => 'RM '.number_format((float) $value, 2),
            'percent' => round((float) $value).'%',
            default => number_format((float) $value),
        };
    }

    /**
     * Localized short month names (Jan..Dec).
     *
     * @return array<int, string>
     */
    private function monthHeaders(string $locale): array
    {
        $headers = [];
        for ($m = 1; $m <= 12; $m++) {
            $headers[] = ucfirst(CarbonImmutable::create(2000, $m, 1)->locale($locale)->isoFormat('MMM'));
        }

        return $headers;
    }
}
