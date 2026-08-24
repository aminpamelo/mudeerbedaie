<?php

namespace App\Services\Reports;

use App\Models\ProductOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates e-commerce order figures for the admin dashboard, mirroring the
 * exact source-attribution and revenue rules used by the Orders & Package Sales
 * Report so the two surfaces never disagree.
 *
 * Revenue counts orders whose status is not cancelled/refunded/draft, summing
 * `total_amount`. Sources: Platform (platform_id), Agent & Co (agent_id, non
 * funnel/pos), Funnel/POS (company-owned), Fighter (funnel/pos tagged with a
 * fighter's sales source).
 */
class SalesChannelDashboard
{
    /** @var array<string, string> */
    private const SOURCE_LABELS = [
        'platform' => 'Platform',
        'agent_company' => 'Agent & Co',
        'funnel' => 'Funnel',
        'pos' => 'POS',
        'fighter' => 'Fighter',
    ];

    private const REVENUE_EXCLUDED_STATUSES = ['cancelled', 'refunded', 'draft'];

    private int $year;

    private ?array $fighterSegmentIdsCache = null;

    public function __construct(?int $year = null)
    {
        $this->year = $year ?? (int) date('Y');
    }

    public function year(): int
    {
        return $this->year;
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        $expr = $this->yearExpression('order_date');

        return DB::table('product_orders')
            ->selectRaw("DISTINCT {$expr} as year")
            ->whereNotNull('order_date')
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->map(fn ($y): int => (int) $y)
            ->values()
            ->all();
    }

    /**
     * All-time and current-period headline figures.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $allTime = $this->revenueBase()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $delivered = $this->revenueBase()->where('status', 'delivered')->count();
        $totalOrders = (int) $allTime->orders;

        $thisMonth = (float) $this->revenueBase()
            ->whereBetween('order_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_amount');

        $lastMonth = (float) $this->revenueBase()
            ->whereBetween('order_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->sum('total_amount');

        $today = (float) $this->revenueBase()
            ->whereDate('order_date', today())
            ->sum('total_amount');

        return [
            'total_revenue' => (float) $allTime->revenue,
            'total_orders' => $totalOrders,
            'avg_order_value' => $totalOrders > 0 ? (float) $allTime->revenue / $totalOrders : 0.0,
            'completion_rate' => $totalOrders > 0 ? ($delivered / $totalOrders) * 100 : 0.0,
            'this_month_revenue' => $thisMonth,
            'last_month_revenue' => $lastMonth,
            'month_growth' => $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0.0,
            'today_revenue' => $today,
            'pending_orders' => $this->pendingOrders(),
        ];
    }

    public function pendingOrders(): int
    {
        return (int) ProductOrder::query()
            ->visibleInAdmin()
            ->whereIn('status', ['pending', 'confirmed', 'processing'])
            ->count();
    }

    /**
     * Twelve months of the selected year, shaped for the Chart.js chart:
     * each entry has month_name, total_revenue, total_orders and by_source.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monthlyData(): array
    {
        $monthExpr = $this->monthExpression('order_date');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = [
                'month_name' => Carbon::create($this->year, $m, 1)->format('M'),
                'total_orders' => 0,
                'total_revenue' => 0.0,
                'by_source' => [
                    'platform' => ['orders' => 0, 'revenue' => 0.0],
                    'agent_company' => ['orders' => 0, 'revenue' => 0.0],
                    'funnel' => ['orders' => 0, 'revenue' => 0.0],
                    'pos' => ['orders' => 0, 'revenue' => 0.0],
                    'fighter' => ['orders' => 0, 'revenue' => 0.0],
                ],
            ];
        }

        $totals = ProductOrder::query()
            ->visibleInAdmin()
            ->whereRaw("{$this->yearExpression('order_date')} = ?", [$this->year])
            ->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)
            ->selectRaw("
                {$monthExpr} as month,
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue
            ")
            ->groupByRaw($monthExpr)
            ->get();

        foreach ($totals as $row) {
            $m = (int) $row->month;
            if ($m >= 1 && $m <= 12) {
                $months[$m]['total_orders'] = (int) $row->total_orders;
                $months[$m]['total_revenue'] = (float) $row->total_revenue;
            }
        }

        $fighter = $this->fighterSql();
        $notFighter = $this->notFighterSql();

        $sourceRows = DB::table('product_orders')
            ->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)
            ->whereRaw("{$this->yearExpression('order_date')} = ?", [$this->year])
            ->where(function ($q): void {
                $q->where('hidden_from_admin', false)->orWhereNull('hidden_from_admin');
            })
            ->selectRaw("
                {$monthExpr} as month,
                SUM(CASE WHEN platform_id IS NOT NULL THEN 1 ELSE 0 END) as platform_orders,
                SUM(CASE WHEN platform_id IS NOT NULL THEN total_amount ELSE 0 END) as platform_revenue,
                SUM(CASE WHEN platform_id IS NULL AND agent_id IS NOT NULL AND (source IS NULL OR source NOT IN ('funnel', 'pos')) THEN 1 ELSE 0 END) as agent_orders,
                SUM(CASE WHEN platform_id IS NULL AND agent_id IS NOT NULL AND (source IS NULL OR source NOT IN ('funnel', 'pos')) THEN total_amount ELSE 0 END) as agent_revenue,
                SUM(CASE WHEN source = 'funnel' AND {$notFighter} THEN 1 ELSE 0 END) as funnel_orders,
                SUM(CASE WHEN source = 'funnel' AND {$notFighter} THEN total_amount ELSE 0 END) as funnel_revenue,
                SUM(CASE WHEN source = 'pos' AND {$notFighter} THEN 1 ELSE 0 END) as pos_orders,
                SUM(CASE WHEN source = 'pos' AND {$notFighter} THEN total_amount ELSE 0 END) as pos_revenue,
                SUM(CASE WHEN {$fighter} THEN 1 ELSE 0 END) as fighter_orders,
                SUM(CASE WHEN {$fighter} THEN total_amount ELSE 0 END) as fighter_revenue
            ")
            ->groupByRaw($monthExpr)
            ->get();

        foreach ($sourceRows as $row) {
            $m = (int) $row->month;
            if ($m >= 1 && $m <= 12) {
                $months[$m]['by_source'] = [
                    'platform' => ['orders' => (int) $row->platform_orders, 'revenue' => (float) $row->platform_revenue],
                    'agent_company' => ['orders' => (int) $row->agent_orders, 'revenue' => (float) $row->agent_revenue],
                    'funnel' => ['orders' => (int) $row->funnel_orders, 'revenue' => (float) $row->funnel_revenue],
                    'pos' => ['orders' => (int) $row->pos_orders, 'revenue' => (float) $row->pos_revenue],
                    'fighter' => ['orders' => (int) $row->fighter_orders, 'revenue' => (float) $row->fighter_revenue],
                ];
            }
        }

        return array_values($months);
    }

    /**
     * Per-source totals for the selected year, derived from monthly data.
     *
     * @param  array<int, array<string, mixed>>|null  $monthlyData
     * @return array<int, array<string, mixed>>
     */
    public function sourceBreakdown(?array $monthlyData = null): array
    {
        $monthlyData ??= $this->monthlyData();

        $totals = [];
        foreach (array_keys(self::SOURCE_LABELS) as $key) {
            $totals[$key] = ['orders' => 0, 'revenue' => 0.0];
        }

        foreach ($monthlyData as $month) {
            foreach (array_keys(self::SOURCE_LABELS) as $key) {
                $totals[$key]['orders'] += $month['by_source'][$key]['orders'];
                $totals[$key]['revenue'] += $month['by_source'][$key]['revenue'];
            }
        }

        return collect(self::SOURCE_LABELS)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'orders' => $totals[$key]['orders'],
                'revenue' => $totals[$key]['revenue'],
            ])
            ->values()
            ->all();
    }

    /**
     * Most recent orders across every channel.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentOrders(int $limit = 6): Collection
    {
        $fighterIds = $this->fighterSegmentIds();

        return ProductOrder::query()
            ->visibleInAdmin()
            ->whereNotIn('status', ['draft'])
            ->with('customer:id,name')
            ->latest('order_date')
            ->limit($limit)
            ->get()
            ->map(fn (ProductOrder $order): array => [
                'id' => $order->id,
                'number' => $order->order_number,
                'customer' => $order->customer?->name ?? 'Guest',
                'amount' => (float) $order->total_amount,
                'status' => $order->status,
                'date' => $order->order_date,
                'source' => $this->sourceLabelFor($order, $fighterIds),
            ]);
    }

    /**
     * Top products by revenue for the selected year.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topProducts(int $limit = 5): array
    {
        $revenueExpr = 'SUM(CASE WHEN poi.total_price > 0 THEN poi.total_price ELSE (po.total_amount * 1.0 / (SELECT COUNT(*) FROM product_order_items AS sub WHERE sub.order_id = po.id)) END)';

        return DB::table('product_order_items as poi')
            ->join('product_orders as po', 'poi.order_id', '=', 'po.id')
            ->leftJoin('products as p', 'poi.product_id', '=', 'p.id')
            ->whereNotIn('po.status', self::REVENUE_EXCLUDED_STATUSES)
            ->whereRaw("{$this->yearExpression('po.order_date')} = ?", [$this->year])
            ->where(function ($q): void {
                $q->where('po.hidden_from_admin', false)->orWhereNull('po.hidden_from_admin');
            })
            ->selectRaw("
                COALESCE(p.name, poi.product_name) as name,
                COUNT(DISTINCT po.id) as order_count,
                SUM(poi.quantity_ordered) as units,
                {$revenueExpr} as revenue
            ")
            ->groupByRaw('COALESCE(p.name, poi.product_name)')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'orders' => (int) $row->order_count,
                'units' => (int) $row->units,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    // ----------------------------------------------------------------- helpers

    private function revenueBase()
    {
        return ProductOrder::query()
            ->visibleInAdmin()
            ->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES);
    }

    /**
     * @param  array<int, int>  $fighterIds
     */
    private function sourceLabelFor(ProductOrder $order, array $fighterIds): string
    {
        if ($order->platform_id !== null) {
            return self::SOURCE_LABELS['platform'];
        }

        if ($order->sales_source_id !== null && in_array((int) $order->sales_source_id, $fighterIds, true)) {
            return self::SOURCE_LABELS['fighter'];
        }

        if ($order->source === 'funnel') {
            return self::SOURCE_LABELS['funnel'];
        }

        if ($order->source === 'pos') {
            return self::SOURCE_LABELS['pos'];
        }

        if ($order->agent_id !== null) {
            return self::SOURCE_LABELS['agent_company'];
        }

        return 'Other';
    }

    /**
     * @return array<int, int>
     */
    private function fighterSegmentIds(): array
    {
        return $this->fighterSegmentIdsCache ??= User::query()
            ->where('role', 'fighter')
            ->whereNotNull('sales_source_id')
            ->pluck('sales_source_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function fighterSql(): string
    {
        $ids = $this->fighterSegmentIds();

        return empty($ids) ? '0 = 1' : 'sales_source_id IN ('.implode(',', $ids).')';
    }

    private function notFighterSql(): string
    {
        $ids = $this->fighterSegmentIds();

        return empty($ids)
            ? '1 = 1'
            : '(sales_source_id IS NULL OR sales_source_id NOT IN ('.implode(',', $ids).'))';
    }

    private function yearExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', {$column}) AS INTEGER)"
            : "YEAR({$column})";
    }

    private function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', {$column}) AS INTEGER)"
            : "MONTH({$column})";
    }
}
