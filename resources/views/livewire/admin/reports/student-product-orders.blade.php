<?php

use App\Models\ProductOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public $selectedYear;

    public $selectedMonth = 'all';

    public $selectedStatus = 'all';

    public $topStudentsLimit = 10;

    public $topProductsLimit = 10;

    public $totalRevenue = 0;

    public $totalOrders = 0;

    public $totalStudents = 0;

    public $avgOrderValue = 0;

    public $totalItems = 0;

    public $avgItemsPerOrder = 0;

    public $topStudents = [];

    public $topProducts = [];

    public $ordersByStatus = [];

    public $monthlyTrend = [];

    public $recentOrders = [];

    public $months = [
        'all' => 'All Months',
        '1' => 'January',
        '2' => 'February',
        '3' => 'March',
        '4' => 'April',
        '5' => 'May',
        '6' => 'June',
        '7' => 'July',
        '8' => 'August',
        '9' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    ];

    public $statuses = [
        'all' => 'All Statuses',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    public function mount()
    {
        $this->selectedYear = now()->year;
        $this->loadReportData();
    }

    public function updatedSelectedYear()
    {
        $this->loadReportData();
    }

    public function updatedSelectedMonth()
    {
        $this->loadReportData();
    }

    public function updatedSelectedStatus()
    {
        $this->loadReportData();
    }

    public function loadReportData()
    {
        // Base query for filtering
        $baseQuery = ProductOrder::query()
            ->whereYear('order_date', $this->selectedYear)
            ->whereHas('student');

        // Apply month filter
        if ($this->selectedMonth !== 'all') {
            $baseQuery->whereMonth('order_date', $this->selectedMonth);
        }

        // Apply status filter (exclude cancelled and refunded from calculations unless specifically selected)
        if ($this->selectedStatus === 'all') {
            $baseQuery->whereNotIn('status', ['cancelled', 'refunded', 'draft']);
        } else {
            $baseQuery->where('status', $this->selectedStatus);
        }

        // Calculate summary statistics
        $this->calculateSummaryStats($baseQuery);

        // Load top students by spending
        $this->loadTopStudents($baseQuery);

        // Load top products by quantity sold
        $this->loadTopProducts($baseQuery);

        // Load orders by status distribution
        $this->loadOrdersByStatus();

        // Load monthly trend data
        $this->loadMonthlyTrend();

        // Load recent orders
        $this->loadRecentOrders($baseQuery);
    }

    protected function calculateSummaryStats($query)
    {
        $clonedQuery = clone $query;

        $stats = $clonedQuery->selectRaw('
            COUNT(DISTINCT id) as total_orders,
            COUNT(DISTINCT student_id) as total_students,
            SUM(total_amount) as total_revenue
        ')->first();

        $this->totalOrders = $stats->total_orders ?? 0;
        $this->totalStudents = $stats->total_students ?? 0;
        $this->totalRevenue = $stats->total_revenue ?? 0;
        $this->avgOrderValue = $this->totalOrders > 0 ? ($this->totalRevenue / $this->totalOrders) : 0;

        // Get total items sold
        $clonedQuery2 = clone $query;
        $itemStats = $clonedQuery2->with('items')
            ->get()
            ->reduce(function ($carry, $order) {
                return $carry + $order->items->sum('quantity_ordered');
            }, 0);

        $this->totalItems = $itemStats;
        $this->avgItemsPerOrder = $this->totalOrders > 0 ? ($this->totalItems / $this->totalOrders) : 0;
    }

    protected function loadTopStudents($query)
    {
        $clonedQuery = clone $query;

        $this->topStudents = $clonedQuery
            ->select('student_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(total_amount) as total_spent')
            ->groupBy('student_id')
            ->orderByDesc('total_spent')
            ->limit($this->topStudentsLimit)
            ->with('student:id,user_id')
            ->get()
            ->map(function ($order) {
                return [
                    'student' => $order->student,
                    'name' => $order->student->user->name ?? 'N/A',
                    'email' => $order->student->user->email ?? 'N/A',
                    'order_count' => $order->order_count,
                    'total_spent' => $order->total_spent,
                ];
            })
            ->toArray();
    }

    protected function loadTopProducts($query)
    {
        $clonedQuery = clone $query;

        $orderIds = $clonedQuery->pluck('id');

        $this->topProducts = DB::table('product_order_items')
            ->join('products', 'products.id', '=', 'product_order_items.product_id')
            ->whereIn('product_order_items.order_id', $orderIds)
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(product_order_items.quantity_ordered) as total_quantity')
            ->selectRaw('SUM(product_order_items.quantity_ordered * product_order_items.unit_price) as total_revenue')
            ->selectRaw('COUNT(DISTINCT product_order_items.order_id) as order_count')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit($this->topProductsLimit)
            ->get()
            ->map(function ($product) {
                return [
                    'name' => $product->name,
                    'total_quantity' => $product->total_quantity,
                    'total_revenue' => $product->total_revenue,
                    'order_count' => $product->order_count,
                ];
            })
            ->toArray();
    }

    protected function loadOrdersByStatus()
    {
        $this->ordersByStatus = ProductOrder::query()
            ->whereYear('order_date', $this->selectedYear)
            ->when($this->selectedMonth !== 'all', fn ($q) => $q->whereMonth('order_date', $this->selectedMonth))
            ->whereHas('student')
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('status')
            ->get()
            ->toArray();
    }

    protected function loadMonthlyTrend()
    {
        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "CAST(strftime('%m', order_date) AS INTEGER)"
            : 'MONTH(order_date)';

        $monthlyData = ProductOrder::query()
            ->whereYear('order_date', $this->selectedYear)
            ->whereHas('student')
            ->whereNotIn('status', ['cancelled', 'refunded', 'draft'])
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(total_amount) as revenue')
            ->selectRaw('COUNT(DISTINCT student_id) as student_count')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Initialize all 12 months with zero values
        $this->monthlyTrend = [];
        for ($i = 1; $i <= 12; $i++) {
            $data = $monthlyData->get($i);
            $this->monthlyTrend[] = [
                'month' => $i,
                'month_name' => Carbon::create()->month($i)->format('M'),
                'order_count' => $data->order_count ?? 0,
                'revenue' => $data->revenue ?? 0,
                'student_count' => $data->student_count ?? 0,
            ];
        }
    }

    protected function loadRecentOrders($query)
    {
        $clonedQuery = clone $query;

        $this->recentOrders = $clonedQuery
            ->with(['student.user:id,name,email', 'items'])
            ->orderBy('order_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'student_name' => $order->student->user->name ?? 'N/A',
                    'order_date' => $order->order_date,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'items_count' => $order->items->count(),
                ];
            })
            ->toArray();
    }

    public function exportCsv()
    {
        $filename = "student-product-orders-{$this->selectedYear}";
        if ($this->selectedMonth !== 'all') {
            $filename .= "-{$this->months[$this->selectedMonth]}";
        }
        $filename .= '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, ['Student Product Order Report']);
            fputcsv($file, ['Year', $this->selectedYear]);
            fputcsv($file, ['Month', $this->months[$this->selectedMonth]]);
            fputcsv($file, ['Status', $this->statuses[$this->selectedStatus]]);
            fputcsv($file, []);

            // Summary
            fputcsv($file, ['Summary Statistics']);
            fputcsv($file, ['Total Revenue', 'RM '.number_format($this->totalRevenue, 2)]);
            fputcsv($file, ['Total Orders', $this->totalOrders]);
            fputcsv($file, ['Total Students', $this->totalStudents]);
            fputcsv($file, ['Average Order Value', 'RM '.number_format($this->avgOrderValue, 2)]);
            fputcsv($file, ['Total Items Sold', $this->totalItems]);
            fputcsv($file, ['Average Items per Order', number_format($this->avgItemsPerOrder, 2)]);
            fputcsv($file, []);

            // Top Students
            fputcsv($file, ['Top Students by Spending']);
            fputcsv($file, ['Rank', 'Student Name', 'Email', 'Orders', 'Total Spent']);
            foreach ($this->topStudents as $index => $student) {
                fputcsv($file, [
                    $index + 1,
                    $student['name'],
                    $student['email'],
                    $student['order_count'],
                    'RM '.number_format($student['total_spent'], 2),
                ]);
            }
            fputcsv($file, []);

            // Top Products
            fputcsv($file, ['Top Products by Quantity Sold']);
            fputcsv($file, ['Rank', 'Product Name', 'Quantity Sold', 'Revenue', 'Orders']);
            foreach ($this->topProducts as $index => $product) {
                fputcsv($file, [
                    $index + 1,
                    $product['name'],
                    $product['total_quantity'],
                    'RM '.number_format($product['total_revenue'], 2),
                    $product['order_count'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Student Product Order Report</flux:heading>
            <flux:text class="mt-2">Comprehensive statistics and insights about student product orders</flux:text>
        </div>
        <flux:button wire:click="exportCsv" icon="arrow-down-tray">Export CSV</flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-4">
        <div class="w-40">
            <flux:select wire:model.live="selectedYear" label="Year">
                @for ($year = now()->year; $year >= 2020; $year--)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endfor
            </flux:select>
        </div>
        <div class="w-48">
            <flux:select wire:model.live="selectedMonth" label="Month">
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-48">
            <flux:select wire:model.live="selectedStatus" label="Status">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Summary Stats Grid -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">RM {{ number_format($totalRevenue, 2) }}</flux:heading>
                <div class="rounded-lg bg-green-100 p-2">
                    <flux:icon name="banknotes" class="h-6 w-6 text-green-600" />
                </div>
            </div>
            <flux:text>Total Revenue</flux:text>
            <flux:subheading class="text-xs text-gray-500">From all student orders</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ number_format($totalOrders) }}</flux:heading>
                <div class="rounded-lg bg-blue-100 p-2">
                    <flux:icon name="shopping-cart" class="h-6 w-6 text-blue-600" />
                </div>
            </div>
            <flux:text>Total Orders</flux:text>
            <flux:subheading class="text-xs text-gray-500">Number of orders placed</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ number_format($totalStudents) }}</flux:heading>
                <div class="rounded-lg bg-purple-100 p-2">
                    <flux:icon name="users" class="h-6 w-6 text-purple-600" />
                </div>
            </div>
            <flux:text>Active Students</flux:text>
            <flux:subheading class="text-xs text-gray-500">Students who ordered</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">RM {{ number_format($avgOrderValue, 2) }}</flux:heading>
                <div class="rounded-lg bg-yellow-100 p-2">
                    <flux:icon name="calculator" class="h-6 w-6 text-yellow-600" />
                </div>
            </div>
            <flux:text>Avg Order Value</flux:text>
            <flux:subheading class="text-xs text-gray-500">Average per order</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ number_format($totalItems) }}</flux:heading>
                <div class="rounded-lg bg-red-100 p-2">
                    <flux:icon name="cube" class="h-6 w-6 text-red-600" />
                </div>
            </div>
            <flux:text>Items Sold</flux:text>
            <flux:subheading class="text-xs text-gray-500">Total product units</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ number_format($avgItemsPerOrder, 1) }}</flux:heading>
                <div class="rounded-lg bg-indigo-100 p-2">
                    <flux:icon name="shopping-bag" class="h-6 w-6 text-indigo-600" />
                </div>
            </div>
            <flux:text>Avg Items/Order</flux:text>
            <flux:subheading class="text-xs text-gray-500">Items per order</flux:subheading>
        </flux:card>
    </div>

    <!-- Charts Row -->
    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <!-- Revenue & Orders Trend Chart -->
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Monthly Revenue & Orders Trend</flux:heading>
                <flux:text>Revenue and order volume throughout the year</flux:text>
            </div>
            <div style="height: 300px;">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </flux:card>

        <!-- Student Activity Chart -->
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Monthly Student Activity</flux:heading>
                <flux:text>Unique students making purchases each month</flux:text>
            </div>
            <div style="height: 300px;">
                <canvas id="studentActivityChart"></canvas>
            </div>
        </flux:card>
    </div>

    <!-- Order Status Distribution -->
    @if (count($ordersByStatus) > 0)
        <div class="mb-6">
            <flux:card>
                <div class="mb-4">
                    <flux:heading size="lg">Order Status Distribution</flux:heading>
                    <flux:text>Breakdown of orders by current status</flux:text>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($ordersByStatus as $statusData)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:heading size="sm">{{ $statusData['count'] }} orders</flux:heading>
                                    <flux:text class="capitalize">{{ $statusData['status'] }}</flux:text>
                                </div>
                                <flux:badge size="sm" class="capitalize">
                                    {{ $statusData['status'] }}
                                </flux:badge>
                            </div>
                            <flux:text class="mt-2 text-xs text-gray-500">
                                RM {{ number_format($statusData['revenue'], 2) }}
                            </flux:text>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        </div>
    @endif

    <!-- Top Students and Products Grid -->
    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <!-- Top Students -->
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Top Students by Spending</flux:heading>
                <flux:text>Students with highest purchase amounts</flux:text>
            </div>
            @if (count($topStudents) > 0)
                <div class="space-y-3">
                    @foreach ($topStudents as $index => $student)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <flux:heading size="sm">{{ $student['name'] }}</flux:heading>
                                    <flux:text class="text-xs text-gray-500">{{ $student['email'] }}</flux:text>
                                </div>
                            </div>
                            <div class="text-right">
                                <flux:heading size="sm">RM {{ number_format($student['total_spent'], 2) }}</flux:heading>
                                <flux:text class="text-xs text-gray-500">{{ $student['order_count'] }} orders</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text>No student data available for the selected period.</flux:text>
            @endif
        </flux:card>

        <!-- Top Products -->
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Top Products by Quantity</flux:heading>
                <flux:text>Most popular products among students</flux:text>
            </div>
            @if (count($topProducts) > 0)
                <div class="space-y-3">
                    @foreach ($topProducts as $index => $product)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-sm font-semibold text-purple-600">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <flux:heading size="sm">{{ $product['name'] }}</flux:heading>
                                    <flux:text class="text-xs text-gray-500">{{ $product['order_count'] }} orders</flux:text>
                                </div>
                            </div>
                            <div class="text-right">
                                <flux:heading size="sm">{{ $product['total_quantity'] }} units</flux:heading>
                                <flux:text class="text-xs text-gray-500">RM {{ number_format($product['total_revenue'], 2) }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text>No product data available for the selected period.</flux:text>
            @endif
        </flux:card>
    </div>

    <!-- Recent Orders -->
    @if (count($recentOrders) > 0)
        <flux:card>
            <div class="mb-4">
                <flux:heading size="lg">Recent Orders</flux:heading>
                <flux:text>Latest student product orders</flux:text>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Student</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Items</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($recentOrders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $order['order_number'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ $order['student_name'] }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($order['order_date'])->format('d M Y') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                    {{ $order['items_count'] }} items
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <flux:badge size="sm" class="capitalize">
                                        {{ $order['status'] }}
                                    </flux:badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">
                                    RM {{ number_format($order['total_amount'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    @vite('resources/js/reports-charts.js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const monthlyData = @json($monthlyTrend);

            if (typeof window.initializeStudentOrderCharts === 'function') {
                window.initializeStudentOrderCharts(monthlyData);
            }
        });
    </script>
</div>
