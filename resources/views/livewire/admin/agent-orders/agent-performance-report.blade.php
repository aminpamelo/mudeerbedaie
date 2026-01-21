<?php

use App\Models\Agent;
use App\Models\ProductOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public int $selectedYear;

    public string $agentType = '';

    public array $availableYears = [];

    public array $monthlyData = [];

    public array $summary = [];

    public array $agentTypeBreakdown = [];

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
            ->selectRaw("
                agents.type as agent_type,
                COUNT(*) as orders,
                SUM(product_orders.total_amount) as revenue
            ")
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

        // Listen for Livewire dispatched event when data updates
        document.addEventListener('livewire:init', function() {
            Livewire.on('charts-data-updated', (event) => {
                const monthlyData = event.monthlyData;
                initCharts(monthlyData);
            });
        });

        // Reinitialize charts when Livewire navigates
        document.addEventListener('livewire:navigated', function() {
            setTimeout(function() {
                const monthlyData = @json($monthlyData);
                initCharts(monthlyData);
            }, 200);
        });
    </script>
</div>
