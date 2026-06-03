<?php

namespace App\Services\Ceo\Reports;

use App\Models\ProductOrder;
use App\Models\ReturnRefund;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\DepartmentHealth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Operational health of the e-commerce operation: are payments going through,
 * is paid stock getting fulfilled, and is the returns queue under control.
 * Revenue is secondary context.
 */
class EcommerceHealthReport
{
    private const UNFULFILLED_STATUSES = ['pending', 'confirmed', 'processing'];

    private const OPEN_RETURN_STATUSES = ['pending', 'processing', 'approved'];

    public function run(CeoPeriod $period): DepartmentHealth
    {
        $today = CarbonImmutable::now()->startOfDay();

        $ordersToday = ProductOrder::query()->whereDate('created_at', $today)->count();

        $payments = $this->paymentBreakdown($period);
        $successRate = $payments['settled'] === 0
            ? null
            : (int) round($payments['paid'] / $payments['settled'] * 100);

        $unfulfilled = ProductOrder::query()
            ->where('payment_status', 'paid')
            ->whereIn('status', self::UNFULFILLED_STATUSES)
            ->count();

        $openReturns = $this->openReturns();

        $revenue = (float) ProductOrder::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->sum('total_amount');

        $status = $this->status($successRate, $unfulfilled, $openReturns['breached']);

        return new DepartmentHealth(
            key: 'ecommerce',
            label: __('ceo.departments.ecommerce'),
            accent: 'violet',
            status: $status,
            href: '/admin/orders',
            metrics: [
                ['label' => __('ceo.metrics.orders_today'), 'value' => (string) $ordersToday],
                ['label' => __('ceo.metrics.payment_success'), 'value' => $successRate === null ? '—' : $successRate.'%', 'hint' => mb_strtolower($period->label()), 'tone' => $this->successTone($successRate)],
                ['label' => __('ceo.metrics.unfulfilled'), 'value' => (string) $unfulfilled, 'hint' => __('ceo.hints.paid_awaiting_ship'), 'tone' => $unfulfilled > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.revenue'), 'value' => 'RM '.number_format($revenue), 'hint' => mb_strtolower($period->label())],
            ],
            trend: $this->dailyTrend($period),
            alerts: $this->alerts($payments['failed'], $unfulfilled, $openReturns),
            extra: [
                'ordersToday' => $ordersToday,
                'failedPayments' => $payments['failed'],
                'revenuePeriod' => $revenue,
                'unfulfilled' => $unfulfilled,
            ],
            gauges: [
                ['label' => __('ceo.metrics.payment_success'), 'value' => $successRate ?? 0, 'target' => 95, 'suffix' => '%', 'tone' => $this->successTone($successRate)],
            ],
            bars: [
                ['label' => __('ceo.metrics.fulfilment_backlog'), 'value' => min($unfulfilled, 50), 'max' => 50, 'valueLabel' => __('ceo.hints.unfulfilled_count', ['count' => $unfulfilled]), 'tone' => $unfulfilled > 15 ? 'warning' : 'positive'],
            ],
        );
    }

    /**
     * Rich drill-in payload for the dedicated E-commerce detail page.
     *
     * @return array<string, mixed>
     */
    public function detail(CeoPeriod $period): array
    {
        $health = $this->run($period);

        $payments = $this->paymentBreakdown($period);
        $revenue = (float) $health->extra['revenuePeriod'];
        $paidOrders = $payments['paid'];
        $aov = $paidOrders > 0 ? $revenue / $paidOrders : 0.0;

        $statusCounts = ProductOrder::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $recentOrders = ProductOrder::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest('created_at')
            ->limit(8)
            ->get(['order_number', 'status', 'payment_status', 'total_amount']);

        return [
            'key' => $health->key,
            'label' => $health->label,
            'accent' => $health->accent,
            'status' => $health->status,
            'moduleHref' => '/admin/orders',
            'moduleLabel' => __('ceo.modules.ecommerce'),
            'gauges' => $health->gauges,
            'alerts' => $health->alerts,
            'kpis' => [
                ['label' => __('ceo.metrics.orders_today'), 'value' => (string) $health->extra['ordersToday']],
                ['label' => __('ceo.metrics.payment_success'), 'value' => $payments['settled'] > 0 ? ((int) round($payments['paid'] / $payments['settled'] * 100)).'%' : '—', 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.unfulfilled'), 'value' => (string) $health->extra['unfulfilled'], 'tone' => $health->extra['unfulfilled'] > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.revenue'), 'value' => 'RM '.number_format($revenue), 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.failed_payments'), 'value' => (string) $payments['failed'], 'tone' => $payments['failed'] > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.avg_order_value'), 'value' => 'RM '.number_format($aov, 2)],
            ],
            'sections' => [
                [
                    'type' => 'chart',
                    'title' => __('ceo.sections.paid_orders'),
                    'subtitle' => mb_strtolower($period->label()),
                    'data' => $health->trend,
                ],
                [
                    'type' => 'breakdown',
                    'title' => __('ceo.sections.payment_status'),
                    'segments' => [
                        ['label' => __('ceo.segments.paid'), 'value' => $payments['paid'], 'tone' => 'positive'],
                        ['label' => __('ceo.segments.failed'), 'value' => $payments['failed'], 'tone' => 'negative'],
                    ],
                ],
                [
                    'type' => 'breakdown',
                    'title' => __('ceo.sections.orders_by_status'),
                    'segments' => [
                        ['label' => __('ceo.segments.completed'), 'value' => (int) ($statusCounts['completed'] ?? 0) + (int) ($statusCounts['delivered'] ?? 0), 'tone' => 'positive'],
                        ['label' => __('ceo.segments.in_progress'), 'value' => (int) ($statusCounts['pending'] ?? 0) + (int) ($statusCounts['confirmed'] ?? 0) + (int) ($statusCounts['processing'] ?? 0) + (int) ($statusCounts['shipped'] ?? 0), 'tone' => 'info'],
                        ['label' => __('ceo.segments.cancelled'), 'value' => (int) ($statusCounts['cancelled'] ?? 0), 'tone' => 'negative'],
                    ],
                ],
                [
                    'type' => 'list',
                    'title' => __('ceo.sections.recent_orders'),
                    'subtitle' => mb_strtolower($period->label()),
                    'columns' => [
                        ['key' => 'order', 'label' => __('ceo.columns.order')],
                        ['key' => 'status', 'label' => __('ceo.columns.status')],
                        ['key' => 'amount', 'label' => __('ceo.columns.amount'), 'align' => 'right'],
                    ],
                    'rows' => $recentOrders->map(fn (ProductOrder $o) => [
                        'order' => (string) $o->order_number,
                        'status' => __('ceo.payment_status.'.$o->payment_status),
                        'amount' => 'RM '.number_format((float) $o->total_amount, 2),
                    ])->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{paid: int, failed: int, settled: int}
     */
    private function paymentBreakdown(CeoPeriod $period): array
    {
        $counts = ProductOrder::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->whereIn('payment_status', ['paid', 'failed'])
            ->selectRaw('payment_status, COUNT(*) as c')
            ->groupBy('payment_status')
            ->pluck('c', 'payment_status');

        $paid = (int) ($counts['paid'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);

        return ['paid' => $paid, 'failed' => $failed, 'settled' => $paid + $failed];
    }

    /**
     * @return array{open: int, breached: int}
     */
    private function openReturns(): array
    {
        if (! Schema::hasTable('return_refunds')) {
            return ['open' => 0, 'breached' => 0];
        }

        $open = ReturnRefund::query()->whereIn('status', self::OPEN_RETURN_STATUSES)->count();
        $breached = ReturnRefund::query()
            ->whereIn('status', self::OPEN_RETURN_STATUSES)
            ->where('sla_breached', true)
            ->count();

        return ['open' => $open, 'breached' => $breached];
    }

    private function status(?int $successRate, int $unfulfilled, int $breachedReturns): string
    {
        if (($successRate !== null && $successRate < 85) || $breachedReturns > 0 || $unfulfilled > 50) {
            return DepartmentHealth::RED;
        }

        if (($successRate !== null && $successRate < 95) || $unfulfilled > 15) {
            return DepartmentHealth::AMBER;
        }

        return DepartmentHealth::GREEN;
    }

    private function successTone(?int $rate): string
    {
        return match (true) {
            $rate === null => 'muted',
            $rate >= 95 => 'positive',
            $rate >= 85 => 'warning',
            default => 'negative',
        };
    }

    /**
     * @param  array{open: int, breached: int}  $returns
     * @return array<int, array{severity: string, message: string, href?: string}>
     */
    private function alerts(int $failedPayments, int $unfulfilled, array $returns): array
    {
        $alerts = [];

        if ($unfulfilled > 15) {
            $alerts[] = [
                'severity' => $unfulfilled > 50 ? 'critical' : 'warning',
                'message' => trans_choice('ceo.alerts.unfulfilled_orders', $unfulfilled, ['count' => $unfulfilled]),
                'href' => '/admin/orders',
            ];
        }

        if ($failedPayments > 0) {
            $alerts[] = [
                'severity' => 'info',
                'message' => trans_choice('ceo.alerts.failed_payments', $failedPayments, ['count' => $failedPayments]),
                'href' => '/admin/orders',
            ];
        }

        if ($returns['breached'] > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => trans_choice('ceo.alerts.returns_sla', $returns['breached'], ['count' => $returns['breached']]),
                'href' => '/admin/orders',
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, int>
     */
    private function dailyTrend(CeoPeriod $period): array
    {
        $rows = ProductOrder::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->where('payment_status', 'paid')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        return TrendFiller::daily($period, $rows);
    }
}
