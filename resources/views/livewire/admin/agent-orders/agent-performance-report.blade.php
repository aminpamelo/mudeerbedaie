<?php

use App\Models\Agent;
use App\Models\ProductOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    public int $selectedYear;

    public string $agentType = '';

    public array $availableYears = [];

    public array $monthlyData = [];

    public array $summary = [];

    public array $agentTypeBreakdown = [];

    public array $topProducts = [];

    public array $topAgents = [];

    public array $productSummary = [];

    public array $topProductsByRevenue = [];

    public array $topProductsByVolume = [];

    public array $productDetailTable = [];

    public array $monthlyProductData = [];

    public array $agentDetailTable = [];

    public array $monthlyAgentData = [];

    #[Url(as: 'tab')]
    public string $activeTab = 'orders';

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;

        if ($tab === 'orders') {
            $this->dispatch('charts-data-updated', monthlyData: $this->monthlyData);
        } elseif ($tab === 'products') {
            $this->dispatch('product-insights-charts-update', monthlyProductData: $this->monthlyProductData, topProductsByRevenue: $this->topProductsByRevenue);
        } elseif ($tab === 'agents') {
            $this->dispatch('agent-leaderboard-charts-update', monthlyAgentData: $this->monthlyAgentData, topAgents: $this->topAgents);
        }
    }

    /**
     * Get the database driver name.
     */
    private function getDriver(): string
    {
        return DB::getDriverName();
    }

    /**
     * Get the SQL expression for extracting year from a date column.
     */
    private function yearExpression(string $column): string
    {
        return $this->getDriver() === 'sqlite'
            ? "CAST(strftime('%Y', {$column}) AS INTEGER)"
            : "YEAR({$column})";
    }

    /**
     * Get the SQL expression for extracting month from a date column.
     */
    private function monthExpression(string $column): string
    {
        return $this->getDriver() === 'sqlite'
            ? "CAST(strftime('%m', {$column}) AS INTEGER)"
            : "MONTH({$column})";
    }

    public function mount(): void
    {
        // Get available years from product_orders with agents
        $yearExpr = $this->yearExpression('order_date');

        $orderYears = DB::table('product_orders')
            ->selectRaw("DISTINCT {$yearExpr} as year")
            ->whereNotNull('order_date')
            ->whereNotNull('agent_id')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        $this->availableYears = collect($orderYears)
            ->filter() // Remove null values
            ->unique()
            ->sort()
            ->reverse()
            ->values()
            ->toArray();

        // Default to current year or latest available year
        $this->selectedYear = ! empty($this->availableYears)
            ? (int) $this->availableYears[0]
            : (int) date('Y');

        $this->loadReportData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadReportData();
        $this->dispatch('charts-data-updated', monthlyData: $this->monthlyData);
    }

    public function updatedAgentType(): void
    {
        $this->loadReportData();
        $this->dispatch('charts-data-updated', monthlyData: $this->monthlyData);
    }

    private function loadReportData(): void
    {
        $this->monthlyData = [];

        // Initialize all 12 months with zero values
        for ($month = 1; $month <= 12; $month++) {
            $monthName = Carbon::create($this->selectedYear, $month, 1)->format('F');

            $this->monthlyData[$month] = [
                'month_name' => $monthName,
                'month_number' => $month,
                'total_orders' => 0,
                'completed_orders' => 0,
                'pending_orders' => 0,
                'cancelled_orders' => 0,
                'total_revenue' => 0,
                'avg_order_value' => 0,
                'by_type' => [
                    'agent' => ['orders' => 0, 'revenue' => 0],
                    'company' => ['orders' => 0, 'revenue' => 0],
                ],
            ];
        }

        $yearExpr = $this->yearExpression('order_date');
        $monthExpr = $this->monthExpression('order_date');

        // Build base query
        $baseQuery = ProductOrder::query()
            ->whereNotNull('agent_id')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->join('agents', 'product_orders.agent_id', '=', 'agents.id');

        if ($this->agentType) {
            $baseQuery->where('agents.type', $this->agentType);
        }

        // Get monthly order data
        $monthlyOrders = (clone $baseQuery)
            ->selectRaw("
                {$monthExpr} as month,
                COUNT(*) as total_orders,
                SUM(CASE WHEN product_orders.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN product_orders.status IN ('pending', 'confirmed', 'processing', 'shipped') THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN product_orders.status IN ('cancelled', 'refunded', 'returned') THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN product_orders.status NOT IN ('cancelled', 'refunded', 'draft') THEN product_orders.total_amount ELSE 0 END) as total_revenue
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

        // Get breakdown by agent type per month
        $typeBreakdown = ProductOrder::query()
            ->whereNotNull('agent_id')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('status', ['cancelled', 'refunded', 'draft'])
            ->join('agents', 'product_orders.agent_id', '=', 'agents.id')
            ->selectRaw("
                {$monthExpr} as month,
                agents.type as agent_type,
                COUNT(*) as orders,
                SUM(product_orders.total_amount) as revenue
            ")
            ->groupByRaw("{$monthExpr}, agents.type")
            ->get();

        foreach ($typeBreakdown as $data) {
            $month = (int) $data->month;
            $type = $data->agent_type;
            if ($month >= 1 && $month <= 12 && isset($this->monthlyData[$month]['by_type'][$type])) {
                $this->monthlyData[$month]['by_type'][$type] = [
                    'orders' => (int) $data->orders,
                    'revenue' => (float) $data->revenue,
                ];
            }
        }

        // Calculate summary statistics
        $this->calculateSummary();

        // Calculate agent type breakdown for the year
        $this->calculateAgentTypeBreakdown();

        // Load top products and top agents data
        $this->loadTopProducts();
        $this->loadTopAgents();
    }

    private function calculateSummary(): void
    {
        $totalOrders = 0;
        $completedOrders = 0;
        $pendingOrders = 0;
        $cancelledOrders = 0;
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
            'avg_monthly_revenue' => $totalRevenue / 12,
            'completion_rate' => $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0,
        ];
    }

    private function calculateAgentTypeBreakdown(): void
    {
        $yearExpr = $this->yearExpression('order_date');

        $breakdown = ProductOrder::query()
            ->whereNotNull('agent_id')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('status', ['cancelled', 'refunded', 'draft'])
            ->join('agents', 'product_orders.agent_id', '=', 'agents.id')
            ->selectRaw('
                agents.type as agent_type,
                COUNT(*) as orders,
                SUM(product_orders.total_amount) as revenue
            ')
            ->groupBy('agents.type')
            ->get();

        $this->agentTypeBreakdown = [
            'agent' => ['orders' => 0, 'revenue' => 0],
            'company' => ['orders' => 0, 'revenue' => 0],
        ];

        foreach ($breakdown as $data) {
            $type = $data->agent_type;
            if (isset($this->agentTypeBreakdown[$type])) {
                $this->agentTypeBreakdown[$type] = [
                    'orders' => (int) $data->orders,
                    'revenue' => (float) $data->revenue,
                ];
            }
        }
    }

    private function loadTopProducts(): void
    {
        $yearExpr = $this->yearExpression('po.order_date');
        $monthExpr = $this->monthExpression('po.order_date');

        $query = DB::table('product_order_items as poi')
            ->join('product_orders as po', 'poi.order_id', '=', 'po.id')
            ->leftJoin('products as p', 'poi.product_id', '=', 'p.id')
            ->whereNotNull('po.agent_id')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('po.status', ['cancelled', 'refunded', 'draft']);

        if ($this->agentType) {
            $query->join('agents as a', 'po.agent_id', '=', 'a.id')
                ->where('a.type', $this->agentType);
        }

        $allData = $query->select([
            DB::raw('COALESCE(p.id, 0) as product_id'),
            DB::raw('COALESCE(p.name, poi.product_name) as product_name'),
            'p.sku',
            DB::raw("{$monthExpr} as month"),
            DB::raw('COUNT(DISTINCT po.id) as order_count'),
            DB::raw('SUM(poi.quantity_ordered) as total_quantity'),
            DB::raw('SUM(poi.total_price) as total_revenue'),
        ])
            ->groupByRaw("COALESCE(p.id, 0), COALESCE(p.name, poi.product_name), p.sku, {$monthExpr}")
            ->get();

        $productTotals = [];
        $productMonthly = [];

        // Monthly aggregation for chart
        $monthlyAgg = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyAgg[$m] = ['units_sold' => 0, 'revenue' => 0];
        }

        foreach ($allData as $row) {
            $key = $row->product_id.'|'.$row->product_name;

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

        // Build monthly product data for chart
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

        // Sort by revenue for topProducts and topProductsByRevenue
        uasort($productTotals, fn ($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        // Build all products with avg_price for detail table
        $allProducts = [];
        foreach ($productTotals as $key => $product) {
            $product['monthly'] = $productMonthly[$key];
            $product['avg_price'] = $product['total_quantity'] > 0
                ? round($product['total_revenue'] / $product['total_quantity'], 2)
                : 0;
            $allProducts[] = $product;
        }

        // Top 10 by revenue
        $this->topProducts = array_slice($allProducts, 0, 10);
        $this->topProductsByRevenue = array_slice($allProducts, 0, 10);

        // Full detail table (all products by revenue)
        $this->productDetailTable = $allProducts;

        // Top 10 by volume
        $byVolume = $allProducts;
        usort($byVolume, fn ($a, $b) => $b['total_quantity'] <=> $a['total_quantity']);
        $this->topProductsByVolume = array_slice($byVolume, 0, 10);

        // Product summary
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

    private function loadTopAgents(): void
    {
        $yearExpr = $this->yearExpression('po.order_date');
        $monthExpr = $this->monthExpression('po.order_date');

        $query = DB::table('product_orders as po')
            ->join('agents as a', 'po.agent_id', '=', 'a.id')
            ->whereNotNull('po.agent_id')
            ->whereRaw("{$yearExpr} = ?", [$this->selectedYear])
            ->whereNotIn('po.status', ['cancelled', 'refunded', 'draft']);

        if ($this->agentType) {
            $query->where('a.type', $this->agentType);
        }

        // Get all agents (no limit) for full detail table
        $agents = (clone $query)->select([
            'a.id',
            'a.name',
            'a.type',
            'a.agent_code',
            'a.pricing_tier',
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(po.total_amount) as total_revenue'),
            DB::raw('AVG(po.total_amount) as avg_order_value'),
            DB::raw("SUM(CASE WHEN po.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders"),
        ])
            ->groupBy('a.id', 'a.name', 'a.type', 'a.agent_code', 'a.pricing_tier')
            ->orderByDesc('total_revenue')
            ->get();

        $allAgents = [];
        $maxRevenue = $agents->isNotEmpty() ? (float) $agents->first()->total_revenue : 0;

        foreach ($agents as $agent) {
            $totalOrders = (int) $agent->total_orders;
            $completedOrders = (int) $agent->completed_orders;

            $allAgents[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'type' => $agent->type,
                'agent_code' => $agent->agent_code,
                'pricing_tier' => $agent->pricing_tier,
                'total_orders' => $totalOrders,
                'total_revenue' => (float) $agent->total_revenue,
                'avg_order_value' => (float) $agent->avg_order_value,
                'completed_orders' => $completedOrders,
                'completion_rate' => $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0,
                'revenue_percentage' => $maxRevenue > 0 ? ((float) $agent->total_revenue / $maxRevenue) * 100 : 0,
            ];
        }

        $this->topAgents = array_slice($allAgents, 0, 10);
        $this->agentDetailTable = $allAgents;

        // Monthly agent data by type for chart
        $monthlyByType = (clone $query)->select([
            DB::raw("{$monthExpr} as month"),
            'a.type as agent_type',
            DB::raw('COUNT(*) as orders'),
            DB::raw('SUM(po.total_amount) as revenue'),
        ])
            ->groupByRaw("{$monthExpr}, a.type")
            ->get();

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $this->monthlyAgentData = [];

        for ($m = 1; $m <= 12; $m++) {
            $this->monthlyAgentData[] = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'agent_orders' => 0,
                'agent_revenue' => 0,
                'company_orders' => 0,
                'company_revenue' => 0,
            ];
        }

        foreach ($monthlyByType as $row) {
            $month = (int) $row->month;
            if ($month >= 1 && $month <= 12) {
                $idx = $month - 1;
                if ($row->agent_type === 'agent') {
                    $this->monthlyAgentData[$idx]['agent_orders'] = (int) $row->orders;
                    $this->monthlyAgentData[$idx]['agent_revenue'] = (float) $row->revenue;
                } elseif ($row->agent_type === 'company') {
                    $this->monthlyAgentData[$idx]['company_orders'] = (int) $row->orders;
                    $this->monthlyAgentData[$idx]['company_revenue'] = (float) $row->revenue;
                }
            }
        }
    }

    public function getAgentTypes(): array
    {
        return [
            '' => 'All Types',
            'agent' => 'Agent',
            'company' => 'Company',
        ];
    }

    public function exportCsv()
    {
        $filename = "agent-performance-report-{$this->selectedYear}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Month',
                'Total Orders',
                'Completed',
                'Pending',
                'Cancelled',
                'Revenue (RM)',
                'Avg Order Value (RM)',
                'Agent Orders',
                'Agent Revenue',
                'Company Orders',
                'Company Revenue',
            ]);

            // Data rows
            foreach ($this->monthlyData as $data) {
                fputcsv($file, [
                    $data['month_name'],
                    $data['total_orders'],
                    $data['completed_orders'],
                    $data['pending_orders'],
                    $data['cancelled_orders'],
                    number_format($data['total_revenue'], 2),
                    number_format($data['avg_order_value'], 2),
                    $data['by_type']['agent']['orders'],
                    number_format($data['by_type']['agent']['revenue'], 2),
                    $data['by_type']['company']['orders'],
                    number_format($data['by_type']['company']['revenue'], 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div>
    <div class="mx-auto max-w-7xl space-y-6 p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col gap-4">
            {{-- Title and Description --}}
            <div>
                <flux:heading size="xl">Agent Performance Report</flux:heading>
                <flux:text class="mt-2">Monthly orders and sales report from all agents</flux:text>
            </div>

            {{-- Filters and Export Button --}}
            <div class="flex flex-col gap-3 w-full sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col w-full gap-3 sm:flex-row sm:items-center">
                    {{-- Agent Type Filter --}}
                    <flux:select wire:model.live="agentType" class="max-w-full">
                        @foreach($this->getAgentTypes() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>

                    {{-- Year Filter --}}
                    <flux:select wire:model.live="selectedYear" class="max-w-full">
                        @forelse($availableYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @empty
                            <option value="{{ date('Y') }}">{{ date('Y') }}</option>
                        @endforelse
                    </flux:select>
                </div>

                {{-- Export Button --}}
                <flux:button wire:click="exportCsv" variant="outline" icon="arrow-down-tray">
                    Export CSV
                </flux:button>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Revenue</flux:text>
                        <flux:heading size="lg" class="mt-1">RM {{ number_format($summary['total_revenue'] ?? 0, 2) }}</flux:heading>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                        <flux:icon name="banknotes" class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Orders</flux:text>
                        <flux:heading size="lg" class="mt-1">{{ number_format($summary['total_orders'] ?? 0) }}</flux:heading>
                        <flux:text class="text-xs text-gray-400 dark:text-zinc-500">{{ number_format($summary['completed_orders'] ?? 0) }} completed</flux:text>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <flux:icon name="shopping-bag" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Avg Order Value</flux:text>
                        <flux:heading size="lg" class="mt-1">RM {{ number_format($summary['avg_order_value'] ?? 0, 2) }}</flux:heading>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                        <flux:icon name="calculator" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Completion Rate</flux:text>
                        <flux:heading size="lg" class="mt-1">{{ number_format($summary['completion_rate'] ?? 0, 1) }}%</flux:heading>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                        <flux:icon name="check-circle" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Agent Type Breakdown Cards --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:card class="border-l-4 border-l-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Agent</flux:text>
                        <flux:heading size="lg" class="mt-1">{{ number_format($agentTypeBreakdown['agent']['orders'] ?? 0) }} orders</flux:heading>
                        <flux:text class="text-sm text-green-600 dark:text-green-400">RM {{ number_format($agentTypeBreakdown['agent']['revenue'] ?? 0, 2) }}</flux:text>
                    </div>
                    <flux:icon name="user" class="h-8 w-8 text-blue-500" />
                </div>
            </flux:card>

            <flux:card class="border-l-4 border-l-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Company</flux:text>
                        <flux:heading size="lg" class="mt-1">{{ number_format($agentTypeBreakdown['company']['orders'] ?? 0) }} orders</flux:heading>
                        <flux:text class="text-sm text-green-600 dark:text-green-400">RM {{ number_format($agentTypeBreakdown['company']['revenue'] ?? 0, 2) }}</flux:text>
                    </div>
                    <flux:icon name="building-office" class="h-8 w-8 text-purple-500" />
                </div>
            </flux:card>
        </div>

        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200 dark:border-zinc-700">
            <nav class="-mb-px flex gap-6 overflow-x-auto">
                <button
                    wire:click="setActiveTab('orders')"
                    class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors
                        {{ $activeTab === 'orders' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-zinc-400 dark:hover:text-zinc-300 dark:hover:border-zinc-600' }}">
                    <flux:icon name="chart-bar" class="w-4 h-4" />
                    Orders Overview
                </button>

                <button
                    wire:click="setActiveTab('products')"
                    class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors
                        {{ $activeTab === 'products' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-zinc-400 dark:hover:text-zinc-300 dark:hover:border-zinc-600' }}">
                    <flux:icon name="cube" class="w-4 h-4" />
                    Product Insights
                    @if(count($topProducts) > 0)
                        <flux:badge size="sm">{{ count($topProducts) }}</flux:badge>
                    @endif
                </button>

                <button
                    wire:click="setActiveTab('agents')"
                    class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors
                        {{ $activeTab === 'agents' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-zinc-400 dark:hover:text-zinc-300 dark:hover:border-zinc-600' }}">
                    <flux:icon name="trophy" class="w-4 h-4" />
                    Top Agents & Companies
                    @if(count($topAgents) > 0)
                        <flux:badge size="sm">{{ count($topAgents) }}</flux:badge>
                    @endif
                </button>
            </nav>
        </div>

        {{-- Tab Content --}}

        {{-- Orders Overview Tab --}}
        @if($activeTab === 'orders')
        <div class="space-y-6">

        {{-- Charts Section --}}
        <div class="grid gap-6 lg:grid-cols-2" wire:ignore.self>
            {{-- Revenue Trend Chart --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Monthly Revenue Trend</flux:heading>
                    <flux:text class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Revenue and order count by month</flux:text>
                </div>
                <div class="relative h-80">
                    {{-- Skeleton Loader --}}
                    <div id="revenueTrendSkeleton" class="absolute inset-0 flex flex-col items-center justify-center">
                        <div class="w-full space-y-3">
                            {{-- Skeleton bars --}}
                            <div class="flex items-end justify-between gap-2 h-48 px-4">
                                @for($i = 0; $i < 12; $i++)
                                    <div class="flex-1 bg-gray-200 dark:bg-zinc-700 rounded-t animate-pulse" style="height: {{ rand(30, 100) }}%"></div>
                                @endfor
                            </div>
                            {{-- Skeleton labels --}}
                            <div class="flex justify-between px-4">
                                @for($i = 0; $i < 12; $i++)
                                    <div class="h-3 w-6 bg-gray-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                                @endfor
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading chart...
                        </div>
                    </div>
                    {{-- Loading Overlay for filter changes --}}
                    <div wire:loading wire:target="agentType, selectedYear" class="absolute inset-0 bg-white/70 dark:bg-zinc-900/70 flex items-center justify-center z-10 rounded-lg">
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-zinc-300">
                            <svg class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Updating...
                        </div>
                    </div>
                    <canvas id="agentRevenueTrendChart" class="opacity-0 transition-opacity duration-300"></canvas>
                </div>
            </flux:card>

            {{-- Orders by Type Chart --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Orders by Agent Type</flux:heading>
                    <flux:text class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Order comparison between agent types</flux:text>
                </div>
                <div class="relative h-80">
                    {{-- Skeleton Loader --}}
                    <div id="ordersByTypeSkeleton" class="absolute inset-0 flex flex-col items-center justify-center">
                        <div class="w-full space-y-3">
                            {{-- Skeleton bars for grouped bar chart --}}
                            <div class="flex items-end justify-between gap-4 h-48 px-8">
                                @for($i = 0; $i < 12; $i++)
                                    <div class="flex gap-0.5 items-end flex-1">
                                        <div class="w-1/3 bg-blue-200 dark:bg-blue-800/50 rounded-t animate-pulse" style="height: {{ rand(20, 80) }}%"></div>
                                        <div class="w-1/3 bg-purple-200 dark:bg-purple-800/50 rounded-t animate-pulse" style="height: {{ rand(20, 80) }}%"></div>
                                        <div class="w-1/3 bg-orange-200 dark:bg-orange-800/50 rounded-t animate-pulse" style="height: {{ rand(20, 80) }}%"></div>
                                    </div>
                                @endfor
                            </div>
                            {{-- Skeleton labels --}}
                            <div class="flex justify-between px-8">
                                @for($i = 0; $i < 12; $i++)
                                    <div class="h-3 w-4 bg-gray-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                                @endfor
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading chart...
                        </div>
                    </div>
                    {{-- Loading Overlay for filter changes --}}
                    <div wire:loading wire:target="agentType, selectedYear" class="absolute inset-0 bg-white/70 dark:bg-zinc-900/70 flex items-center justify-center z-10 rounded-lg">
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-zinc-300">
                            <svg class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Updating...
                        </div>
                    </div>
                    <canvas id="agentOrdersByTypeChart" class="opacity-0 transition-opacity duration-300"></canvas>
                </div>
            </flux:card>
        </div>

        {{-- Monthly Data Table --}}
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Monthly Data</flux:heading>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-zinc-700">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Month</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Orders</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Completed</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Pending</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Cancelled</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Revenue (RM)</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Avg (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($monthlyData as $data)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $data['month_name'] }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">
                                    {{ number_format($data['total_orders']) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <span class="text-green-600 dark:text-green-400">{{ number_format($data['completed_orders']) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <span class="text-yellow-600 dark:text-yellow-400">{{ number_format($data['pending_orders']) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <span class="text-red-600 dark:text-red-400">{{ number_format($data['cancelled_orders']) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                    RM {{ number_format($data['total_revenue'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">
                                    RM {{ number_format($data['avg_order_value'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-zinc-400">
                                    No data for {{ $selectedYear }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 dark:border-zinc-600 bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-white">Total</td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">
                                {{ number_format($summary['total_orders'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-green-600 dark:text-green-400">
                                {{ number_format($summary['completed_orders'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-yellow-600 dark:text-yellow-400">
                                {{ number_format($summary['pending_orders'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-red-600 dark:text-red-400">
                                {{ number_format($summary['cancelled_orders'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">
                                RM {{ number_format($summary['total_revenue'] ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">
                                RM {{ number_format($summary['avg_order_value'] ?? 0, 2) }}
                            </td>
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
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Distinct products sold</flux:text>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ number_format($productSummary['total_units'] ?? 0) }}</flux:heading>
                    <div class="rounded-lg bg-teal-100 p-2 dark:bg-teal-900/30">
                        <flux:icon name="shopping-cart" class="h-6 w-6 text-teal-600 dark:text-teal-400" />
                    </div>
                </div>
                <flux:text>Total Units Sold</flux:text>
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Across all products</flux:text>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">RM {{ number_format($productSummary['total_revenue'] ?? 0, 2) }}</flux:heading>
                    <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                        <flux:icon name="banknotes" class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <flux:text>Product Revenue</flux:text>
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Total from product sales</flux:text>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">RM {{ number_format($productSummary['avg_revenue_per_product'] ?? 0, 2) }}</flux:heading>
                    <div class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                        <flux:icon name="calculator" class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
                <flux:text>Avg Revenue / Product</flux:text>
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Average per unique product</flux:text>
            </flux:card>
        </div>

        {{-- Product Charts --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Monthly Product Sales Trend &mdash; {{ $selectedYear }}</flux:heading>
                    <flux:text>Units sold and revenue throughout the year</flux:text>
                </div>
                <div style="height: 300px;">
                    <canvas id="agentProductMonthlyTrendChart"></canvas>
                </div>
            </flux:card>

            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Top Products by Revenue</flux:heading>
                    <flux:text>Top 10 products ranked by sales revenue</flux:text>
                </div>
                <div style="height: 300px;">
                    <canvas id="agentProductRevenueBarChart"></canvas>
                </div>
            </flux:card>
        </div>

        {{-- Top Products Ranked --}}
        @if(count($topProductsByRevenue) > 0)
            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Top by Revenue --}}
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Top Products by Revenue</flux:heading>
                        <flux:text>Ranked by total sales revenue</flux:text>
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
                                        <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ $product['total_quantity'] }} units &middot; {{ $product['order_count'] }} orders</flux:text>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <flux:heading size="sm">RM {{ number_format($product['total_revenue'], 2) }}</flux:heading>
                                    <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Avg RM {{ number_format($product['avg_price'] ?? 0, 2) }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                {{-- Top by Volume --}}
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
                                <div class="text-right shrink-0">
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
                <flux:text>Complete breakdown of all products sold through agents in {{ $selectedYear }}</flux:text>
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

        {{-- Top Agents & Companies Tab --}}
        @if($activeTab === 'agents')
        <div class="space-y-6">

        {{-- Agent Summary Cards --}}
        @php
            $totalAgentCount = count($agentDetailTable);
            $totalAgentRevenue = collect($agentDetailTable)->sum('total_revenue');
            $avgRevenuePerAgent = $totalAgentCount > 0 ? $totalAgentRevenue / $totalAgentCount : 0;
            $bestCompletionRate = collect($agentDetailTable)->max('completion_rate') ?? 0;
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ number_format($totalAgentCount) }}</flux:heading>
                    <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <flux:icon name="users" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
                <flux:text>Active Agents</flux:text>
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Agents with orders in {{ $selectedYear }}</flux:text>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">RM {{ number_format($totalAgentRevenue, 2) }}</flux:heading>
                    <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                        <flux:icon name="banknotes" class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <flux:text>Total Revenue</flux:text>
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">From all agents combined</flux:text>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">RM {{ number_format($avgRevenuePerAgent, 2) }}</flux:heading>
                    <div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                        <flux:icon name="calculator" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
                <flux:text>Avg Revenue / Agent</flux:text>
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Average per active agent</flux:text>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ number_format($bestCompletionRate, 0) }}%</flux:heading>
                    <div class="rounded-lg bg-yellow-100 p-2 dark:bg-yellow-900/30">
                        <flux:icon name="check-circle" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                </div>
                <flux:text>Best Completion Rate</flux:text>
                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Highest among all agents</flux:text>
            </flux:card>
        </div>

        {{-- Agent Charts --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Monthly Revenue by Type &mdash; {{ $selectedYear }}</flux:heading>
                    <flux:text>Agent vs Company revenue trends</flux:text>
                </div>
                <div style="height: 300px;">
                    <canvas id="agentLeaderboardTrendChart"></canvas>
                </div>
            </flux:card>

            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Revenue by Agent</flux:heading>
                    <flux:text>Top 10 agents ranked by revenue</flux:text>
                </div>
                <div style="height: 300px;">
                    <canvas id="agentLeaderboardBarChart"></canvas>
                </div>
            </flux:card>
        </div>

        {{-- Top Agents Leaderboard --}}
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Top Agents & Companies</flux:heading>
                <flux:text class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Leaderboard ranked by revenue in {{ $selectedYear }}</flux:text>
            </div>
            @if(count($topAgents) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-zinc-700">
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white w-10">Rank</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Agent / Company</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Type</th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Orders</th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Revenue (RM)</th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Avg Order (RM)</th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Completion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                            @foreach($topAgents as $index => $agent)
                                <tr wire:key="top-agent-{{ $agent['id'] }}" class="hover:bg-gray-50 dark:hover:bg-zinc-800 {{ $index === 0 ? 'bg-amber-50/40 dark:bg-amber-900/10' : ($index === 1 ? 'bg-gray-50/40 dark:bg-zinc-800/40' : ($index === 2 ? 'bg-orange-50/30 dark:bg-orange-900/10' : '')) }}">
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
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $agent['name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $agent['agent_code'] }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($agent['type'] === 'company')
                                            <flux:badge color="purple" size="sm">Company</flux:badge>
                                        @else
                                            <flux:badge color="blue" size="sm">Agent</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">{{ number_format($agent['total_orders']) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">RM {{ number_format($agent['total_revenue'], 2) }}</p>
                                        <div class="mt-1 h-1.5 w-full max-w-28 ml-auto rounded-full bg-gray-100 dark:bg-zinc-700">
                                            <div class="h-1.5 rounded-full bg-blue-500 dark:bg-blue-400 transition-all" style="width: {{ $agent['revenue_percentage'] }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">RM {{ number_format($agent['avg_order_value'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @php
                                            $rate = $agent['completion_rate'];
                                            $rateColor = $rate >= 80 ? 'text-green-600 dark:text-green-400' : ($rate >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                        @endphp
                                        <span class="text-sm font-medium {{ $rateColor }}">{{ number_format($rate, 0) }}%</span>
                                        <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $agent['completed_orders'] }}/{{ $agent['total_orders'] }}</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon name="user-group" class="mx-auto h-12 w-12 text-gray-300 dark:text-zinc-600" />
                    <flux:text class="mt-2 text-sm text-gray-500 dark:text-zinc-400">No agent data available for {{ $selectedYear }}</flux:text>
                </div>
            @endif
        </flux:card>

        {{-- Full Agent Detail Table --}}
        @if(count($agentDetailTable) > 0)
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Agent Performance Detail</flux:heading>
                <flux:text>Complete breakdown of all agents and companies in {{ $selectedYear }}</flux:text>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-zinc-800">
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Code</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Type</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Tier</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Orders</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Revenue</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Avg Order</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Completed</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                        @foreach($agentDetailTable as $index => $agent)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800" wire:key="agent-detail-{{ $agent['id'] }}">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $agent['name'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ $agent['agent_code'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    @if($agent['type'] === 'company')
                                        <flux:badge color="purple" size="sm">Company</flux:badge>
                                    @else
                                        <flux:badge color="blue" size="sm">Agent</flux:badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-gray-500 dark:text-zinc-400">{{ ucfirst($agent['pricing_tier'] ?? '-') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-zinc-100">{{ number_format($agent['total_orders']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($agent['total_revenue'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">RM {{ number_format($agent['avg_order_value'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">{{ $agent['completed_orders'] }}/{{ $agent['total_orders'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @php
                                        $rate = $agent['completion_rate'];
                                        $rateColor = $rate >= 80 ? 'text-green-600 dark:text-green-400' : ($rate >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                    @endphp
                                    <span class="text-sm font-medium {{ $rateColor }}">{{ number_format($rate, 0) }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 bg-gray-50 dark:border-zinc-600 dark:bg-zinc-800">
                        <tr>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-zinc-100">Total</td>
                            <td class="px-4 py-3" colspan="3"></td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">{{ number_format(collect($agentDetailTable)->sum('total_orders')) }}</td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">RM {{ number_format(collect($agentDetailTable)->sum('total_revenue'), 2) }}</td>
                            <td class="px-4 py-3" colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </flux:card>
        @endif

        </div>
        @endif

    </div>

    @vite('resources/js/reports-charts.js')
    <script>
        // Function to hide skeletons and show charts
        function hideSkeletonsAndShowCharts() {
            const revenueSkeleton = document.getElementById('revenueTrendSkeleton');
            const ordersSkeleton = document.getElementById('ordersByTypeSkeleton');
            const revenueCanvas = document.getElementById('agentRevenueTrendChart');
            const ordersCanvas = document.getElementById('agentOrdersByTypeChart');

            if (revenueSkeleton) revenueSkeleton.style.display = 'none';
            if (ordersSkeleton) ordersSkeleton.style.display = 'none';
            if (revenueCanvas) revenueCanvas.classList.remove('opacity-0');
            if (ordersCanvas) ordersCanvas.classList.remove('opacity-0');
        }

        // Function to show skeletons and hide charts (for loading state)
        function showSkeletonsAndHideCharts() {
            const revenueSkeleton = document.getElementById('revenueTrendSkeleton');
            const ordersSkeleton = document.getElementById('ordersByTypeSkeleton');
            const revenueCanvas = document.getElementById('agentRevenueTrendChart');
            const ordersCanvas = document.getElementById('agentOrdersByTypeChart');

            if (revenueSkeleton) revenueSkeleton.style.display = 'flex';
            if (ordersSkeleton) ordersSkeleton.style.display = 'flex';
            if (revenueCanvas) revenueCanvas.classList.add('opacity-0');
            if (ordersCanvas) ordersCanvas.classList.add('opacity-0');
        }

        // Initialize charts function with retry logic
        function initCharts(data, retryCount = 0) {
            const maxRetries = 10;

            if (typeof window.initializeAgentPerformanceCharts === 'function') {
                // Destroy existing charts first
                if (typeof window.destroyAgentPerformanceCharts === 'function') {
                    window.destroyAgentPerformanceCharts();
                }

                // Initialize new charts
                window.initializeAgentPerformanceCharts(data);

                // Hide skeletons after charts are rendered
                setTimeout(hideSkeletonsAndShowCharts, 400);
            } else if (retryCount < maxRetries) {
                // If chart function not ready, retry after short delay
                setTimeout(() => initCharts(data, retryCount + 1), 100);
            }
        }

        // Initial load
        document.addEventListener('DOMContentLoaded', function() {
            const monthlyData = @json($monthlyData);
            initCharts(monthlyData);
        });

        // Initialize product insights charts
        function initProductInsightsCharts(monthlyProductData, topProductsByRevenue, retryCount = 0) {
            const maxRetries = 10;
            if (typeof window.initializeAgentProductCharts === 'function') {
                if (typeof window.destroyAgentProductCharts === 'function') {
                    window.destroyAgentProductCharts();
                }
                window.initializeAgentProductCharts(monthlyProductData, topProductsByRevenue);
            } else if (retryCount < maxRetries) {
                setTimeout(() => initProductInsightsCharts(monthlyProductData, topProductsByRevenue, retryCount + 1), 100);
            }
        }

        // Initialize agent leaderboard charts
        function initAgentLeaderboardCharts(monthlyAgentData, topAgents, retryCount = 0) {
            const maxRetries = 10;
            if (typeof window.initializeAgentLeaderboardCharts === 'function') {
                if (typeof window.destroyAgentLeaderboardCharts === 'function') {
                    window.destroyAgentLeaderboardCharts();
                }
                window.initializeAgentLeaderboardCharts(monthlyAgentData, topAgents);
            } else if (retryCount < maxRetries) {
                setTimeout(() => initAgentLeaderboardCharts(monthlyAgentData, topAgents, retryCount + 1), 100);
            }
        }

        // Listen for Livewire dispatched event when data updates (filters or tab switch)
        document.addEventListener('livewire:init', function() {
            Livewire.on('charts-data-updated', (event) => {
                const monthlyData = event.monthlyData;
                setTimeout(function() {
                    showSkeletonsAndHideCharts();
                    initCharts(monthlyData);
                }, 150);
            });

            Livewire.on('product-insights-charts-update', (event) => {
                setTimeout(function() {
                    initProductInsightsCharts(event.monthlyProductData, event.topProductsByRevenue);
                }, 150);
            });

            Livewire.on('agent-leaderboard-charts-update', (event) => {
                setTimeout(function() {
                    initAgentLeaderboardCharts(event.monthlyAgentData, event.topAgents);
                }, 150);
            });
        });

        // Reinitialize charts when Livewire navigates
        document.addEventListener('livewire:navigated', function() {
            setTimeout(function() {
                const monthlyData = @json($monthlyData);
                initCharts(monthlyData);
            }, 200);
        });

        // Initial load for active tab charts
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = @json($activeTab);
            if (activeTab === 'products') {
                const monthlyProductData = @json($monthlyProductData);
                const topProductsByRevenue = @json($topProductsByRevenue);
                initProductInsightsCharts(monthlyProductData, topProductsByRevenue);
            } else if (activeTab === 'agents') {
                const monthlyAgentData = @json($monthlyAgentData);
                const topAgents = @json($topAgents);
                initAgentLeaderboardCharts(monthlyAgentData, topAgents);
            }
        });
    </script>
</div>
