<?php

declare(strict_types=1);

namespace App\Services\Fighter;

use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Funnel\FunnelStudioReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cross-fighter performance for the admin Fighter Report: per fighter, the
 * funnel traffic, funnel + manual sales, ad spend from their own Business
 * Managers (with SST), ROAS and net over a date range. ROAS/net use funnel
 * sales only, matching each fighter's own Daily Reporting page.
 */
class FighterPerformanceReport
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        // `date` columns may be stored with a time part on SQLite, so bound
        // them with "< next day" rather than "<= last day".
        $dayAfter = $to->copy()->addDay()->toDateString();

        $fighters = User::query()
            ->where('role', 'fighter')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'sales_source_id', 'created_at']);

        $fighterIds = $fighters->pluck('id');

        $funnels = Funnel::query()
            ->whereIn('user_id', $fighterIds)
            ->get(['id', 'user_id', 'status']);
        $funnelOwner = $funnels->pluck('user_id', 'id');

        $funnelSales = FunnelOrder::query()
            ->whereIn('funnel_id', $funnels->pluck('id'))
            ->whereBetween('created_at', [$from, $to])
            ->get(['funnel_id', 'funnel_revenue', 'order_type'])
            ->groupBy(fn (FunnelOrder $o) => $funnelOwner[$o->funnel_id]);

        $visitors = FunnelAnalytics::query()
            ->whereIn('funnel_id', $funnels->pluck('id'))
            ->whereNull('funnel_step_id')
            ->where('date', '>=', $from->toDateString())
            ->where('date', '<', $dayAfter)
            ->selectRaw('funnel_id, SUM(unique_visitors) as visitors')
            ->groupBy('funnel_id')
            ->get()
            ->groupBy(fn ($row) => $funnelOwner[$row->funnel_id])
            ->map(fn (Collection $rows) => (int) $rows->sum('visitors'));

        // Manual (POS) orders = orders on the fighter's segment that did not
        // come through a funnel checkout.
        $manual = ProductOrder::query()
            ->whereIn('sales_source_id', $fighters->pluck('sales_source_id')->filter())
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('id', FunnelOrder::query()->whereNotNull('product_order_id')->select('product_order_id'))
            ->selectRaw('sales_source_id, SUM(total_amount) as sales, COUNT(*) as orders')
            ->groupBy('sales_source_id')
            ->get()
            ->keyBy('sales_source_id');

        $spend = FacebookAdInsight::query()
            ->join('facebook_ad_accounts', 'facebook_ad_accounts.id', '=', 'facebook_ad_insights.facebook_ad_account_id')
            ->join('facebook_ad_connections', 'facebook_ad_connections.id', '=', 'facebook_ad_accounts.facebook_ad_connection_id')
            ->whereIn('facebook_ad_connections.user_id', $fighterIds)
            ->where('facebook_ad_insights.date', '>=', $from->toDateString())
            ->where('facebook_ad_insights.date', '<', $dayAfter)
            ->selectRaw('facebook_ad_connections.user_id as user_id, SUM(facebook_ad_insights.spend) as spend')
            ->groupBy('facebook_ad_connections.user_id')
            ->pluck('spend', 'user_id');

        $connections = FacebookAdConnection::query()
            ->whereIn('user_id', $fighterIds)
            ->get(['user_id', 'status'])
            ->groupBy('user_id');

        $sst = 1 + FunnelStudioReportService::SST_RATE;

        $rows = $fighters->map(function (User $fighter) use ($funnels, $funnelSales, $visitors, $manual, $spend, $connections, $sst): array {
            $own = $funnels->where('user_id', $fighter->id);
            $orders = $funnelSales->get($fighter->id, collect());
            $funnelRevenue = round((float) $orders->sum('funnel_revenue'), 2);
            $funnelOrders = $orders->where('order_type', 'main')->count();
            $manualRow = $fighter->sales_source_id ? $manual->get($fighter->sales_source_id) : null;
            $manualSales = round((float) ($manualRow->sales ?? 0), 2);
            $manualOrders = (int) ($manualRow->orders ?? 0);
            $adSpend = round((float) ($spend[$fighter->id] ?? 0), 2);
            $spendWithSst = round($adSpend * $sst, 2);
            $visitorCount = (int) ($visitors[$fighter->id] ?? 0);
            $bm = $connections->get($fighter->id, collect());

            return [
                'id' => $fighter->id,
                'name' => $fighter->name,
                'email' => $fighter->email,
                'funnels' => $own->count(),
                'published_funnels' => $own->where('status', 'published')->count(),
                'visitors' => $visitorCount,
                'funnel_orders' => $funnelOrders,
                'funnel_sales' => $funnelRevenue,
                'conversion_rate' => $visitorCount > 0 ? round($funnelOrders / $visitorCount * 100, 2) : null,
                'manual_orders' => $manualOrders,
                'manual_sales' => $manualSales,
                'total_orders' => $funnelOrders + $manualOrders,
                'total_sales' => round($funnelRevenue + $manualSales, 2),
                'spend' => $adSpend,
                'spend_with_sst' => $spendWithSst,
                'roas' => $adSpend > 0 ? round($funnelRevenue / $adSpend, 2) : null,
                'net' => round($funnelRevenue - $spendWithSst, 2),
                'bm_connected' => $bm->where('status', 'connected')->count(),
                'bm_error' => $bm->where('status', 'error')->count(),
            ];
        });

        $totalSpend = round($rows->sum('spend'), 2);
        $totalFunnelSales = round($rows->sum('funnel_sales'), 2);
        $totalSpendWithSst = round($rows->sum('spend_with_sst'), 2);

        return [
            'rows' => $rows,
            'totals' => [
                'fighters' => $rows->count(),
                'active_fighters' => $rows->filter(fn (array $r) => $r['total_orders'] > 0 || $r['spend'] > 0)->count(),
                'with_bm' => $rows->filter(fn (array $r) => $r['bm_connected'] > 0)->count(),
                'visitors' => (int) $rows->sum('visitors'),
                'funnel_sales' => $totalFunnelSales,
                'manual_sales' => round($rows->sum('manual_sales'), 2),
                'total_sales' => round($rows->sum('total_sales'), 2),
                'total_orders' => (int) $rows->sum('total_orders'),
                'spend' => $totalSpend,
                'spend_with_sst' => $totalSpendWithSst,
                'roas' => $totalSpend > 0 ? round($totalFunnelSales / $totalSpend, 2) : null,
                'net' => round($totalFunnelSales - $totalSpendWithSst, 2),
            ],
        ];
    }
}
