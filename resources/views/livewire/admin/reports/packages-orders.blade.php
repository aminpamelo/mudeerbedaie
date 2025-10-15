<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    public int $selectedYear;
    public array $availableYears = [];
    public array $monthlyData = [];
    public array $summary = [];

    public function mount(): void
    {
        // Get available years from both package_purchases and product_orders
        $packageYears = DB::table('package_purchases')
            ->selectRaw('DISTINCT YEAR(created_at) as year')
            ->whereNotNull('created_at')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        $orderYears = DB::table('product_orders')
            ->selectRaw('DISTINCT YEAR(order_date) as year')
            ->whereNotNull('order_date')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        $this->availableYears = collect(array_merge($packageYears, $orderYears))
            ->unique()
            ->sort()
            ->reverse()
            ->values()
            ->toArray();

        // Default to current year or latest available year
        $this->selectedYear = !empty($this->availableYears)
            ? $this->availableYears[0]
            : (int) date('Y');

        $this->loadReportData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadReportData();
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
                'packages' => [
                    'count' => 0,
                    'revenue' => 0,
                    'items' => 0,
                ],
                'orders' => [
                    'count' => 0,
                    'revenue' => 0,
                    'items' => 0,
                ],
                'total_revenue' => 0,
                'total_items' => 0,
            ];
        }

        // Get package purchase data grouped by month
        $packageData = DB::table('package_purchases')
            ->selectRaw('
                MONTH(created_at) as month,
                COUNT(*) as purchase_count,
                SUM(amount_paid) as total_revenue,
                SUM((SELECT COUNT(*) FROM package_items WHERE package_items.package_id = package_purchases.package_id)) as total_items
            ')
            ->whereYear('created_at', $this->selectedYear)
            ->whereIn('status', ['completed', 'processing'])
            ->groupBy('month')
            ->get();

        foreach ($packageData as $data) {
            $month = $data->month;
            $this->monthlyData[$month]['packages'] = [
                'count' => $data->purchase_count,
                'revenue' => (float) $data->total_revenue,
                'items' => (int) $data->total_items,
            ];
        }

        // Get product order data grouped by month with item counts
        $orderData = DB::table('product_orders')
            ->selectRaw('
                MONTH(order_date) as month,
                COUNT(DISTINCT product_orders.id) as order_count,
                SUM(product_orders.total_amount) as total_revenue
            ')
            ->whereYear('order_date', $this->selectedYear)
            ->whereNotIn('status', ['cancelled', 'refunded', 'draft'])
            ->groupBy('month')
            ->get();

        // Get order items count separately
        $orderItemCounts = DB::table('product_order_items')
            ->join('product_orders', 'product_orders.id', '=', 'product_order_items.order_id')
            ->selectRaw('
                MONTH(product_orders.order_date) as month,
                COUNT(*) as total_items
            ')
            ->whereYear('product_orders.order_date', $this->selectedYear)
            ->whereNotIn('product_orders.status', ['cancelled', 'refunded', 'draft'])
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        foreach ($orderData as $data) {
            $month = $data->month;
            $itemCount = isset($orderItemCounts[$month]) ? (int) $orderItemCounts[$month]->total_items : 0;

            $this->monthlyData[$month]['orders'] = [
                'count' => $data->order_count,
                'revenue' => (float) $data->total_revenue,
                'items' => $itemCount,
            ];
        }

        // Calculate totals for each month
        foreach ($this->monthlyData as $month => $data) {
            $this->monthlyData[$month]['total_revenue'] =
                $data['packages']['revenue'] + $data['orders']['revenue'];
            $this->monthlyData[$month]['total_items'] =
                $data['packages']['items'] + $data['orders']['items'];
        }

        // Calculate summary statistics
        $this->calculateSummary();
    }

    private function calculateSummary(): void
    {
        $totalPackageRevenue = 0;
        $totalOrderRevenue = 0;
        $totalPackageCount = 0;
        $totalOrderCount = 0;
        $totalPackageItems = 0;
        $totalOrderItems = 0;

        foreach ($this->monthlyData as $data) {
            $totalPackageRevenue += $data['packages']['revenue'];
            $totalOrderRevenue += $data['orders']['revenue'];
            $totalPackageCount += $data['packages']['count'];
            $totalOrderCount += $data['orders']['count'];
            $totalPackageItems += $data['packages']['items'];
            $totalOrderItems += $data['orders']['items'];
        }

        $this->summary = [
            'total_revenue' => $totalPackageRevenue + $totalOrderRevenue,
            'package_revenue' => $totalPackageRevenue,
            'order_revenue' => $totalOrderRevenue,
            'total_packages' => $totalPackageCount,
            'total_orders' => $totalOrderCount,
            'total_items' => $totalPackageItems + $totalOrderItems,
            'avg_monthly_revenue' => ($totalPackageRevenue + $totalOrderRevenue) / 12,
        ];
    }

    public function exportCsv()
    {
        $filename = "packages-orders-report-{$this->selectedYear}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Month',
                'Package Sales Count',
                'Package Revenue',
                'Package Items',
                'Product Orders Count',
                'Product Orders Revenue',
                'Product Order Items',
                'Total Revenue',
                'Total Items'
            ]);

            // Data rows
            foreach ($this->monthlyData as $data) {
                fputcsv($file, [
                    $data['month_name'],
                    $data['packages']['count'],
                    number_format($data['packages']['revenue'], 2),
                    $data['packages']['items'],
                    $data['orders']['count'],
                    number_format($data['orders']['revenue'], 2),
                    $data['orders']['items'],
                    number_format($data['total_revenue'], 2),
                    $data['total_items'],
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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Package & Order Product Report</flux:heading>
                <flux:text class="mt-2">Monthly breakdown of package sales and product orders</flux:text>
            </div>

            <div class="flex items-center gap-3">
                {{-- Year Filter --}}
                <flux:select wire:model.live="selectedYear">
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </flux:select>

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
                        <flux:text class="text-sm font-medium text-gray-500">Total Revenue</flux:text>
                        <flux:heading size="lg" class="mt-1">RM {{ number_format($summary['total_revenue'] ?? 0, 2) }}</flux:heading>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100">
                        <flux:icon name="banknotes" class="h-6 w-6 text-green-600" />
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500">Package Sales</flux:text>
                        <flux:heading size="lg" class="mt-1">{{ number_format($summary['total_packages'] ?? 0) }}</flux:heading>
                        <flux:text class="text-xs text-gray-400">RM {{ number_format($summary['package_revenue'] ?? 0, 2) }}</flux:text>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100">
                        <flux:icon name="gift" class="h-6 w-6 text-blue-600" />
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500">Product Orders</flux:text>
                        <flux:heading size="lg" class="mt-1">{{ number_format($summary['total_orders'] ?? 0) }}</flux:heading>
                        <flux:text class="text-xs text-gray-400">RM {{ number_format($summary['order_revenue'] ?? 0, 2) }}</flux:text>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100">
                        <flux:icon name="shopping-bag" class="h-6 w-6 text-purple-600" />
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500">Avg Monthly Revenue</flux:text>
                        <flux:heading size="lg" class="mt-1">RM {{ number_format($summary['avg_monthly_revenue'] ?? 0, 2) }}</flux:heading>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-yellow-100">
                        <flux:icon name="chart-bar" class="h-6 w-6 text-yellow-600" />
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Charts Section --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Revenue Trend Chart --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Monthly Revenue Trend</flux:heading>
                    <flux:text class="mt-1 text-sm text-gray-500">Package sales vs product orders revenue over time</flux:text>
                </div>
                <div class="h-80">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </flux:card>

            {{-- Sales Comparison Chart --}}
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Sales Volume Comparison</flux:heading>
                    <flux:text class="mt-1 text-sm text-gray-500">Number of package sales vs product orders</flux:text>
                </div>
                <div class="h-80">
                    <canvas id="salesComparisonChart"></canvas>
                </div>
            </flux:card>
        </div>

        {{-- Monthly Data Table --}}
        <flux:card>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Month</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900">Package Sales</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900">Package Revenue</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900">Product Orders</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900">Order Revenue</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($monthlyData as $data)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $data['month_name'] }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">
                                    {{ number_format($data['packages']['count']) }}
                                    @if($data['packages']['items'] > 0)
                                        <span class="text-xs text-gray-400">({{ $data['packages']['items'] }} items)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">
                                    RM {{ number_format($data['packages']['revenue'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">
                                    {{ number_format($data['orders']['count']) }}
                                    @if($data['orders']['items'] > 0)
                                        <span class="text-xs text-gray-400">({{ $data['orders']['items'] }} items)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">
                                    RM {{ number_format($data['orders']['revenue'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">
                                    RM {{ number_format($data['total_revenue'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No data available for {{ $selectedYear }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                        <tr>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900">Total</td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">
                                {{ number_format($summary['total_packages'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">
                                RM {{ number_format($summary['package_revenue'] ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">
                                {{ number_format($summary['total_orders'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">
                                RM {{ number_format($summary['order_revenue'] ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">
                                RM {{ number_format($summary['total_revenue'] ?? 0, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </flux:card>

    </div>

    @vite('resources/js/reports-charts.js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const monthlyData = @json($monthlyData);

            if (typeof window.initializeCharts === 'function') {
                window.initializeCharts(monthlyData);
            }
        });
    </script>
</div>
