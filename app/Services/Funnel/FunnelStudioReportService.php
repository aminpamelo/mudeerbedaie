<?php

declare(strict_types=1);

namespace App\Services\Funnel;

use App\Models\FacebookAdInsight;
use App\Models\Funnel;
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

    /**
     * Per-funnel slice of the daily report: sales & orders in the window, plus
     * the spend and ROAS of the ad account the funnel is linked to (its Ads
     * Source), when one is set and visible. Only funnels with activity.
     *
     * @param  array<int, int>  $funnelIds
     * @param  array<int, int>  $adAccountIds
     * @return Collection<int, array<string, mixed>>
     */
    public function byFunnel(array $funnelIds, array $adAccountIds, int $days): Collection
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $from = now()->subDays($days - 1)->startOfDay();
        $adAccountIds = array_map('intval', $adAccountIds);

        $salesByFunnel = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('funnel_id, SUM(funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('funnel_id')
            ->get()
            ->keyBy('funnel_id');

        $spendByAccount = FacebookAdInsight::query()
            ->whereIn('facebook_ad_account_id', $adAccountIds)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw('facebook_ad_account_id, SUM(spend) as spend')
            ->groupBy('facebook_ad_account_id')
            ->get()
            ->keyBy('facebook_ad_account_id');

        return Funnel::query()
            ->whereIn('id', $funnelIds)
            ->get(['id', 'uuid', 'name', 'status', 'settings'])
            ->map(function (Funnel $funnel) use ($salesByFunnel, $spendByAccount, $adAccountIds) {
                $sales = (float) ($salesByFunnel[$funnel->id]->revenue ?? 0);
                $orders = (int) ($salesByFunnel[$funnel->id]->orders ?? 0);
                $accountId = data_get($funnel->settings, 'ads.facebook_ad_account_id');
                $visible = $accountId !== null && in_array((int) $accountId, $adAccountIds, true);
                $spend = $visible ? (float) ($spendByAccount[$accountId]->spend ?? 0) : null;

                return [
                    'funnel_uuid' => $funnel->uuid,
                    'funnel_name' => $funnel->name,
                    'status' => $funnel->status,
                    'sales' => round($sales, 2),
                    'orders' => $orders,
                    'linked_spend' => $spend !== null ? round($spend, 2) : null,
                    'linked_spend_with_sst' => $spend !== null ? round($spend * (1 + self::SST_RATE), 2) : null,
                    'roas' => ($spend !== null && $spend > 0) ? round($sales / $spend, 2) : null,
                ];
            })
            ->filter(fn (array $row) => $row['sales'] > 0 || $row['orders'] > 0 || ($row['linked_spend'] ?? 0) > 0)
            ->sortByDesc('sales')
            ->values();
    }
}
