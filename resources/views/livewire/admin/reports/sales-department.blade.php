<?php

use App\Models\ProductOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $activeTab = 'reports';

    public string $selectedSalesperson = 'all';

    public string $selectedPeriod = 'this_month';

    public string $selectedStatus = 'all';

    public string $search = '';

    public int $selectedYear;

    public array $summary = [];

    public array $salespersonPerformance = [];

    public array $salespersonPerformanceByVolume = [];

    public array $monthlyData = [];

    public array $salespersonOptions = [];

    public array $statusBreakdown = [];

    public float $maxMonthlyRevenue = 0;

    public array $monthlyPivotData = [];

    public array $pivotSalespersons = [];

    public function mount(): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'class_admin', 'sales'])) {
            abort(403, 'Access denied');
        }

        $this->selectedYear = (int) date('Y');
        $this->loadSalespersonOptions();
        $this->loadReportData();
    }

    public function updatedSelectedSalesperson(): void
    {
        $this->resetPage();
        $this->loadReportData();
    }

    public function updatedSelectedPeriod(): void
    {
        $this->resetPage();
        $this->loadReportData();
    }

    public function updatedSelectedStatus(): void
    {
        $this->resetPage();
        $this->loadReportData();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadReportData();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    private function loadSalespersonOptions(): void
    {
        $salespersonIds = ProductOrder::query()
            ->where('source', 'pos')
            ->whereNotNull('metadata')
            ->get()
            ->pluck('metadata.salesperson_id')
            ->filter()
            ->unique()
            ->values();

        $this->salespersonOptions = User::whereIn('id', $salespersonIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = ProductOrder::query()->where('source', 'pos');

        if ($this->selectedSalesperson !== 'all') {
            $query->whereJsonContains('metadata->salesperson_id', (int) $this->selectedSalesperson);
        }

        if ($this->selectedStatus !== 'all') {
            if ($this->selectedStatus === 'paid') {
                $query->whereNotNull('paid_time');
            } elseif ($this->selectedStatus === 'pending') {
                $query->whereNull('paid_time')->where('status', '!=', 'cancelled');
            } elseif ($this->selectedStatus === 'cancelled') {
                $query->where('status', 'cancelled');
            }
        }

        if ($this->selectedPeriod !== 'all') {
            match ($this->selectedPeriod) {
                'today' => $query->whereDate('order_date', today()),
                'this_week' => $query->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()]),
                'this_month' => $query->whereBetween('order_date', [now()->startOfMonth(), now()->endOfMonth()]),
                default => null,
            };
        }

        return $query;
    }

    private function loadReportData(): void
    {
        $this->loadSummary();
        $this->loadStatusBreakdown();
        $this->loadSalespersonPerformance();
        $this->loadMonthlyData();
        $this->loadMonthlyPivotData();
        $this->dispatch('sales-dept-charts-update', monthlyData: $this->monthlyData);
    }

    private function loadSummary(): void
    {
        $paidQuery = $this->baseQuery()->whereNotNull('paid_time');
        $orders = $paidQuery->with('items')->get();

        $totalRevenue = (float) $orders->sum('total_amount');
        $totalOrders = $orders->count();
        $totalItems = (int) $orders->sum(fn ($order) => $order->items->sum('quantity_ordered'));

        $this->summary = [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'avg_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            'total_items' => $totalItems,
        ];
    }

    private function loadStatusBreakdown(): void
    {
        $allOrders = $this->baseQuery()->get();

        $paid = $allOrders->filter(fn ($o) => $o->paid_time !== null);
        $pending = $allOrders->filter(fn ($o) => $o->paid_time === null && $o->status !== 'cancelled');
        $cancelled = $allOrders->filter(fn ($o) => $o->status === 'cancelled');

        $this->statusBreakdown = [
            [
                'status' => 'Paid',
                'count' => $paid->count(),
                'revenue' => (float) $paid->sum('total_amount'),
                'color' => 'green',
            ],
            [
                'status' => 'Pending',
                'count' => $pending->count(),
                'revenue' => (float) $pending->sum('total_amount'),
                'color' => 'yellow',
            ],
            [
                'status' => 'Cancelled',
                'count' => $cancelled->count(),
                'revenue' => (float) $cancelled->sum('total_amount'),
                'color' => 'red',
            ],
        ];
    }

    private function loadSalespersonPerformance(): void
    {
        $orders = $this->baseQuery()
            ->whereNotNull('paid_time')
            ->get();

        $grouped = $orders->groupBy(fn ($order) => $order->metadata['salesperson_id'] ?? 'unknown');

        $performance = [];
        foreach ($grouped as $spId => $spOrders) {
            if ($spId === 'unknown') {
                continue;
            }

            $revenue = (float) $spOrders->sum('total_amount');
            $count = $spOrders->count();

            $performance[] = [
                'salesperson_id' => $spId,
                'salesperson_name' => $spOrders->first()->metadata['salesperson_name'] ?? 'Unknown',
                'sales_count' => $count,
                'revenue' => $revenue,
                'avg_order_value' => $count > 0 ? round($revenue / $count, 2) : 0,
                'last_sale' => $spOrders->max('order_date'),
            ];
        }

        $byRevenue = $performance;
        usort($byRevenue, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $this->salespersonPerformance = $byRevenue;

        $byVolume = $performance;
        usort($byVolume, fn ($a, $b) => $b['sales_count'] <=> $a['sales_count']);
        $this->salespersonPerformanceByVolume = $byVolume;
    }

    private function loadMonthlyData(): void
    {
        $driver = DB::connection()->getDriverName();
        $isSqlite = $driver === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', order_date) AS INTEGER)" : 'MONTH(order_date)';
        $monthGroup = $isSqlite ? "strftime('%m', order_date)" : 'MONTH(order_date)';

        $query = ProductOrder::query()
            ->where('source', 'pos')
            ->whereNotNull('paid_time')
            ->whereYear('order_date', $this->selectedYear);

        if ($this->selectedSalesperson !== 'all') {
            $query->whereJsonContains('metadata->salesperson_id', (int) $this->selectedSalesperson);
        }

        if ($this->selectedStatus === 'paid') {
            $query->whereNotNull('paid_time');
        } elseif ($this->selectedStatus === 'pending') {
            $query->whereNull('paid_time')->where('status', '!=', 'cancelled');
        } elseif ($this->selectedStatus === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $stats = $query->selectRaw("
                {$monthExpr} as month_number,
                COUNT(*) as sales_count,
                COALESCE(SUM(total_amount), 0) as revenue
            ")
            ->groupByRaw($monthGroup)
            ->get()
            ->keyBy('month_number');

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $this->monthlyData = [];
        $this->maxMonthlyRevenue = 0;

        for ($m = 1; $m <= 12; $m++) {
            $revenue = round((float) ($stats[$m]->revenue ?? 0), 2);
            $this->monthlyData[] = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'sales_count' => (int) ($stats[$m]->sales_count ?? 0),
                'revenue' => $revenue,
            ];

            if ($revenue > $this->maxMonthlyRevenue) {
                $this->maxMonthlyRevenue = $revenue;
            }
        }
    }

    private function loadMonthlyPivotData(): void
    {
        $query = ProductOrder::query()
            ->where('source', 'pos')
            ->whereNotNull('paid_time')
            ->whereYear('order_date', $this->selectedYear);

        if ($this->selectedSalesperson !== 'all') {
            $query->whereJsonContains('metadata->salesperson_id', (int) $this->selectedSalesperson);
        }

        if ($this->selectedStatus === 'paid') {
            $query->whereNotNull('paid_time');
        } elseif ($this->selectedStatus === 'pending') {
            $query->whereNull('paid_time')->where('status', '!=', 'cancelled');
        } elseif ($this->selectedStatus === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $orders = $query->get();

        $salespersonMap = [];
        $monthData = [];

        foreach ($orders as $order) {
            $spId = $order->metadata['salesperson_id'] ?? null;
            $spName = $order->metadata['salesperson_name'] ?? 'Unknown';
            if (! $spId) {
                continue;
            }

            $month = (int) $order->order_date->format('m');

            if (! isset($salespersonMap[$spId])) {
                $salespersonMap[$spId] = $spName;
            }

            if (! isset($monthData[$month][$spId])) {
                $monthData[$month][$spId] = ['sales_count' => 0, 'revenue' => 0];
            }

            $monthData[$month][$spId]['sales_count']++;
            $monthData[$month][$spId]['revenue'] += (float) $order->total_amount;
        }

        asort($salespersonMap);
        $this->pivotSalespersons = $salespersonMap;

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $this->monthlyPivotData = [];

        for ($m = 1; $m <= 12; $m++) {
            $row = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'salespersons' => [],
                'total_sales' => 0,
                'total_revenue' => 0,
            ];

            foreach ($salespersonMap as $spId => $spName) {
                $spData = $monthData[$m][$spId] ?? ['sales_count' => 0, 'revenue' => 0];
                $row['salespersons'][$spId] = $spData;
                $row['total_sales'] += $spData['sales_count'];
                $row['total_revenue'] += $spData['revenue'];
            }

            $this->monthlyPivotData[] = $row;
        }
    }

    public function exportCsv()
    {
        $query = $this->baseQuery()
            ->with(['items', 'customer'])
            ->latest('order_date');

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->get();

        $filename = 'sales-department-report-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Order #', 'Date', 'Customer', 'Salesperson', 'Items', 'Total (RM)',
                'Payment Method', 'Status',
            ]);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->order_date?->format('Y-m-d H:i'),
                    $order->getCustomerName(),
                    $order->metadata['salesperson_name'] ?? 'Unknown',
                    $order->items->sum('quantity_ordered'),
                    number_format((float) $order->total_amount, 2),
                    $order->payment_method ?? '-',
                    $order->paid_time ? 'Paid' : ($order->status === 'cancelled' ? 'Cancelled' : 'Pending'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function rendering(\Illuminate\View\View $view): void
    {
        $query = $this->baseQuery()
            ->with(['items', 'customer'])
            ->latest('order_date');

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $view->with('salesHistory', $query->paginate(20));
    }
}; ?>

<div>
    <div class="space-y-6 p-6 lg:p-8">

        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Sales Department Report</flux:heading>
                <flux:text class="mt-2">POS sales data across all salespersons</flux:text>
            </div>

            <flux:button wire:click="exportCsv" variant="outline" icon="arrow-down-tray">
                Export CSV
            </flux:button>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-zinc-800">
            <button
                wire:click="setActiveTab('reports')"
                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'reports' ? 'bg-white text-gray-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                Reports
            </button>
            <button
                wire:click="setActiveTab('history')"
                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'history' ? 'bg-white text-gray-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                Sales History
            </button>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="selectedSalesperson" label="Salesperson">
                    <option value="all">All Salespersons</option>
                    @foreach($salespersonOptions as $sp)
                        <option value="{{ $sp['id'] }}">{{ $sp['name'] }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-full sm:w-40">
                <flux:select wire:model.live="selectedPeriod" label="Period">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                </flux:select>
            </div>

            <div class="w-full sm:w-36">
                <flux:select wire:model.live="selectedStatus" label="Status">
                    <option value="all">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="cancelled">Cancelled</option>
                </flux:select>
            </div>

            @if($activeTab === 'history')
                <div class="w-full sm:min-w-[200px] sm:flex-1">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Search order # or customer..." icon="magnifying-glass" />
                </div>
            @endif

            @if($activeTab === 'reports')
                <div class="w-full sm:w-32">
                    <flux:select wire:model.live="selectedYear" label="Year">
                        @for($y = now()->year; $y >= now()->year - 3; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </flux:select>
                </div>
            @endif
        </div>

        {{-- Sales History Tab --}}
        @if($activeTab === 'history')
            <flux:card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-zinc-800">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Order #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Salesperson</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Items</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Payment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                            @forelse($salesHistory as $order)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800" wire:key="order-{{ $order->id }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $order->order_number }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ $order->order_date?->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-zinc-100">{{ $order->getCustomerName() }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-zinc-300">{{ $order->metadata['salesperson_name'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-zinc-400">{{ $order->items->sum('quantity_ordered') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format((float) $order->total_amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-zinc-300">{{ ucfirst($order->payment_method ?? '-') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if($order->paid_time)
                                            <flux:badge color="green" size="sm">Paid</flux:badge>
                                        @elseif($order->status === 'cancelled')
                                            <flux:badge color="red" size="sm">Cancelled</flux:badge>
                                        @else
                                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-zinc-400">
                                        No sales found matching your filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($salesHistory->hasPages())
                    <div class="mt-4 border-t border-gray-200 px-4 pt-4 dark:border-zinc-700">
                        {{ $salesHistory->links() }}
                    </div>
                @endif
            </flux:card>
        @endif

        {{-- Reports Tab --}}
        @if($activeTab === 'reports')
            {{-- Summary Stats Grid --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">RM {{ number_format($summary['total_revenue'] ?? 0, 2) }}</flux:heading>
                        <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                            <flux:icon name="banknotes" class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                    <flux:text>Total Revenue</flux:text>
                    <flux:subheading class="text-xs text-gray-500 dark:text-zinc-400">From paid POS orders</flux:subheading>
                </flux:card>

                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ number_format($summary['total_orders'] ?? 0) }}</flux:heading>
                        <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                            <flux:icon name="shopping-cart" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                    <flux:text>Total Orders</flux:text>
                    <flux:subheading class="text-xs text-gray-500 dark:text-zinc-400">Number of paid orders</flux:subheading>
                </flux:card>

                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">RM {{ number_format($summary['avg_order_value'] ?? 0, 2) }}</flux:heading>
                        <div class="rounded-lg bg-yellow-100 p-2 dark:bg-yellow-900/30">
                            <flux:icon name="calculator" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                    </div>
                    <flux:text>Avg Order Value</flux:text>
                    <flux:subheading class="text-xs text-gray-500 dark:text-zinc-400">Average per order</flux:subheading>
                </flux:card>

                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ number_format($summary['total_items'] ?? 0) }}</flux:heading>
                        <div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                            <flux:icon name="cube" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                    <flux:text>Items Sold</flux:text>
                    <flux:subheading class="text-xs text-gray-500 dark:text-zinc-400">Total product units</flux:subheading>
                </flux:card>
            </div>

            {{-- Charts Row --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Monthly Revenue & Sales Trend &mdash; {{ $selectedYear }}</flux:heading>
                        <flux:text>Revenue and sales volume throughout the year</flux:text>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="salesDeptRevenueChart"></canvas>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Monthly Revenue Breakdown &mdash; {{ $selectedYear }}</flux:heading>
                        <flux:text>Revenue per month</flux:text>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="salesDeptMonthlyBarChart"></canvas>
                    </div>
                </flux:card>
            </div>

            {{-- Order Status Distribution --}}
            @if(collect($statusBreakdown)->sum('count') > 0)
                <flux:card>
                    <div class="mb-4">
                        <flux:heading size="lg">Order Status Distribution</flux:heading>
                        <flux:text>Breakdown of POS orders by status</flux:text>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        @foreach($statusBreakdown as $statusData)
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-zinc-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:heading size="sm">{{ $statusData['count'] }} orders</flux:heading>
                                        <flux:text>{{ $statusData['status'] }}</flux:text>
                                    </div>
                                    <flux:badge color="{{ $statusData['color'] }}" size="sm">
                                        {{ $statusData['status'] }}
                                    </flux:badge>
                                </div>
                                <flux:text class="mt-2 text-xs text-gray-500 dark:text-zinc-400">
                                    RM {{ number_format($statusData['revenue'], 2) }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- Salesperson Performance (Ranked Cards) --}}
            @if(count($salespersonPerformance) > 0)
                <div class="grid gap-6 lg:grid-cols-2">
                    {{-- Top by Revenue --}}
                    <flux:card>
                        <div class="mb-4">
                            <flux:heading size="lg">Top Salespersons by Revenue</flux:heading>
                            <flux:text>Ranked by total sales revenue</flux:text>
                        </div>
                        @if(count($salespersonPerformance) > 0)
                            <div class="space-y-3">
                                @foreach($salespersonPerformance as $index => $sp)
                                    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-zinc-700">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-sm font-semibold text-green-600 dark:bg-green-900/30 dark:text-green-400">
                                                {{ $index + 1 }}
                                            </div>
                                            <div>
                                                <flux:heading size="sm">{{ $sp['salesperson_name'] }}</flux:heading>
                                                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ $sp['sales_count'] }} sales &middot; Avg RM {{ number_format($sp['avg_order_value'], 2) }}</flux:text>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <flux:heading size="sm">RM {{ number_format($sp['revenue'], 2) }}</flux:heading>
                                            <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Last: {{ $sp['last_sale'] ? \Carbon\Carbon::parse($sp['last_sale'])->format('d M Y') : '-' }}</flux:text>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <flux:text>No salesperson data available.</flux:text>
                        @endif
                    </flux:card>

                    {{-- Top by Volume --}}
                    <flux:card>
                        <div class="mb-4">
                            <flux:heading size="lg">Top Salespersons by Volume</flux:heading>
                            <flux:text>Ranked by number of sales</flux:text>
                        </div>
                        @if(count($salespersonPerformanceByVolume) > 0)
                            <div class="space-y-3">
                                @foreach($salespersonPerformanceByVolume as $index => $sp)
                                    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-zinc-700">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                {{ $index + 1 }}
                                            </div>
                                            <div>
                                                <flux:heading size="sm">{{ $sp['salesperson_name'] }}</flux:heading>
                                                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">RM {{ number_format($sp['revenue'], 2) }} revenue</flux:text>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <flux:heading size="sm">{{ $sp['sales_count'] }} sales</flux:heading>
                                            <flux:text class="text-xs text-gray-500 dark:text-zinc-400">Avg RM {{ number_format($sp['avg_order_value'], 2) }}</flux:text>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <flux:text>No salesperson data available.</flux:text>
                        @endif
                    </flux:card>
                </div>
            @endif

            {{-- Monthly Breakdown with Expandable Salesperson Details --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Monthly Breakdown by Salesperson &mdash; {{ $selectedYear }}</flux:heading>
                    <flux:text>Click on a month to see per-salesperson breakdown</flux:text>
                </div>
                <div class="overflow-x-auto" x-data="{ expanded: {} }">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-zinc-800">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Month</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Sales</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                            @foreach($monthlyPivotData as $month)
                                {{-- Main month row --}}
                                <tr
                                    class="cursor-pointer transition-colors {{ $month['total_sales'] > 0 ? 'hover:bg-gray-50 dark:hover:bg-zinc-800' : 'opacity-40' }}"
                                    @click="expanded[{{ $month['month'] }}] = !expanded[{{ $month['month'] }}]"
                                    wire:key="pivot-row-{{ $month['month'] }}"
                                >
                                    <td class="w-10 px-4 py-3 text-center">
                                        @if($month['total_sales'] > 0)
                                            <flux:icon
                                                name="chevron-right"
                                                class="h-4 w-4 text-gray-400 transition-transform duration-200 dark:text-zinc-500"
                                                x-bind:class="expanded[{{ $month['month'] }}] ? 'rotate-90' : ''"
                                            />
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $month['month_name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">{{ number_format($month['total_sales']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($month['total_revenue'], 2) }}</td>
                                </tr>

                                {{-- Expanded salesperson rows --}}
                                @if($month['total_sales'] > 0)
                                    <tr x-show="expanded[{{ $month['month'] }}]" x-collapse wire:key="pivot-expand-{{ $month['month'] }}">
                                        <td colspan="4" class="p-0">
                                            <div class="border-l-4 border-blue-200 bg-blue-50/50 px-4 py-2 dark:border-blue-700 dark:bg-blue-950/30">
                                                <div class="space-y-2">
                                                    @foreach($pivotSalespersons as $spId => $spName)
                                                        @php
                                                            $spData = $month['salespersons'][$spId] ?? ['sales_count' => 0, 'revenue' => 0];
                                                        @endphp
                                                        @if($spData['sales_count'] > 0)
                                                            <div class="flex items-center justify-between rounded-lg bg-white px-4 py-2.5 shadow-sm dark:bg-zinc-800" wire:key="pivot-sp-{{ $month['month'] }}-{{ $spId }}">
                                                                <div class="flex items-center gap-3">
                                                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                                        <flux:icon name="user" class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                                                    </div>
                                                                    <span class="text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $spName }}</span>
                                                                </div>
                                                                <div class="flex items-center gap-6">
                                                                    <div class="text-right">
                                                                        <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $spData['sales_count'] }} sales</div>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <div class="text-sm font-semibold text-gray-900 dark:text-zinc-100">RM {{ number_format($spData['revenue'], 2) }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-300 bg-gray-50 dark:border-zinc-600 dark:bg-zinc-800">
                            <tr>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-zinc-100">Total</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">{{ number_format(collect($monthlyPivotData)->sum('total_sales')) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-zinc-100">RM {{ number_format(collect($monthlyPivotData)->sum('total_revenue'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </flux:card>
        @endif

    </div>

    @if($activeTab === 'reports')
        @vite('resources/js/reports-charts.js')
        <script>
            function renderSalesDeptCharts(monthlyData) {
                if (typeof window.initializeSalesDeptCharts === 'function') {
                    window.initializeSalesDeptCharts(monthlyData);
                }
            }

            // Initial render with current data
            const initialData = @json($monthlyData);

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => renderSalesDeptCharts(initialData));
            } else {
                setTimeout(() => renderSalesDeptCharts(initialData), 50);
            }

            // Re-render after Livewire navigations
            document.addEventListener('livewire:navigated', () => renderSalesDeptCharts(initialData));

            // Re-render when filters change (dispatched from PHP)
            document.addEventListener('livewire:init', () => {
                Livewire.on('sales-dept-charts-update', (event) => {
                    setTimeout(() => renderSalesDeptCharts(event.monthlyData), 50);
                });
            });
        </script>
    @endif
</div>
