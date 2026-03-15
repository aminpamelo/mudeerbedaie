<?php

use App\Models\ProductOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    public int $selectedYear;

    public string $sourceFilter = '';

    public array $availableYears = [];

    public array $monthlyData = [];

    public array $summary = [];

    public array $sourceBreakdown = [];

    public array $topProducts = [];

    public array $topProductsByRevenue = [];

    public array $topProductsByVolume = [];

    public array $productDetailTable = [];

    public array $monthlyProductData = [];

    public array $productSummary = [];

    public array $statusBreakdown = [];

    public array $topCustomers = [];

    public array $customerDetailTable = [];

    public array $monthlyCustomerData = [];

    public array $customerSummary = [];

    public int $customerPage = 1;

    public int $customerPerPage = 50;

    public int $customerTotalPages = 1;

    public string $customerSearch = '';

    /** Lightweight counts for tab badges (loaded with core data) */
    public array $tabCounts = ['products' => 0, 'status' => 0, 'customers' => 0];

    #[Url(as: 'tab')]
    public string $activeTab = 'orders';

    /** Track which tabs have been loaded to avoid re-querying */
    private array $loadedTabs = [];

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->loadActiveTabData();

        if ($tab === 'orders') {
            $this->dispatch('order-report-charts-updated', monthlyData: $this->monthlyData);
        } elseif ($tab === 'products') {
            $this->dispatch('order-report-product-charts-update', monthlyProductData: $this->monthlyProductData, topProductsByRevenue: $this->topProductsByRevenue);
        } elseif ($tab === 'status') {
            $this->dispatch('order-report-status-charts-update', statusBreakdown: $this->statusBreakdown);
        } elseif ($tab === 'customers') {
            $this->dispatch('order-report-customer-charts-update', monthlyCustomerData: $this->monthlyCustomerData, topCustomers: $this->topCustomers);
        }
    }

    private function getDriver(): string
    {
        return DB::getDriverName();
    }

    private function yearExpression(string $column): string
    {
        return $this->getDriver() === 'sqlite'
            ? "CAST(strftime('%Y', {$column}) AS INTEGER)"
            : "YEAR({$column})";
    }

    private function monthExpression(string $column): string
    {
        return $this->getDriver() === 'sqlite'
            ? "CAST(strftime('%m', {$column}) AS INTEGER)"
            : "MONTH({$column})";
    }

    public function mount(): void
    {
        $yearExpr = $this->yearExpression('order_date');

        $this->availableYears = DB::table('product_orders')
            ->selectRaw("DISTINCT {$yearExpr} as year")
            ->whereNotNull('order_date')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter()
            ->values()
            ->toArray();

        $this->selectedYear = ! empty($this->availableYears)
            ? (int) $this->availableYears[0]
            : (int) date('Y');

        $this->loadCoreData();
        $this->loadActiveTabData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadedTabs = [];
        $this->loadCoreData();
        $this->loadActiveTabData();
        $this->dispatchActiveTabCharts();
    }

    public function updatedSourceFilter(): void
    {
        $this->loadedTabs = [];
        $this->loadCoreData();
        $this->loadActiveTabData();
        $this->dispatchActiveTabCharts();
    }

    private function dispatchActiveTabCharts(): void
    {
        match ($this->activeTab) {
            'orders' => $this->dispatch('order-report-charts-updated', monthlyData: $this->monthlyData),
            'products' => $this->dispatch('order-report-product-charts-update', monthlyProductData: $this->monthlyProductData, topProductsByRevenue: $this->topProductsByRevenue),
            'status' => $this->dispatch('order-report-status-charts-update', statusBreakdown: $this->statusBreakdown),
            'customers' => $this->dispatch('order-report-customer-charts-update', monthlyCustomerData: $this->monthlyCustomerData, topCustomers: $this->topCustomers),
            default => null,
        };
    }

    private function applySourceFilter($query, string $tableAlias = 'product_orders'): void
    {
        if (! $this->sourceFilter) {
            return;
        }

        match ($this->sourceFilter) {
            'platform' => $query->whereNotNull("{$tableAlias}.platform_id"),
            'agent_company' => $query->whereNull("{$tableAlias}.platform_id")->where(function ($q) use ($tableAlias) {
                $q->whereNotIn("{$tableAlias}.source", ['funnel', 'pos'])
                    ->whereNotNull("{$tableAlias}.agent_id");
            }),
            'funnel' => $query->where("{$tableAlias}.source", 'funnel'),
            'pos' => $query->where("{$tableAlias}.source", 'pos'),
            default => null,
        };
    }

    private function applySourceFilterRaw($query, string $tableAlias = 'po'): void
    {
        if (! $this->sourceFilter) {
            return;
        }

        match ($this->sourceFilter) {
            'platform' => $query->whereNotNull("{$tableAlias}.platform_id"),
            'agent_company' => $query->whereNull("{$tableAlias}.platform_id")->where(function ($q) use ($tableAlias) {
                $q->whereNotIn("{$tableAlias}.source", ['funnel', 'pos'])
                    ->whereNotNull("{$tableAlias}.agent_id");
            }),
            'funnel' => $query->where("{$tableAlias}.source", 'funnel'),
            'pos' => $query->where("{$tableAlias}.source", 'pos'),
            default => null,
        };
    }

    private function applyVisibleInAdminRaw($query, string $tableAlias = 'po'): void
    {
        $query->where(function ($q) use ($tableAlias) {
            $q->where("{$tableAlias}.hidden_from_admin", false)
                ->orWhereNull("{$tableAlias}.hidden_from_admin");
        });
    }

    /** Load only the data needed for summary cards, source breakdown, and monthly overview (always needed) */
    private function loadCoreData(): void
    {
        $yearExpr = $this->yearExpression('order_date');
        $monthExpr = $this->monthExpression('order_date');

        // Initialize monthly data structure
        $this->monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $this->monthlyData[$month] = [
                'month_name' => Carbon::create($this->selectedYear, $month, 1)->format('F'),
                'month_number' => $month,
                'total_orders' => 0,
                'completed_orders' => 0,
                'pending_orders' => 0,
                'cancelled_orders' => 0,
                'total_revenue' => 0,
                'avg_order_value' => 0,
                'by_source' => [
                    'platform' => ['orders' => 0, 'revenue' => 0],
                    'agent_company' => ['orders' => 0, 'revenue' => 0],
                    'funnel' => ['orders' => 0, 'revenue' => 0],
                    'pos' => ['orders' => 0, 'revenue' => 0],
                ],
            ];
        }

        $baseQuery = ProductOrder::query()
            ->visibleInAdmin()
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear]);

        $this->applySourceFilter($baseQuery);

        // Monthly orders — single query
        $monthlyOrders = (clone $baseQuery)
            ->selectRaw("
                {$monthExpr} as month,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status IN ('pending', 'confirmed', 'processing', 'shipped') THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status IN ('cancelled', 'refunded', 'returned') THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN status NOT IN ('cancelled', 'refunded', 'draft') THEN total_amount ELSE 0 END) as total_revenue
            ")
            ->groupByRaw($monthExpr)
            ->get();

        foreach ($monthlyOrders as $data) {
            $month = (int) $data->month;
            if ($month >= 1 && $month <= 12) {
                $this->monthlyData[$month]['total_orders'] = (int) $data->total_orders;
                $this->monthlyData[$month]['completed_orders'] = (int) $data->completed_orders;
                $this->monthlyData[$month]['pending_orders'] = (int) $data->pending_orders;
                $this->monthlyData[$month]['cancelled_orders'] = (int) $data->cancelled_orders;
                $this->monthlyData[$month]['total_revenue'] = (float) $data->total_revenue;
                $this->monthlyData[$month]['avg_order_value'] = $data->total_orders > 0
                    ? (float) $data->total_revenue / $data->total_orders
                    : 0;
            }
        }

        // Source breakdown per month — single consolidated query instead of 4 separate queries
        $sourceQuery = DB::table('product_orders')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('status', ['cancelled', 'refunded', 'draft']);

        $this->applyVisibleInAdminRaw($sourceQuery, 'product_orders');

        $sourceRows = $sourceQuery->selectRaw("
                {$monthExpr} as month,
                SUM(CASE WHEN platform_id IS NOT NULL THEN 1 ELSE 0 END) as platform_orders,
                SUM(CASE WHEN platform_id IS NOT NULL THEN total_amount ELSE 0 END) as platform_revenue,
                SUM(CASE WHEN platform_id IS NULL AND agent_id IS NOT NULL AND (source IS NULL OR source NOT IN ('funnel', 'pos')) THEN 1 ELSE 0 END) as agent_orders,
                SUM(CASE WHEN platform_id IS NULL AND agent_id IS NOT NULL AND (source IS NULL OR source NOT IN ('funnel', 'pos')) THEN total_amount ELSE 0 END) as agent_revenue,
                SUM(CASE WHEN source = 'funnel' THEN 1 ELSE 0 END) as funnel_orders,
                SUM(CASE WHEN source = 'funnel' THEN total_amount ELSE 0 END) as funnel_revenue,
                SUM(CASE WHEN source = 'pos' THEN 1 ELSE 0 END) as pos_orders,
                SUM(CASE WHEN source = 'pos' THEN total_amount ELSE 0 END) as pos_revenue
            ")
            ->groupByRaw($monthExpr)
            ->get();

        foreach ($sourceRows as $row) {
            $month = (int) $row->month;
            if ($month >= 1 && $month <= 12) {
                $this->monthlyData[$month]['by_source'] = [
                    'platform' => ['orders' => (int) $row->platform_orders, 'revenue' => (float) $row->platform_revenue],
                    'agent_company' => ['orders' => (int) $row->agent_orders, 'revenue' => (float) $row->agent_revenue],
                    'funnel' => ['orders' => (int) $row->funnel_orders, 'revenue' => (float) $row->funnel_revenue],
                    'pos' => ['orders' => (int) $row->pos_orders, 'revenue' => (float) $row->pos_revenue],
                ];
            }
        }

        // Calculate summary from monthly data (no extra queries)
        $totalOrders = $completedOrders = $pendingOrders = $cancelledOrders = 0;
        $totalRevenue = 0;

        foreach ($this->monthlyData as $data) {
            $totalOrders += $data['total_orders'];
            $completedOrders += $data['completed_orders'];
            $pendingOrders += $data['pending_orders'];
            $cancelledOrders += $data['cancelled_orders'];
            $totalRevenue += $data['total_revenue'];
        }

        $this->summary = [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'pending_orders' => $pendingOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_revenue' => $totalRevenue,
            'avg_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'completion_rate' => $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0,
        ];

        // Derive source breakdown totals from monthly data (no extra queries)
        $this->sourceBreakdown = [
            'platform' => ['orders' => 0, 'revenue' => 0],
            'agent_company' => ['orders' => 0, 'revenue' => 0],
            'funnel' => ['orders' => 0, 'revenue' => 0],
            'pos' => ['orders' => 0, 'revenue' => 0],
        ];

        foreach ($this->monthlyData as $data) {
            foreach (['platform', 'agent_company', 'funnel', 'pos'] as $source) {
                $this->sourceBreakdown[$source]['orders'] += $data['by_source'][$source]['orders'];
                $this->sourceBreakdown[$source]['revenue'] += $data['by_source'][$source]['revenue'];
            }
        }

        // Lightweight badge counts — 3 fast COUNT queries
        $countBase = ProductOrder::query()
            ->visibleInAdmin()
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('status', ['cancelled', 'refunded', 'draft']);

        $this->applySourceFilter($countBase);

        $productCount = DB::table('product_order_items as poi')
            ->join('product_orders as po', 'poi.order_id', '=', 'po.id')
            ->whereRaw("{$this->yearExpression('po.order_date')} = ?", [$this->selectedYear])
            ->whereNotIn('po.status', ['cancelled', 'refunded', 'draft']);
        $this->applyVisibleInAdminRaw($productCount);
        $this->applySourceFilterRaw($productCount);

        $statusCount = ProductOrder::query()
            ->visibleInAdmin()
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear]);
        $this->applySourceFilter($statusCount);

        $customerCount = (clone $countBase);

        $this->tabCounts = [
            'products' => (int) $productCount->selectRaw('COUNT(DISTINCT COALESCE(poi.product_id, poi.product_name)) as cnt')->value('cnt'),
            'status' => (int) $statusCount->selectRaw('COUNT(DISTINCT status) as cnt')->value('cnt'),
            'customers' => (int) $customerCount->selectRaw('COUNT(DISTINCT COALESCE(customer_id, id)) as cnt')->value('cnt'),
        ];
    }

    /** Load data only for the currently active tab */
    private function loadActiveTabData(): void
    {
        $tab = $this->activeTab;

        if (in_array($tab, $this->loadedTabs)) {
            return;
        }

        match ($tab) {
            'products' => $this->loadTopProducts(),
            'status' => $this->loadStatusBreakdown(),
            'customers' => $this->loadTopCustomers(),
            default => null, // 'orders' tab uses core data only
        };

        $this->loadedTabs[] = $tab;
    }

    private function loadTopProducts(): void
    {
        $yearExpr = $this->yearExpression('po.order_date');
        $monthExpr = $this->monthExpression('po.order_date');

        $query = DB::table('product_order_items as poi')
            ->join('product_orders as po', 'poi.order_id', '=', 'po.id')
            ->leftJoin('products as p', 'poi.product_id', '=', 'p.id')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('po.status', ['cancelled', 'refunded', 'draft']);

        $this->applyVisibleInAdminRaw($query);
        $this->applySourceFilterRaw($query);

        // When poi.total_price is 0 (common in platform imports), fall back to
        // po.total_amount divided by the number of items in that order
        $revenueExpr = "SUM(CASE WHEN poi.total_price > 0 THEN poi.total_price ELSE (po.total_amount * 1.0 / (SELECT COUNT(*) FROM product_order_items AS sub WHERE sub.order_id = po.id)) END)";

        $allData = $query->select([
            DB::raw('COALESCE(p.id, 0) as product_id'),
            DB::raw('COALESCE(p.name, poi.product_name) as product_name'),
            'p.sku',
            DB::raw("{$monthExpr} as month"),
            DB::raw('COUNT(DISTINCT po.id) as order_count'),
            DB::raw('SUM(poi.quantity_ordered) as total_quantity'),
            DB::raw("{$revenueExpr} as total_revenue"),
        ])
            ->groupByRaw("COALESCE(p.id, 0), COALESCE(p.name, poi.product_name), p.sku, {$monthExpr}")
            ->get();

        $productTotals = [];
        $productMonthly = [];

        $monthlyAgg = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyAgg[$m] = ['units_sold' => 0, 'revenue' => 0];
        }

        foreach ($allData as $row) {
            $key = $row->product_id . '|' . $row->product_name;

            if (! isset($productTotals[$key])) {
                $productTotals[$key] = [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'sku' => $row->sku,
                    'order_count' => 0,
                    'total_quantity' => 0,
                    'total_revenue' => 0,
                ];

                for ($m = 1; $m <= 12; $m++) {
                    $productMonthly[$key][$m] = ['quantity' => 0, 'revenue' => 0];
                }
            }

            $productTotals[$key]['order_count'] += (int) $row->order_count;
            $productTotals[$key]['total_quantity'] += (int) $row->total_quantity;
            $productTotals[$key]['total_revenue'] += (float) $row->total_revenue;

            $month = (int) $row->month;
            if ($month >= 1 && $month <= 12) {
                $productMonthly[$key][$month] = [
                    'quantity' => (int) $row->total_quantity,
                    'revenue' => (float) $row->total_revenue,
                ];

                $monthlyAgg[$month]['units_sold'] += (int) $row->total_quantity;
                $monthlyAgg[$month]['revenue'] += (float) $row->total_revenue;
            }
        }

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $this->monthlyProductData = [];
        for ($m = 1; $m <= 12; $m++) {
            $this->monthlyProductData[] = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'units_sold' => $monthlyAgg[$m]['units_sold'],
                'revenue' => round($monthlyAgg[$m]['revenue'], 2),
            ];
        }

        uasort($productTotals, fn ($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        $allProducts = [];
        foreach ($productTotals as $key => $product) {
            $product['monthly'] = $productMonthly[$key];
            $product['avg_price'] = $product['total_quantity'] > 0
                ? round($product['total_revenue'] / $product['total_quantity'], 2)
                : 0;
            $allProducts[] = $product;
        }

        $this->topProducts = array_slice($allProducts, 0, 10);
        $this->topProductsByRevenue = array_slice($allProducts, 0, 10);
        $this->productDetailTable = $allProducts;

        $byVolume = $allProducts;
        usort($byVolume, fn ($a, $b) => $b['total_quantity'] <=> $a['total_quantity']);
        $this->topProductsByVolume = array_slice($byVolume, 0, 10);

        $uniqueProducts = count($productTotals);
        $totalUnits = (int) collect($productTotals)->sum('total_quantity');
        $totalRevenue = (float) collect($productTotals)->sum('total_revenue');

        $this->productSummary = [
            'unique_products' => $uniqueProducts,
            'total_units' => $totalUnits,
            'total_revenue' => $totalRevenue,
            'avg_revenue_per_product' => $uniqueProducts > 0 ? round($totalRevenue / $uniqueProducts, 2) : 0,
        ];
    }

    private function loadStatusBreakdown(): void
    {
        $yearExpr = $this->yearExpression('order_date');

        $query = ProductOrder::query()
            ->visibleInAdmin()
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear]);

        $this->applySourceFilter($query);

        $statuses = $query
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('status')
            ->get();

        $this->statusBreakdown = [];
        $totalOrders = $statuses->sum('count');

        foreach ($statuses as $status) {
            $this->statusBreakdown[] = [
                'status' => $status->status,
                'count' => (int) $status->count,
                'revenue' => (float) $status->revenue,
                'percentage' => $totalOrders > 0 ? round(((int) $status->count / $totalOrders) * 100, 1) : 0,
            ];
        }

        usort($this->statusBreakdown, fn ($a, $b) => $b['count'] <=> $a['count']);
    }

    public function updatedCustomerSearch(): void
    {
        $this->customerPage = 1;
        $this->loadCustomerDetailPage();
    }

    public function setCustomerPage(int $page): void
    {
        $this->customerPage = max(1, min($page, $this->customerTotalPages));
        $this->loadCustomerDetailPage();
    }

    private function buildCustomerBaseQuery()
    {
        $yearExpr = $this->yearExpression('po.order_date');

        $query = DB::table('product_orders as po')
            ->leftJoin('users as u', 'po.customer_id', '=', 'u.id')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('po.status', ['cancelled', 'refunded', 'draft']);

        $this->applyVisibleInAdminRaw($query);
        $this->applySourceFilterRaw($query);

        return $query;
    }

    private function loadTopCustomers(): void
    {
        $monthExpr = $this->monthExpression('po.order_date');
        $query = $this->buildCustomerBaseQuery();

        // Top 10 customers only (for charts and leaderboard)
        $topCustomersQuery = (clone $query)->select([
            DB::raw("COALESCE(po.customer_id, 0) as customer_id"),
            DB::raw("COALESCE(u.name, po.customer_name, 'Guest Customer') as customer_name"),
            DB::raw("u.email as customer_email"),
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(po.total_amount) as total_revenue'),
            DB::raw('AVG(po.total_amount) as avg_order_value'),
            DB::raw("SUM(CASE WHEN po.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders"),
        ])
            ->groupByRaw("COALESCE(po.customer_id, 0), COALESCE(u.name, po.customer_name, 'Guest Customer'), u.email")
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $maxRevenue = $topCustomersQuery->isNotEmpty() ? (float) $topCustomersQuery->first()->total_revenue : 0;

        $this->topCustomers = [];
        foreach ($topCustomersQuery as $customer) {
            $this->topCustomers[] = [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->customer_name,
                'customer_email' => $customer->customer_email,
                'total_orders' => (int) $customer->total_orders,
                'total_revenue' => (float) $customer->total_revenue,
                'avg_order_value' => (float) $customer->avg_order_value,
                'completed_orders' => (int) $customer->completed_orders,
                'revenue_percentage' => $maxRevenue > 0 ? ((float) $customer->total_revenue / $maxRevenue) * 100 : 0,
            ];
        }

        // Monthly customer data (lightweight aggregation)
        $monthlyCustomers = (clone $query)->select([
            DB::raw("{$monthExpr} as month"),
            DB::raw('COUNT(DISTINCT COALESCE(po.customer_id, po.id)) as unique_customers'),
            DB::raw('SUM(po.total_amount) as revenue'),
        ])
            ->groupByRaw($monthExpr)
            ->get();

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $this->monthlyCustomerData = [];

        for ($m = 1; $m <= 12; $m++) {
            $this->monthlyCustomerData[] = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'unique_customers' => 0,
                'revenue' => 0,
            ];
        }

        foreach ($monthlyCustomers as $row) {
            $month = (int) $row->month;
            if ($month >= 1 && $month <= 12) {
                $this->monthlyCustomerData[$month - 1]['unique_customers'] = (int) $row->unique_customers;
                $this->monthlyCustomerData[$month - 1]['revenue'] = (float) $row->revenue;
            }
        }

        // Summary via aggregate query (no need to load all rows)
        $summaryRow = (clone $query)->selectRaw("
            COUNT(DISTINCT COALESCE(po.customer_id, po.id)) as total_customers,
            SUM(po.total_amount) as total_revenue
        ")->first();

        $totalCustomers = (int) $summaryRow->total_customers;
        $totalCustomerRevenue = (float) $summaryRow->total_revenue;

        $this->customerSummary = [
            'total_customers' => $totalCustomers,
            'total_revenue' => $totalCustomerRevenue,
            'avg_revenue_per_customer' => $totalCustomers > 0 ? round($totalCustomerRevenue / $totalCustomers, 2) : 0,
            'top_customer_revenue' => $maxRevenue,
        ];

        // Load first page of the detail table
        $this->customerPage = 1;
        $this->loadCustomerDetailPage();
    }

    private function loadCustomerDetailPage(): void
    {
        $query = $this->buildCustomerBaseQuery();

        $baseSelect = (clone $query)->select([
            DB::raw("COALESCE(po.customer_id, 0) as customer_id"),
            DB::raw("COALESCE(u.name, po.customer_name, 'Guest Customer') as customer_name"),
            DB::raw("u.email as customer_email"),
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(po.total_amount) as total_revenue'),
            DB::raw('AVG(po.total_amount) as avg_order_value'),
            DB::raw("SUM(CASE WHEN po.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders"),
        ])
            ->groupByRaw("COALESCE(po.customer_id, 0), COALESCE(u.name, po.customer_name, 'Guest Customer'), u.email");

        // Apply search filter
        if ($this->customerSearch) {
            $search = $this->customerSearch;
            $baseSelect->having(DB::raw("COALESCE(u.name, po.customer_name, 'Guest Customer')"), 'like', "%{$search}%");
        }

        // Get total count for pagination
        $countQuery = DB::query()->fromSub($baseSelect, 'customers_sub');
        $totalRows = $countQuery->count();
        $this->customerTotalPages = max(1, (int) ceil($totalRows / $this->customerPerPage));
        $this->customerPage = min($this->customerPage, $this->customerTotalPages);

        // Paginated results
        $offset = ($this->customerPage - 1) * $this->customerPerPage;
        $customers = (clone $baseSelect)
            ->orderByDesc('total_revenue')
            ->offset($offset)
            ->limit($this->customerPerPage)
            ->get();

        $maxRevenue = $this->topCustomers[0]['total_revenue'] ?? 0;

        $this->customerDetailTable = [];
        foreach ($customers as $customer) {
            $this->customerDetailTable[] = [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->customer_name,
                'customer_email' => $customer->customer_email,
                'total_orders' => (int) $customer->total_orders,
                'total_revenue' => (float) $customer->total_revenue,
                'avg_order_value' => (float) $customer->avg_order_value,
                'completed_orders' => (int) $customer->completed_orders,
                'revenue_percentage' => $maxRevenue > 0 ? ((float) $customer->total_revenue / $maxRevenue) * 100 : 0,
            ];
        }
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = "order-report-{$this->selectedYear}.csv";

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Month',
                'Total Orders',
                'Completed',
                'Pending',
                'Cancelled',
                'Total Revenue (RM)',
                'Avg Order Value (RM)',
                'Platform Orders',
                'Platform Revenue',
                'Agent & Co Orders',
                'Agent & Co Revenue',
                'Funnel Orders',
                'Funnel Revenue',
                'POS Orders',
                'POS Revenue',
            ]);

            foreach ($this->monthlyData as $data) {
                fputcsv($handle, [
                    $data['month_name'],
                    $data['total_orders'],
                    $data['completed_orders'],
                    $data['pending_orders'],
                    $data['cancelled_orders'],
                    number_format($data['total_revenue'], 2),
                    number_format($data['avg_order_value'], 2),
                    $data['by_source']['platform']['orders'],
                    number_format($data['by_source']['platform']['revenue'], 2),
                    $data['by_source']['agent_company']['orders'],
                    number_format($data['by_source']['agent_company']['revenue'], 2),
                    $data['by_source']['funnel']['orders'],
                    number_format($data['by_source']['funnel']['revenue'], 2),
                    $data['by_source']['pos']['orders'],
                    number_format($data['by_source']['pos']['revenue'], 2),
                ]);
            }

            // Total row
            fputcsv($handle, [
                'TOTAL',
                $this->summary['total_orders'],
                $this->summary['completed_orders'],
                $this->summary['pending_orders'],
                $this->summary['cancelled_orders'],
                number_format($this->summary['total_revenue'], 2),
                number_format($this->summary['avg_order_value'], 2),
                $this->sourceBreakdown['platform']['orders'],
                number_format($this->sourceBreakdown['platform']['revenue'], 2),
                $this->sourceBreakdown['agent_company']['orders'],
                number_format($this->sourceBreakdown['agent_company']['revenue'], 2),
                $this->sourceBreakdown['funnel']['orders'],
                number_format($this->sourceBreakdown['funnel']['revenue'], 2),
                $this->sourceBreakdown['pos']['orders'],
                number_format($this->sourceBreakdown['pos']['revenue'], 2),
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}; ?>

<div>
    <div class="space-y-6 p-6 lg:p-8">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Orders & Package Sales Report</flux:heading>
                <flux:text class="mt-2">Comprehensive sales report across all order sources</flux:text>
            </div>
            <flux:button wire:click="exportCsv" variant="outline">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="mr-1 h-4 w-4" />
                    Export CSV
                </div>
            </flux:button>
        </div>

        {{-- Filters --}}
        <div class="mb-6 flex flex-wrap items-center gap-4">
            <div class="w-48">
                <flux:select wire:model.live="sourceFilter" placeholder="All Sources">
                    <flux:select.option value="">All Sources</flux:select.option>
                    <flux:select.option value="platform">Platform</flux:select.option>
                    <flux:select.option value="agent_company">Agent & Co</flux:select.option>
                    <flux:select.option value="funnel">Funnel</flux:select.option>
                    <flux:select.option value="pos">POS</flux:select.option>
                </flux:select>
            </div>
            <div class="w-32">
                <flux:select wire:model.live="selectedYear">
                    @foreach($availableYears as $year)
                        <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text>Total Revenue</flux:text>
                        <flux:heading size="xl">RM {{ number_format($summary['total_revenue'] ?? 0, 2) }}</flux:heading>
                    </div>
                    <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                        <flux:icon name="currency-dollar" class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text>Total Orders</flux:text>
                        <flux:heading size="xl">{{ number_format($summary['total_orders'] ?? 0) }}</flux:heading>
                        <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ $summary['completed_orders'] ?? 0 }} completed</flux:text>
                    </div>
                    <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <flux:icon name="shopping-bag" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text>Avg Order Value</flux:text>
                        <flux:heading size="xl">RM {{ number_format($summary['avg_order_value'] ?? 0, 2) }}</flux:heading>
                    </div>
                    <div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                        <flux:icon name="calculator" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text>Completion Rate</flux:text>
                        <flux:heading size="xl">{{ number_format($summary['completion_rate'] ?? 0, 1) }}%</flux:heading>
                    </div>
                    <div class="rounded-lg bg-yellow-100 p-2 dark:bg-yellow-900/30">
                        <flux:icon name="check-circle" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Source Breakdown Cards --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $sourceLabels = [
                    'platform' => ['label' => 'Platform', 'icon' => 'globe-alt', 'color' => 'orange'],
                    'agent_company' => ['label' => 'Agent & Co', 'icon' => 'building-office', 'color' => 'blue'],
                    'funnel' => ['label' => 'Funnel', 'icon' => 'funnel', 'color' => 'purple'],
                    'pos' => ['label' => 'POS', 'icon' => 'computer-desktop', 'color' => 'pink'],
                ];
            @endphp
            @foreach($sourceLabels as $key => $source)
                <flux:card class="space-y-1 border-l-4 border-{{ $source['color'] }}-500">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ $source['label'] }}</flux:heading>
                        <flux:icon name="{{ $source['icon'] }}" class="h-5 w-5 text-{{ $source['color'] }}-500" />
                    </div>
                    <flux:heading size="lg">{{ number_format($sourceBreakdown[$key]['orders'] ?? 0) }} orders</flux:heading>
                    <flux:text class="text-sm text-{{ $source['color'] }}-600 dark:text-{{ $source['color'] }}-400">RM {{ number_format($sourceBreakdown[$key]['revenue'] ?? 0, 2) }}</flux:text>
                </flux:card>
            @endforeach
        </div>

        {{-- Tab Navigation --}}
        <div class="mb-6 flex gap-2 border-b border-gray-200 dark:border-zinc-700">
            @php
                $tabs = [
                    'orders' => ['label' => 'Orders Overview', 'icon' => 'chart-bar'],
                    'products' => ['label' => 'Product Insights', 'count' => $tabCounts['products'] ?? 0, 'icon' => 'cube'],
                    'status' => ['label' => 'Order Status', 'count' => $tabCounts['status'] ?? 0, 'icon' => 'clipboard-document-list'],
                    'customers' => ['label' => 'Customer Insights', 'count' => $tabCounts['customers'] ?? 0, 'icon' => 'users'],
                ];
            @endphp
            @foreach($tabs as $tabKey => $tab)
                <button
                    wire:click="setActiveTab('{{ $tabKey }}')"
                    class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors {{ $activeTab === $tabKey ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                >
                    <flux:icon name="{{ $tab['icon'] }}" class="h-4 w-4" />
                    {{ $tab['label'] }}
                    @if(isset($tab['count']))
                        <flux:badge size="sm" color="{{ $activeTab === $tabKey ? 'blue' : 'zinc' }}">{{ $tab['count'] }}</flux:badge>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Orders Overview Tab --}}
        @if($activeTab === 'orders')
        <div class="space-y-6">
            {{-- Charts --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Monthly Revenue Trend</flux:heading>
                        <flux:text>Revenue and order count by month</flux:text>
                    </div>
                    <div style="height: 300px;" class="relative">
                        <div id="orderReportRevenueSkeleton" class="absolute inset-0 flex items-center justify-center">
                            <flux:icon name="arrow-path" class="h-8 w-8 animate-spin text-gray-300 dark:text-zinc-600" />
                        </div>
                        <canvas id="orderReportRevenueTrendChart" class="opacity-0"></canvas>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Orders by Source</flux:heading>
                        <flux:text>Order comparison between sources</flux:text>
                    </div>
                    <div style="height: 300px;" class="relative">
                        <div id="orderReportSourceSkeleton" class="absolute inset-0 flex items-center justify-center">
                            <flux:icon name="arrow-path" class="h-8 w-8 animate-spin text-gray-300 dark:text-zinc-600" />
                        </div>
                        <canvas id="orderReportBySourceChart" class="opacity-0"></canvas>
                    </div>
                </flux:card>
            </div>

            {{-- Monthly Data Table --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Monthly Breakdown</flux:heading>
                    <flux:text>Detailed month-by-month order data for {{ $selectedYear }}</flux:text>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-zinc-800">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Month</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Orders</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Completed</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Pending</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Cancelled</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Revenue (RM)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Avg Order (RM)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                            @foreach($monthlyData as $month => $data)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800" wire:key="monthly-{{ $month }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $data['month_name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-zinc-100">{{ number_format($data['total_orders']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-green-600 dark:text-green-400">{{ number_format($data['completed_orders']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-yellow-600 dark:text-yellow-400">{{ number_format($data['pending_orders']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-red-600 dark:text-red-400">{{ number_format($data['cancelled_orders']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($data['total_revenue'], 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">RM {{ number_format($data['avg_order_value'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-300 bg-gray-50 dark:border-zinc-600 dark:bg-zinc-800">
                            <tr>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-zinc-100">Total</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">{{ number_format($summary['total_orders']) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-green-600 dark:text-green-400">{{ number_format($summary['completed_orders']) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($summary['pending_orders']) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-red-600 dark:text-red-400">{{ number_format($summary['cancelled_orders']) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">RM {{ number_format($summary['total_revenue'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">RM {{ number_format($summary['avg_order_value'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </flux:card>
        </div>
        @endif

        {{-- Product Insights Tab --}}
        @if($activeTab === 'products')
        <div class="space-y-6">
            {{-- Product Summary Cards --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ number_format($productSummary['unique_products'] ?? 0) }}</flux:heading>
                        <div class="rounded-lg bg-indigo-100 p-2 dark:bg-indigo-900/30">
                            <flux:icon name="cube" class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                        </div>
                    </div>
                    <flux:text>Unique Products</flux:text>
                </flux:card>
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ number_format($productSummary['total_units'] ?? 0) }}</flux:heading>
                        <div class="rounded-lg bg-teal-100 p-2 dark:bg-teal-900/30">
                            <flux:icon name="archive-box" class="h-6 w-6 text-teal-600 dark:text-teal-400" />
                        </div>
                    </div>
                    <flux:text>Total Units Sold</flux:text>
                </flux:card>
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">RM {{ number_format($productSummary['total_revenue'] ?? 0, 2) }}</flux:heading>
                        <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                            <flux:icon name="banknotes" class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                    <flux:text>Product Revenue</flux:text>
                </flux:card>
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">RM {{ number_format($productSummary['avg_revenue_per_product'] ?? 0, 2) }}</flux:heading>
                        <div class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                            <flux:icon name="chart-bar" class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                        </div>
                    </div>
                    <flux:text>Avg Revenue / Product</flux:text>
                </flux:card>
            </div>

            {{-- Product Charts --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Product Sales Trend</flux:heading>
                        <flux:text>Monthly revenue and units sold</flux:text>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="orderReportProductTrendChart"></canvas>
                    </div>
                </flux:card>
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Top Products by Revenue</flux:heading>
                        <flux:text>Top 10 products ranked by revenue</flux:text>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="orderReportProductBarChart"></canvas>
                    </div>
                </flux:card>
            </div>

            {{-- Top Products --}}
            @if(count($topProductsByRevenue) > 0)
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Top Products by Revenue</flux:heading>
                        <flux:text>Ranked by total revenue</flux:text>
                    </div>
                    <div class="space-y-3">
                        @foreach($topProductsByRevenue as $index => $product)
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-zinc-700" wire:key="top-rev-{{ $index }}">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="min-w-0">
                                        <flux:heading size="sm" class="truncate">{{ $product['product_name'] }}</flux:heading>
                                        <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ number_format($product['total_quantity']) }} units &bull; {{ $product['order_count'] }} orders</flux:text>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <flux:heading size="sm">RM {{ number_format($product['total_revenue'], 2) }}</flux:heading>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Top Products by Volume</flux:heading>
                        <flux:text>Ranked by units sold</flux:text>
                    </div>
                    <div class="space-y-3">
                        @foreach($topProductsByVolume as $index => $product)
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-zinc-700" wire:key="top-vol-{{ $index }}">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-100 text-sm font-semibold text-teal-600 dark:bg-teal-900/30 dark:text-teal-400">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="min-w-0">
                                        <flux:heading size="sm" class="truncate">{{ $product['product_name'] }}</flux:heading>
                                        <flux:text class="text-xs text-gray-500 dark:text-zinc-400">RM {{ number_format($product['total_revenue'], 2) }} revenue</flux:text>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <flux:heading size="sm">{{ number_format($product['total_quantity']) }} units</flux:heading>
                                    <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ $product['order_count'] }} orders</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            </div>
            @endif

            {{-- Product Detail Table --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Product Sales Detail</flux:heading>
                    <flux:text>Complete breakdown of all products sold in {{ $selectedYear }}</flux:text>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-zinc-800">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">SKU</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Units Sold</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Orders</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Avg Price</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                            @forelse($productDetailTable as $index => $product)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800" wire:key="product-detail-{{ $index }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $product['product_name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ $product['sku'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-zinc-100">{{ number_format($product['total_quantity']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">{{ number_format($product['order_count']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">RM {{ number_format($product['avg_price'] ?? 0, 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($product['total_revenue'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-zinc-400">
                                        No product data found for {{ $selectedYear }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(count($productDetailTable) > 0)
                            <tfoot class="border-t-2 border-gray-300 bg-gray-50 dark:border-zinc-600 dark:bg-zinc-800">
                                <tr>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-zinc-100">Total</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">{{ number_format(collect($productDetailTable)->sum('total_quantity')) }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">{{ number_format(collect($productDetailTable)->sum('order_count')) }}</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">RM {{ number_format(collect($productDetailTable)->sum('total_revenue'), 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </flux:card>
        </div>
        @endif

        {{-- Order Status Tab --}}
        @if($activeTab === 'status')
        <div class="space-y-6">
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Order Status Distribution</flux:heading>
                    <flux:text>Breakdown of all orders by status in {{ $selectedYear }}</flux:text>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $statusColors = [
                            'pending' => 'yellow',
                            'confirmed' => 'blue',
                            'processing' => 'indigo',
                            'shipped' => 'cyan',
                            'delivered' => 'green',
                            'cancelled' => 'red',
                            'refunded' => 'orange',
                            'returned' => 'rose',
                            'draft' => 'gray',
                        ];
                    @endphp
                    @foreach($statusBreakdown as $index => $status)
                        @php
                            $color = $statusColors[$status['status']] ?? 'gray';
                        @endphp
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-zinc-700" wire:key="status-{{ $index }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="h-3 w-3 rounded-full bg-{{ $color }}-500"></div>
                                    <flux:heading size="sm">{{ ucfirst($status['status']) }}</flux:heading>
                                </div>
                                <flux:badge size="sm" color="{{ $color }}">{{ $status['percentage'] }}%</flux:badge>
                            </div>
                            <div class="mt-2">
                                <flux:heading size="lg">{{ number_format($status['count']) }}</flux:heading>
                                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">RM {{ number_format($status['revenue'], 2) }}</flux:text>
                            </div>
                            <div class="mt-2 h-2 w-full rounded-full bg-gray-100 dark:bg-zinc-700">
                                <div class="h-2 rounded-full bg-{{ $color }}-500 transition-all" style="width: {{ min($status['percentage'], 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>

            {{-- Status Summary Table --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Status Summary Table</flux:heading>
                    <flux:text>Detailed status breakdown with revenue</flux:text>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-zinc-800">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Orders</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Percentage</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Revenue (RM)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                            @foreach($statusBreakdown as $index => $status)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800" wire:key="status-table-{{ $index }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-zinc-100">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2.5 w-2.5 rounded-full bg-{{ $statusColors[$status['status']] ?? 'gray' }}-500"></div>
                                            {{ ucfirst($status['status']) }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-zinc-100">{{ number_format($status['count']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">{{ $status['percentage'] }}%</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($status['revenue'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-300 bg-gray-50 dark:border-zinc-600 dark:bg-zinc-800">
                            <tr>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-zinc-100">Total</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">{{ number_format(collect($statusBreakdown)->sum('count')) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">100%</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">RM {{ number_format(collect($statusBreakdown)->sum('revenue'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </flux:card>
        </div>
        @endif

        {{-- Customer Insights Tab --}}
        @if($activeTab === 'customers')
        <div class="space-y-6">
            {{-- Customer Summary Cards --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ number_format($customerSummary['total_customers'] ?? 0) }}</flux:heading>
                        <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                            <flux:icon name="users" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                    <flux:text>Total Customers</flux:text>
                    <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Customers with orders in {{ $selectedYear }}</flux:text>
                </flux:card>

                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">RM {{ number_format($customerSummary['total_revenue'] ?? 0, 2) }}</flux:heading>
                        <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                            <flux:icon name="banknotes" class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                    <flux:text>Total Revenue</flux:text>
                    <flux:text class="text-xs text-gray-500 dark:text-zinc-400">From all customers combined</flux:text>
                </flux:card>

                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">RM {{ number_format($customerSummary['avg_revenue_per_customer'] ?? 0, 2) }}</flux:heading>
                        <div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                            <flux:icon name="calculator" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                    <flux:text>Avg Revenue / Customer</flux:text>
                    <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Average per active customer</flux:text>
                </flux:card>

                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">RM {{ number_format($customerSummary['top_customer_revenue'] ?? 0, 2) }}</flux:heading>
                        <div class="rounded-lg bg-yellow-100 p-2 dark:bg-yellow-900/30">
                            <flux:icon name="star" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                    </div>
                    <flux:text>Top Customer Revenue</flux:text>
                    <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Highest spending customer</flux:text>
                </flux:card>
            </div>

            {{-- Customer Charts --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Monthly Customer Activity</flux:heading>
                        <flux:text>Unique customers per month</flux:text>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="orderReportCustomerTrendChart"></canvas>
                    </div>
                </flux:card>
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Top Customers by Revenue</flux:heading>
                        <flux:text>Top 10 customers ranked by revenue</flux:text>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="orderReportCustomerBarChart"></canvas>
                    </div>
                </flux:card>
            </div>

            {{-- Top Customers Leaderboard --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Top Customers</flux:heading>
                    <flux:text class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Leaderboard ranked by revenue in {{ $selectedYear }}</flux:text>
                </div>
                @if(count($topCustomers) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-zinc-700">
                                    <th class="w-10 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Rank</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Customer</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Orders</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Revenue (RM)</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Avg Order (RM)</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Completed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                                @foreach($topCustomers as $index => $customer)
                                    <tr wire:key="top-customer-{{ $index }}" class="hover:bg-gray-50 dark:hover:bg-zinc-800 {{ $index === 0 ? 'bg-amber-50/40 dark:bg-amber-900/10' : ($index === 1 ? 'bg-gray-50/40 dark:bg-zinc-800/40' : ($index === 2 ? 'bg-orange-50/30 dark:bg-orange-900/10' : '')) }}">
                                        <td class="px-4 py-3">
                                            @if($index === 0)
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-amber-300 to-amber-500 text-xs font-bold text-white shadow-sm">1</span>
                                            @elseif($index === 1)
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-gray-300 to-gray-400 text-xs font-bold text-white shadow-sm dark:from-zinc-400 dark:to-zinc-500">2</span>
                                            @elseif($index === 2)
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-orange-600 text-xs font-bold text-white shadow-sm">3</span>
                                            @else
                                                <span class="inline-flex h-7 w-7 items-center justify-center text-sm font-medium text-gray-500 dark:text-zinc-400">{{ $index + 1 }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $customer['customer_name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $customer['customer_email'] ?? 'No email' }}</p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">{{ number_format($customer['total_orders']) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">RM {{ number_format($customer['total_revenue'], 2) }}</p>
                                            <div class="ml-auto mt-1 h-1.5 w-full max-w-28 rounded-full bg-gray-100 dark:bg-zinc-700">
                                                <div class="h-1.5 rounded-full bg-blue-500 transition-all dark:bg-blue-400" style="width: {{ $customer['revenue_percentage'] }}%"></div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">RM {{ number_format($customer['avg_order_value'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">{{ $customer['completed_orders'] }}/{{ $customer['total_orders'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-8 text-center">
                        <flux:icon name="user-group" class="mx-auto h-12 w-12 text-gray-300 dark:text-zinc-600" />
                        <flux:text class="mt-2 text-sm text-gray-500 dark:text-zinc-400">No customer data available for {{ $selectedYear }}</flux:text>
                    </div>
                @endif
            </flux:card>

            {{-- Full Customer Detail Table (Paginated) --}}
            <flux:card>
                <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="lg">Customer Detail</flux:heading>
                        <flux:text>Complete breakdown of all customers in {{ $selectedYear }}</flux:text>
                    </div>
                    <div class="w-full sm:w-64">
                        <flux:input wire:model.live.debounce.400ms="customerSearch" placeholder="Search customers..." icon="magnifying-glass" />
                    </div>
                </div>

                @if(count($customerDetailTable) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-zinc-800">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Email</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Orders</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Revenue</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Avg Order</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                            @foreach($customerDetailTable as $index => $customer)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800" wire:key="customer-detail-{{ $customerPage }}-{{ $index }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ (($customerPage - 1) * $customerPerPage) + $index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $customer['customer_name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ $customer['customer_email'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-zinc-100">{{ number_format($customer['total_orders']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($customer['total_revenue'], 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">RM {{ number_format($customer['avg_order_value'], 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">{{ $customer['completed_orders'] }}/{{ $customer['total_orders'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Controls --}}
                @if($customerTotalPages > 1)
                <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4 dark:border-zinc-700">
                    <flux:text class="text-sm text-gray-500 dark:text-zinc-400">
                        Showing {{ (($customerPage - 1) * $customerPerPage) + 1 }} - {{ min($customerPage * $customerPerPage, $customerSummary['total_customers'] ?? 0) }}
                        of {{ number_format($customerSummary['total_customers'] ?? 0) }} customers
                    </flux:text>
                    <div class="flex items-center gap-2">
                        <flux:button wire:click="setCustomerPage(1)" variant="outline" size="sm" :disabled="$customerPage <= 1">
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-double-left" class="h-4 w-4" />
                            </div>
                        </flux:button>
                        <flux:button wire:click="setCustomerPage({{ $customerPage - 1 }})" variant="outline" size="sm" :disabled="$customerPage <= 1">
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-left" class="h-4 w-4" />
                            </div>
                        </flux:button>
                        <flux:text class="px-3 text-sm font-medium">Page {{ $customerPage }} of {{ $customerTotalPages }}</flux:text>
                        <flux:button wire:click="setCustomerPage({{ $customerPage + 1 }})" variant="outline" size="sm" :disabled="$customerPage >= $customerTotalPages">
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-right" class="h-4 w-4" />
                            </div>
                        </flux:button>
                        <flux:button wire:click="setCustomerPage({{ $customerTotalPages }})" variant="outline" size="sm" :disabled="$customerPage >= $customerTotalPages">
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-double-right" class="h-4 w-4" />
                            </div>
                        </flux:button>
                    </div>
                </div>
                @endif
                @else
                <div class="py-8 text-center">
                    <flux:icon name="user-group" class="mx-auto h-12 w-12 text-gray-300 dark:text-zinc-600" />
                    <flux:text class="mt-2 text-sm text-gray-500 dark:text-zinc-400">
                        @if($customerSearch)
                            No customers found matching "{{ $customerSearch }}"
                        @else
                            No customer data available for {{ $selectedYear }}
                        @endif
                    </flux:text>
                </div>
                @endif
            </flux:card>
        </div>
        @endif

        </div>

    @vite('resources/js/reports-charts.js')
    <script>
        function hideOrderReportSkeletons() {
            const revenueSkeleton = document.getElementById('orderReportRevenueSkeleton');
            const sourceSkeleton = document.getElementById('orderReportSourceSkeleton');
            const revenueCanvas = document.getElementById('orderReportRevenueTrendChart');
            const sourceCanvas = document.getElementById('orderReportBySourceChart');

            if (revenueSkeleton) revenueSkeleton.style.display = 'none';
            if (sourceSkeleton) sourceSkeleton.style.display = 'none';
            if (revenueCanvas) revenueCanvas.classList.remove('opacity-0');
            if (sourceCanvas) sourceCanvas.classList.remove('opacity-0');
        }

        function showOrderReportSkeletons() {
            const revenueSkeleton = document.getElementById('orderReportRevenueSkeleton');
            const sourceSkeleton = document.getElementById('orderReportSourceSkeleton');
            const revenueCanvas = document.getElementById('orderReportRevenueTrendChart');
            const sourceCanvas = document.getElementById('orderReportBySourceChart');

            if (revenueSkeleton) revenueSkeleton.style.display = 'flex';
            if (sourceSkeleton) sourceSkeleton.style.display = 'flex';
            if (revenueCanvas) revenueCanvas.classList.add('opacity-0');
            if (sourceCanvas) sourceCanvas.classList.add('opacity-0');
        }

        function initOrderReportCharts(data, retryCount = 0) {
            const maxRetries = 10;
            if (typeof window.initializeOrderReportCharts === 'function') {
                if (typeof window.destroyOrderReportCharts === 'function') {
                    window.destroyOrderReportCharts();
                }
                window.initializeOrderReportCharts(data);
                setTimeout(hideOrderReportSkeletons, 400);
            } else if (retryCount < maxRetries) {
                setTimeout(() => initOrderReportCharts(data, retryCount + 1), 100);
            }
        }

        function initOrderReportProductCharts(monthlyProductData, topProducts, retryCount = 0) {
            const maxRetries = 10;
            if (typeof window.initializeOrderReportProductCharts === 'function') {
                if (typeof window.destroyOrderReportProductCharts === 'function') {
                    window.destroyOrderReportProductCharts();
                }
                window.initializeOrderReportProductCharts(monthlyProductData, topProducts);
            } else if (retryCount < maxRetries) {
                setTimeout(() => initOrderReportProductCharts(monthlyProductData, topProducts, retryCount + 1), 100);
            }
        }

        function initOrderReportCustomerCharts(monthlyCustomerData, topCustomers, retryCount = 0) {
            const maxRetries = 10;
            if (typeof window.initializeOrderReportCustomerCharts === 'function') {
                if (typeof window.destroyOrderReportCustomerCharts === 'function') {
                    window.destroyOrderReportCustomerCharts();
                }
                window.initializeOrderReportCustomerCharts(monthlyCustomerData, topCustomers);
            } else if (retryCount < maxRetries) {
                setTimeout(() => initOrderReportCustomerCharts(monthlyCustomerData, topCustomers, retryCount + 1), 100);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const monthlyData = @json($monthlyData);
            initOrderReportCharts(monthlyData);
        });

        document.addEventListener('livewire:init', function() {
            Livewire.on('order-report-charts-updated', (event) => {
                const monthlyData = event.monthlyData;
                setTimeout(function() {
                    showOrderReportSkeletons();
                    initOrderReportCharts(monthlyData);
                }, 150);
            });

            Livewire.on('order-report-product-charts-update', (event) => {
                setTimeout(function() {
                    initOrderReportProductCharts(event.monthlyProductData, event.topProductsByRevenue);
                }, 150);
            });

            Livewire.on('order-report-customer-charts-update', (event) => {
                setTimeout(function() {
                    initOrderReportCustomerCharts(event.monthlyCustomerData, event.topCustomers);
                }, 150);
            });
        });

        document.addEventListener('livewire:navigated', function() {
            setTimeout(function() {
                const monthlyData = @json($monthlyData);
                initOrderReportCharts(monthlyData);
            }, 200);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = @json($activeTab);
            if (activeTab === 'products') {
                const monthlyProductData = @json($monthlyProductData);
                const topProductsByRevenue = @json($topProductsByRevenue);
                initOrderReportProductCharts(monthlyProductData, topProductsByRevenue);
            } else if (activeTab === 'customers') {
                const monthlyCustomerData = @json($monthlyCustomerData);
                const topCustomers = @json($topCustomers);
                initOrderReportCustomerCharts(monthlyCustomerData, topCustomers);
            }
        });
    </script>
</div>
