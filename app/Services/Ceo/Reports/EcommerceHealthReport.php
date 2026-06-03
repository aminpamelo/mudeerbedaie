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
            label: 'E-commerce',
            accent: 'violet',
            status: $status,
            href: '/admin/orders',
            metrics: [
                ['label' => 'Orders today', 'value' => (string) $ordersToday],
                ['label' => 'Payment success', 'value' => $successRate === null ? '—' : $successRate.'%', 'hint' => strtolower($period->label()), 'tone' => $this->successTone($successRate)],
                ['label' => 'Unfulfilled', 'value' => (string) $unfulfilled, 'hint' => 'paid, awaiting ship', 'tone' => $unfulfilled > 0 ? 'warning' : 'muted'],
                ['label' => 'Revenue', 'value' => 'RM '.number_format($revenue), 'hint' => strtolower($period->label())],
            ],
            trend: $this->dailyTrend($period),
            alerts: $this->alerts($payments['failed'], $unfulfilled, $openReturns),
            extra: [
                'ordersToday' => $ordersToday,
                'failedPayments' => $payments['failed'],
                'revenuePeriod' => $revenue,
                'unfulfilled' => $unfulfilled,
            ],
        );
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
                'message' => $unfulfilled.' paid orders awaiting fulfilment',
                'href' => '/admin/orders',
            ];
        }

        if ($failedPayments > 0) {
            $alerts[] = [
                'severity' => 'info',
                'message' => $failedPayments.' failed '.($failedPayments === 1 ? 'payment' : 'payments').' this period',
                'href' => '/admin/orders',
            ];
        }

        if ($returns['breached'] > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => $returns['breached'].' return '.($returns['breached'] === 1 ? 'request has' : 'requests have').' breached SLA',
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
