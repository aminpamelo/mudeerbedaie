<?php

namespace App\Services\Ceo\Reports;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\SalesSource;
use App\Models\User;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\DepartmentHealth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Operational health of the Sales Department: are the team's orders converting
 * to paid, and is the unpaid backlog under control. Scope mirrors the admin
 * Sales Department report — orders attributed to a salesperson (via
 * metadata->salesperson_id) plus unassigned POS orders. Revenue is context.
 */
class SalesHealthReport
{
    public function run(CeoPeriod $period): DepartmentHealth
    {
        $today = CarbonImmutable::now()->startOfDay();

        $salesToday = $this->scoped(ProductOrder::query())
            ->whereDate('order_date', $today)
            ->count();

        $counts = $this->paymentBreakdown($period);
        $settled = $counts['paid'] + $counts['pending'];
        $conversion = $settled === 0
            ? null
            : (int) round($counts['paid'] / $settled * 100);

        $revenue = (float) $this->base($period)->whereNotNull('paid_time')->sum('total_amount');
        $aov = $counts['paid'] > 0 ? $revenue / $counts['paid'] : 0.0;
        $itemsSold = $this->itemsSold($period);

        $status = $this->status($conversion, $counts['pending']);

        return new DepartmentHealth(
            key: 'sales',
            label: __('ceo.departments.sales'),
            accent: 'brand',
            status: $status,
            href: '/admin/reports/sales-department',
            metrics: [
                ['label' => __('ceo.metrics.revenue'), 'value' => 'RM '.number_format($revenue), 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.paid_conversion'), 'value' => $conversion === null ? '—' : $conversion.'%', 'hint' => mb_strtolower($period->label()), 'tone' => $this->conversionTone($conversion)],
                ['label' => __('ceo.metrics.orders'), 'value' => (string) $settled, 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.pending_orders'), 'value' => (string) $counts['pending'], 'hint' => __('ceo.hints.awaiting_payment'), 'tone' => $counts['pending'] > 0 ? 'warning' : 'muted'],
            ],
            trend: $this->dailyRevenue($period),
            alerts: $this->alerts($counts['pending'], $conversion),
            extra: [
                'revenuePeriod' => $revenue,
                'paid' => $counts['paid'],
                'pending' => $counts['pending'],
                'cancelled' => $counts['cancelled'],
                'salesToday' => $salesToday,
                'itemsSold' => $itemsSold,
                'aov' => $aov,
            ],
            gauges: [
                ['label' => __('ceo.metrics.paid_conversion'), 'value' => $conversion ?? 0, 'target' => 80, 'suffix' => '%', 'tone' => $this->conversionTone($conversion)],
            ],
            bars: [
                ['label' => __('ceo.metrics.pending_orders'), 'value' => min($counts['pending'], 50), 'max' => 50, 'valueLabel' => __('ceo.hints.pending_count', ['count' => $counts['pending']]), 'tone' => $counts['pending'] > 15 ? 'warning' : 'positive'],
            ],
        );
    }

    /**
     * Rich drill-in payload for the dedicated Sales detail page.
     *
     * @return array<string, mixed>
     */
    public function detail(CeoPeriod $period): array
    {
        $health = $this->run($period);

        $counts = $this->paymentBreakdown($period);
        $settled = $counts['paid'] + $counts['pending'];
        $conversion = $settled === 0 ? null : (int) round($counts['paid'] / $settled * 100);

        $revenue = (float) $health->extra['revenuePeriod'];
        $aov = (float) $health->extra['aov'];
        $itemsSold = (int) $health->extra['itemsSold'];

        return [
            'key' => $health->key,
            'label' => $health->label,
            'accent' => $health->accent,
            'status' => $health->status,
            'moduleHref' => '/admin/reports/sales-department',
            'moduleLabel' => __('ceo.modules.sales'),
            'gauges' => $health->gauges,
            'alerts' => $health->alerts,
            'kpis' => [
                ['label' => __('ceo.metrics.revenue'), 'value' => 'RM '.number_format($revenue), 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.paid_conversion'), 'value' => $conversion === null ? '—' : $conversion.'%', 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.orders'), 'value' => (string) $settled],
                ['label' => __('ceo.metrics.avg_order_value'), 'value' => 'RM '.number_format($aov, 2)],
                ['label' => __('ceo.metrics.items_sold'), 'value' => number_format($itemsSold)],
                ['label' => __('ceo.metrics.pending_orders'), 'value' => (string) $counts['pending'], 'tone' => $counts['pending'] > 0 ? 'warning' : 'muted'],
            ],
            'sections' => [
                [
                    'type' => 'chart',
                    'title' => __('ceo.sections.revenue_trend'),
                    'subtitle' => mb_strtolower($period->label()),
                    'data' => $health->trend,
                ],
                [
                    'type' => 'list',
                    'title' => __('ceo.sections.top_salespersons'),
                    'subtitle' => mb_strtolower($period->label()),
                    'columns' => [
                        ['key' => 'salesperson', 'label' => __('ceo.columns.salesperson')],
                        ['key' => 'orders', 'label' => __('ceo.columns.orders'), 'align' => 'right'],
                        ['key' => 'revenue', 'label' => __('ceo.columns.revenue'), 'align' => 'right'],
                    ],
                    'rows' => array_map(fn (array $r) => [
                        'salesperson' => $r['name'],
                        'orders' => (string) $r['orders'],
                        'revenue' => 'RM '.number_format($r['revenue'], 2),
                    ], $this->topSalespersons($period)),
                ],
                [
                    'type' => 'breakdown',
                    'title' => __('ceo.sections.payment_status'),
                    'segments' => [
                        ['label' => __('ceo.segments.paid'), 'value' => $counts['paid'], 'tone' => 'positive'],
                        ['label' => __('ceo.segments.pending'), 'value' => $counts['pending'], 'tone' => 'info'],
                        ['label' => __('ceo.segments.cancelled'), 'value' => $counts['cancelled'], 'tone' => 'negative'],
                    ],
                ],
                [
                    'type' => 'list',
                    'title' => __('ceo.sections.sales_by_source'),
                    'subtitle' => mb_strtolower($period->label()),
                    'columns' => [
                        ['key' => 'source', 'label' => __('ceo.columns.source')],
                        ['key' => 'orders', 'label' => __('ceo.columns.orders'), 'align' => 'right'],
                        ['key' => 'revenue', 'label' => __('ceo.columns.revenue'), 'align' => 'right'],
                    ],
                    'rows' => array_map(fn (array $r) => [
                        'source' => $r['name'],
                        'orders' => (string) $r['orders'],
                        'revenue' => 'RM '.number_format($r['revenue'], 2),
                    ], $this->sourceBreakdown($period)),
                ],
            ],
        ];
    }

    /**
     * Sales-team order scope: attributed to a salesperson, or unassigned POS.
     * Matches the admin Sales Department report's baseQuery.
     */
    private function scoped(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereRaw("json_extract(metadata, '$.salesperson_id') IS NOT NULL")
                ->orWhere(function (Builder $sub): void {
                    $sub->where('source', 'pos')
                        ->whereRaw("json_extract(metadata, '$.salesperson_id') IS NULL");
                });
        });
    }

    /**
     * Fresh, period-bounded scoped query (by order date).
     */
    private function base(CeoPeriod $period): Builder
    {
        return $this->scoped(ProductOrder::query())
            ->whereBetween('order_date', [$period->from, $period->to]);
    }

    /**
     * @return array{paid: int, pending: int, cancelled: int}
     */
    private function paymentBreakdown(CeoPeriod $period): array
    {
        $paid = (int) $this->base($period)->whereNotNull('paid_time')->count();
        $pending = (int) $this->base($period)->whereNull('paid_time')->where('status', '!=', 'cancelled')->count();
        $cancelled = (int) $this->base($period)->where('status', 'cancelled')->count();

        return ['paid' => $paid, 'pending' => $pending, 'cancelled' => $cancelled];
    }

    private function itemsSold(CeoPeriod $period): int
    {
        return (int) ProductOrderItem::query()
            ->whereIn('order_id', $this->base($period)->where('status', '!=', 'cancelled')->select('id'))
            ->sum('quantity_ordered');
    }

    /**
     * Top salespersons by paid revenue this period. Orders are grouped in PHP
     * because the attribution lives in the metadata JSON column.
     *
     * @return array<int, array{name: string, orders: int, revenue: float}>
     */
    private function topSalespersons(CeoPeriod $period): array
    {
        $orders = $this->base($period)
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'metadata', 'total_amount', 'paid_time']);

        $agg = [];
        foreach ($orders as $order) {
            $id = $order->metadata['salesperson_id'] ?? 'unassigned';
            if (! isset($agg[$id])) {
                $agg[$id] = ['orders' => 0, 'revenue' => 0.0];
            }
            $agg[$id]['orders']++;
            if ($order->paid_time !== null) {
                $agg[$id]['revenue'] += (float) $order->total_amount;
            }
        }

        $ids = array_filter(array_keys($agg), fn ($k) => $k !== 'unassigned');
        $names = User::query()->whereIn('id', $ids)->pluck('name', 'id');

        $rows = [];
        foreach ($agg as $id => $data) {
            $rows[] = [
                'name' => $id === 'unassigned' ? __('ceo.sales.unassigned') : ($names[$id] ?? '#'.$id),
                'orders' => $data['orders'],
                'revenue' => $data['revenue'],
            ];
        }

        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return array_slice($rows, 0, 6);
    }

    /**
     * Revenue and order counts per sales source channel this period.
     *
     * @return array<int, array{name: string, orders: int, revenue: float}>
     */
    private function sourceBreakdown(CeoPeriod $period): array
    {
        $rows = [];

        foreach (SalesSource::query()->ordered()->get() as $source) {
            $orders = (int) $this->base($period)->where('status', '!=', 'cancelled')->where('sales_source_id', $source->id)->count();
            if ($orders === 0) {
                continue;
            }
            $revenue = (float) $this->base($period)->where('sales_source_id', $source->id)->whereNotNull('paid_time')->sum('total_amount');
            $rows[] = ['name' => $source->name, 'orders' => $orders, 'revenue' => $revenue];
        }

        $noSourceOrders = (int) $this->base($period)->where('status', '!=', 'cancelled')->whereNull('sales_source_id')->count();
        if ($noSourceOrders > 0) {
            $rows[] = [
                'name' => __('ceo.sales.no_source'),
                'orders' => $noSourceOrders,
                'revenue' => (float) $this->base($period)->whereNull('sales_source_id')->whereNotNull('paid_time')->sum('total_amount'),
            ];
        }

        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $rows;
    }

    private function status(?int $conversion, int $pending): string
    {
        if (($conversion !== null && $conversion < 60) || $pending > 50) {
            return DepartmentHealth::RED;
        }

        if (($conversion !== null && $conversion < 80) || $pending > 15) {
            return DepartmentHealth::AMBER;
        }

        return DepartmentHealth::GREEN;
    }

    private function conversionTone(?int $rate): string
    {
        return match (true) {
            $rate === null => 'muted',
            $rate >= 80 => 'positive',
            $rate >= 60 => 'warning',
            default => 'negative',
        };
    }

    /**
     * @return array<int, array{severity: string, message: string, href?: string}>
     */
    private function alerts(int $pending, ?int $conversion): array
    {
        $alerts = [];

        if ($pending > 15) {
            $alerts[] = [
                'severity' => $pending > 50 ? 'critical' : 'warning',
                'message' => trans_choice('ceo.alerts.sales_pending_payment', $pending, ['count' => $pending]),
                'href' => '/admin/reports/sales-department',
            ];
        }

        if ($conversion !== null && $conversion < 60) {
            $alerts[] = [
                'severity' => 'info',
                'message' => __('ceo.alerts.low_conversion', ['rate' => $conversion]),
                'href' => '/admin/reports/sales-department',
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, int|float>
     */
    private function dailyRevenue(CeoPeriod $period): array
    {
        $rows = $this->base($period)
            ->whereNotNull('paid_time')
            ->selectRaw('DATE(order_date) as day, COALESCE(SUM(total_amount), 0) as c')
            ->groupBy('day')
            ->pluck('c', 'day')
            ->map(fn ($v) => (float) $v);

        return TrendFiller::daily($period, $rows);
    }
}
