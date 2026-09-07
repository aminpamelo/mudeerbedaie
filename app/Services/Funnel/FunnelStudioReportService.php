<?php

declare(strict_types=1);

namespace App\Services\Funnel;

use App\Models\FacebookAdInsight;
use App\Models\FunnelOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes the daily ad-spend-vs-sales P&L used by both the Studio Daily
 * Reporting page and the Funnel Studio MCP server, so the money math (SST,
 * ROAS, net) lives in exactly one place and the two surfaces never drift.
 */
class FunnelStudioReportService
{
    /**
     * Malaysian Service Tax charged by Meta on ad spend (8% since 1 Mar 2024).
     */
    public const SST_RATE = 0.08;

    /**
     * Daily spend/sales P&L for a set of funnels and ad accounts: a per-day
     * series (most-recent-first), a per-calendar-month rollup, and totals.
     *
     * @param  array<int, int>  $funnelIds
     * @param  array<int, int>  $adAccountIds
     * @return array{days: int, sst_rate: float, totals: array<string, mixed>, daily: array<int, array<string, mixed>>, monthly: Collection<int, array<string, mixed>>}
     */
    public function dailyReport(array $funnelIds, array $adAccountIds, int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $from = now()->subDays($days - 1)->startOfDay();

        $spendByDay = FacebookAdInsight::query()
            ->whereIn('facebook_ad_account_id', $adAccountIds)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw('DATE(date) as day, SUM(spend) as spend')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $salesByDay = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, SUM(funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = now()->subDays($i)->toDateString();
            $spend = (float) ($spendByDay[$day]->spend ?? 0);
            $sales = (float) ($salesByDay[$day]->revenue ?? 0);
            $orders = (int) ($salesByDay[$day]->orders ?? 0);
            $spendWithSst = round($spend * (1 + self::SST_RATE), 2);

            $series[] = [
                'day' => $day,
                'spend' => round($spend, 2),
                'spend_with_sst' => $spendWithSst,
                'sales' => round($sales, 2),
                'orders' => $orders,
                'roas' => $spend > 0 ? round($sales / $spend, 2) : null,
                'net' => round($sales - $spendWithSst, 2),
            ];
        }
        $rows = collect($series);

        $monthly = $rows
            ->groupBy(fn (array $row) => substr($row['day'], 0, 7))
            ->map(function (Collection $group, string $month) {
                $spend = round($group->sum('spend'), 2);
                $spendWithSst = round($group->sum('spend_with_sst'), 2);
                $sales = round($group->sum('sales'), 2);
                $orders = (int) $group->sum('orders');

                return [
                    'month' => $month,
                    'month_label' => Carbon::parse($month.'-01')->format('M Y'),
                    'spend' => $spend,
                    'spend_with_sst' => $spendWithSst,
                    'sales' => $sales,
                    'orders' => $orders,
                    'roas' => $spend > 0 ? round($sales / $spend, 2) : null,
                    'net' => round($sales - $spendWithSst, 2),
                ];
            })
            ->values()
            ->sortByDesc('month')
            ->values();

        $totalSpend = round($rows->sum('spend'), 2);
        $totalSpendWithSst = round($rows->sum('spend_with_sst'), 2);
        $totalSales = round($rows->sum('sales'), 2);
        $totalOrders = (int) $rows->sum('orders');

        return [
            'days' => $days,
            'sst_rate' => self::SST_RATE,
            'totals' => [
                'spend' => $totalSpend,
                'spend_with_sst' => $totalSpendWithSst,
                'sales' => $totalSales,
                'orders' => $totalOrders,
                'roas' => $totalSpend > 0 ? round($totalSales / $totalSpend, 2) : null,
                'net' => round($totalSales - $totalSpendWithSst, 2),
                'avg_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
            ],
            'daily' => $series,
            'monthly' => $monthly,
        ];
    }
}
