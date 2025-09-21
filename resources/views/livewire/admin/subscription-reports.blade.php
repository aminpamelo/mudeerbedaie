<?php
use App\Services\SubscriptionAnalyticsService;
use App\Models\Course;
use Livewire\Volt\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

new class extends Component {
    public string $selectedCourse = '';
    public string $selectedYear = '';
    public string $selectedMonth = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $subscriptionStatus = '';
    public string $paymentStatus = '';
    public string $reportType = 'overview';

    public function mount()
    {
        // Default to current year
        $this->selectedYear = now()->year;
    }

    private function getAnalyticsService(): SubscriptionAnalyticsService
    {
        return app(SubscriptionAnalyticsService::class);
    }

    public function updatedReportType()
    {
        // Clear other filters when report type changes
        $this->reset(['selectedCourse', 'selectedMonth', 'subscriptionStatus', 'paymentStatus']);
    }

    public function with(): array
    {
        $filters = $this->buildFilters();

        $data = [
            'courses' => Course::orderBy('name')->get(),
            'availableYears' => $this->getAvailableYears(),
            'filters' => $filters,
        ];

        // Load data based on report type
        $analyticsService = $this->getAnalyticsService();

        switch ($this->reportType) {
            case 'overview':
                $data = array_merge($data, [
                    'overview' => $analyticsService->getOverviewMetrics($filters),
                    'statusDistribution' => $analyticsService->getSubscriptionStatusDistribution($filters),
                ]);
                break;

            case 'revenue':
                $data = array_merge($data, [
                    'revenue' => $analyticsService->getRevenueMetrics($filters),
                ]);
                break;

            case 'courses':
                $data = array_merge($data, [
                    'coursePerformance' => $analyticsService->getCoursePerformance($filters),
                ]);
                break;

            case 'payments':
                $data = array_merge($data, [
                    'paymentAnalytics' => $analyticsService->getPaymentAnalytics($filters),
                ]);
                break;

            case 'growth':
                $data = array_merge($data, [
                    'growth' => $analyticsService->getGrowthMetrics($filters),
                ]);
                break;
        }

        return $data;
    }

    public function exportReport()
    {
        $filters = $this->buildFilters();
        $reportData = $this->getAnalyticsService()->exportReportData($filters);

        $csvData = [];

        // Header
        $csvData[] = ['Subscription Report Export'];
        $csvData[] = ['Generated on: ' . now()->format('Y-m-d H:i:s')];
        $csvData[] = ['Filters Applied: ' . $this->getFilterSummary()];
        $csvData[] = [];

        // Overview metrics
        $csvData[] = ['OVERVIEW METRICS'];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Total Subscriptions', $reportData['overview']['total_subscriptions']];
        $csvData[] = ['Active Subscriptions', $reportData['overview']['active_subscriptions']];
        $csvData[] = ['Monthly Revenue', 'RM ' . number_format($reportData['overview']['monthly_revenue'], 2)];
        $csvData[] = ['Total Revenue', 'RM ' . number_format($reportData['overview']['total_revenue'], 2)];
        $csvData[] = ['Churn Rate', $reportData['overview']['churn_rate'] . '%'];
        $csvData[] = ['Payment Success Rate', $reportData['overview']['payment_success_rate'] . '%'];
        $csvData[] = [];

        // Course performance
        if (!empty($reportData['course_performance'])) {
            $csvData[] = ['COURSE PERFORMANCE'];
            $csvData[] = ['Course', 'Total Enrollments', 'Active', 'Canceled', 'Total Revenue', 'Churn Rate', 'ARPU'];

            foreach ($reportData['course_performance'] as $course) {
                $csvData[] = [
                    $course['name'],
                    $course['total_enrollments'],
                    $course['active_subscriptions'],
                    $course['canceled_subscriptions'],
                    'RM ' . number_format($course['total_revenue'], 2),
                    $course['churn_rate'] . '%',
                    'RM ' . number_format($course['average_revenue_per_user'], 2),
                ];
            }
            $csvData[] = [];
        }

        // Monthly revenue
        if (!empty($reportData['revenue']['monthly_revenue'])) {
            $csvData[] = ['MONTHLY REVENUE'];
            $csvData[] = ['Month', 'Revenue'];

            foreach ($reportData['revenue']['monthly_revenue'] as $month => $revenue) {
                $csvData[] = [$month, 'RM ' . number_format($revenue, 2)];
            }
        }

        $filename = 'subscription_report_' . now()->format('Y_m_d_His') . '.csv';

        $handle = fopen('php://memory', 'r+');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return Response::streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function buildFilters(): array
    {
        $filters = [];

        if ($this->selectedCourse) {
            $filters['course_id'] = $this->selectedCourse;
        }

        if ($this->selectedYear) {
            $filters['year'] = $this->selectedYear;
        }

        if ($this->selectedMonth) {
            $filters['month'] = $this->selectedMonth;
        }

        if ($this->dateFrom) {
            $filters['date_from'] = $this->dateFrom;
        }

        if ($this->dateTo) {
            $filters['date_to'] = $this->dateTo;
        }

        if ($this->subscriptionStatus) {
            $filters['subscription_status'] = $this->subscriptionStatus;
        }

        if ($this->paymentStatus) {
            $filters['payment_status'] = $this->paymentStatus;
        }

        return $filters;
    }

    private function getAvailableYears(): array
    {
        $currentYear = now()->year;
        $startYear = 2023; // Or fetch from earliest enrollment

        $years = [];
        for ($year = $currentYear; $year >= $startYear; $year--) {
            $years[] = $year;
        }

        return $years;
    }

    private function getFilterSummary(): string
    {
        $parts = [];

        if ($this->selectedCourse) {
            $course = Course::find($this->selectedCourse);
            $parts[] = 'Course: ' . ($course ? $course->name : 'Unknown');
        }

        if ($this->selectedYear) {
            $parts[] = 'Year: ' . $this->selectedYear;
        }

        if ($this->selectedMonth) {
            $parts[] = 'Month: ' . $this->selectedMonth;
        }

        if ($this->dateFrom) {
            $parts[] = 'From: ' . $this->dateFrom;
        }

        if ($this->dateTo) {
            $parts[] = 'To: ' . $this->dateTo;
        }

        if ($this->subscriptionStatus) {
            $parts[] = 'Subscription Status: ' . $this->subscriptionStatus;
        }

        if ($this->paymentStatus) {
            $parts[] = 'Payment Status: ' . $this->paymentStatus;
        }

        return !empty($parts) ? implode(', ', $parts) : 'No filters applied';
    }

    public function clearFilters()
    {
        $this->reset(['selectedCourse', 'selectedMonth', 'dateFrom', 'dateTo', 'subscriptionStatus', 'paymentStatus']);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Subscription Reports</flux:heading>
            <flux:text class="mt-2">Monitor and analyze subscription performance by course and time period</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:button variant="outline" wire:click="exportReport">
                <div class="flex items-center justify-center">
                    <flux:icon icon="arrow-down-tray" class="w-4 h-4 mr-2" />
                    Export Report
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Report Type Selector -->
    <flux:card class="mb-6">
        <flux:header>
            <flux:heading size="lg">Report Type</flux:heading>
        </flux:header>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <flux:button
                :variant="$reportType === 'overview' ? 'primary' : 'outline'"
                wire:click="$set('reportType', 'overview')"
                class="w-full"
            >
                <div class="flex items-center justify-center">
                    <flux:icon icon="chart-pie" class="w-4 h-4 mr-2" />
                    Overview
                </div>
            </flux:button>

            <flux:button
                :variant="$reportType === 'revenue' ? 'primary' : 'outline'"
                wire:click="$set('reportType', 'revenue')"
                class="w-full"
            >
                <div class="flex items-center justify-center">
                    <flux:icon icon="currency-dollar" class="w-4 h-4 mr-2" />
                    Revenue
                </div>
            </flux:button>

            <flux:button
                :variant="$reportType === 'courses' ? 'primary' : 'outline'"
                wire:click="$set('reportType', 'courses')"
                class="w-full"
            >
                <div class="flex items-center justify-center">
                    <flux:icon icon="academic-cap" class="w-4 h-4 mr-2" />
                    Courses
                </div>
            </flux:button>

            <flux:button
                :variant="$reportType === 'payments' ? 'primary' : 'outline'"
                wire:click="$set('reportType', 'payments')"
                class="w-full"
            >
                <div class="flex items-center justify-center">
                    <flux:icon icon="credit-card" class="w-4 h-4 mr-2" />
                    Payments
                </div>
            </flux:button>

            <flux:button
                :variant="$reportType === 'growth' ? 'primary' : 'outline'"
                wire:click="$set('reportType', 'growth')"
                class="w-full"
            >
                <div class="flex items-center justify-center">
                    <flux:icon icon="chart-bar" class="w-4 h-4 mr-2" />
                    Growth
                </div>
            </flux:button>
        </div>
    </flux:card>

    <!-- Filters -->
    <flux:card class="mb-6">
        <flux:header>
            <flux:heading size="lg">Filters</flux:heading>
            <flux:button variant="ghost" wire:click="clearFilters" size="sm">
                Clear All
            </flux:button>
        </flux:header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Course Filter -->
            <flux:select wire:model.live="selectedCourse" placeholder="All Courses">
                <flux:select.option value="">All Courses</flux:select.option>
                @foreach($courses as $course)
                    <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <!-- Year Filter -->
            <flux:select wire:model.live="selectedYear" placeholder="Select Year">
                <flux:select.option value="">All Years</flux:select.option>
                @foreach($availableYears as $year)
                    <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                @endforeach
            </flux:select>

            <!-- Month Filter -->
            <flux:select wire:model.live="selectedMonth" placeholder="All Months">
                <flux:select.option value="">All Months</flux:select.option>
                @for($i = 1; $i <= 12; $i++)
                    <flux:select.option value="{{ $i }}">{{ date('F', mktime(0, 0, 0, $i, 1)) }}</flux:select.option>
                @endfor
            </flux:select>

            <!-- Subscription Status Filter -->
            <flux:select wire:model.live="subscriptionStatus" placeholder="All Statuses">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="canceled">Canceled</flux:select.option>
                <flux:select.option value="past_due">Past Due</flux:select.option>
                <flux:select.option value="trialing">Trialing</flux:select.option>
                <flux:select.option value="incomplete">Incomplete</flux:select.option>
            </flux:select>
        </div>

        <!-- Date Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <flux:input type="date" wire:model.live="dateFrom" placeholder="From Date" />
            <flux:input type="date" wire:model.live="dateTo" placeholder="To Date" />
        </div>

        @if($selectedCourse || $selectedYear || $selectedMonth || $dateFrom || $dateTo || $subscriptionStatus)
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <flux:text class="text-blue-800 text-sm">
                    <flux:icon icon="funnel" class="w-4 h-4 inline mr-1" />
                    Active Filters: {{ $this->getFilterSummary() }}
                </flux:text>
            </div>
        @endif
    </flux:card>

    <!-- Report Content -->
    @if($reportType === 'overview')
        <!-- Overview Report -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 mb-6">
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Total Subscriptions</flux:heading>
                        <flux:heading size="xl" class="text-blue-600">{{ number_format($overview['total_subscriptions']) }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">All time subscriptions</flux:text>
                    </div>
                    <flux:icon icon="users" class="w-8 h-8 text-blue-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Active Subscriptions</flux:heading>
                        <flux:heading size="xl" class="text-emerald-600">{{ number_format($overview['active_subscriptions']) }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Currently active</flux:text>
                    </div>
                    <flux:icon icon="check-circle" class="w-8 h-8 text-emerald-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Monthly Revenue</flux:heading>
                        <flux:heading size="xl" class="text-emerald-600">RM {{ number_format($overview['monthly_revenue'], 2) }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Current month</flux:text>
                    </div>
                    <flux:icon icon="currency-dollar" class="w-8 h-8 text-emerald-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Total Revenue</flux:heading>
                        <flux:heading size="xl" class="text-purple-600">RM {{ number_format($overview['total_revenue'], 2) }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">All time revenue</flux:text>
                    </div>
                    <flux:icon icon="banknotes" class="w-8 h-8 text-purple-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Churn Rate</flux:heading>
                        <flux:heading size="xl" class="text-red-600">{{ number_format($overview['churn_rate'], 1) }}%</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Subscription cancellations</flux:text>
                    </div>
                    <flux:icon icon="exclamation-triangle" class="w-8 h-8 text-red-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Payment Success Rate</flux:heading>
                        <flux:heading size="xl" class="text-blue-600">{{ number_format($overview['payment_success_rate'], 1) }}%</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Payment processing</flux:text>
                    </div>
                    <flux:icon icon="shield-check" class="w-8 h-8 text-blue-500" />
                </div>
            </flux:card>
        </div>

        <!-- Subscription Status Distribution -->
        @if(!empty($statusDistribution))
            <flux:card class="mb-6">
                <flux:header>
                    <flux:heading size="lg">Subscription Status Distribution</flux:heading>
                </flux:header>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($statusDistribution as $status => $count)
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <flux:heading size="lg" class="
                                @if($status === 'active') text-emerald-600
                                @elseif($status === 'canceled') text-red-600
                                @elseif($status === 'past_due') text-amber-600
                                @else text-blue-600
                                @endif
                            ">{{ number_format($count) }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600 capitalize">{{ str_replace('_', ' ', $status) }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif
    @endif

    @if($reportType === 'revenue')
        <!-- Revenue Report -->
        <div class="grid gap-6 lg:grid-cols-2 mb-6">
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Revenue Summary</flux:heading>
                </flux:header>

                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <flux:text class="text-gray-600">Total Revenue</flux:text>
                        <flux:text class="font-bold text-emerald-600">RM {{ number_format($revenue['total_revenue'], 2) }}</flux:text>
                    </div>
                    <div class="flex justify-between items-center">
                        <flux:text class="text-gray-600">Average Order Value</flux:text>
                        <flux:text class="font-medium">RM {{ number_format($revenue['average_order_value'], 2) }}</flux:text>
                    </div>
                </div>
            </flux:card>

            @if(!empty($revenue['revenue_by_course']))
                <flux:card>
                    <flux:header>
                        <flux:heading size="lg">Revenue by Course</flux:heading>
                    </flux:header>

                    <div class="space-y-3">
                        @foreach($revenue['revenue_by_course'] as $courseRevenue)
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <flux:text class="font-medium">{{ $courseRevenue['course_name'] }}</flux:text>
                                    <flux:text size="sm" class="text-gray-600">{{ $courseRevenue['order_count'] }} orders</flux:text>
                                </div>
                                <flux:text class="font-medium text-emerald-600">
                                    RM {{ number_format($courseRevenue['total_revenue'], 2) }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif
        </div>

        @if(!empty($revenue['monthly_revenue']))
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Monthly Revenue Trend</flux:heading>
                </flux:header>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4">Month</th>
                                <th class="text-right py-3 px-4">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($revenue['monthly_revenue'] as $month => $amount)
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium">{{ $month }}</td>
                                    <td class="py-3 px-4 text-right font-medium text-emerald-600">
                                        RM {{ number_format($amount, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif
    @endif

    @if($reportType === 'courses')
        <!-- Course Performance Report -->
        @if(!empty($coursePerformance))
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Course Performance Analysis</flux:heading>
                </flux:header>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4">Course</th>
                                <th class="text-center py-3 px-4">Total Enrollments</th>
                                <th class="text-center py-3 px-4">Active</th>
                                <th class="text-center py-3 px-4">Canceled</th>
                                <th class="text-right py-3 px-4">Total Revenue</th>
                                <th class="text-center py-3 px-4">Churn Rate</th>
                                <th class="text-right py-3 px-4">ARPU</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($coursePerformance as $course)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 font-medium">{{ $course['name'] }}</td>
                                    <td class="py-3 px-4 text-center">{{ $course['total_enrollments'] }}</td>
                                    <td class="py-3 px-4 text-center text-emerald-600">{{ $course['active_subscriptions'] }}</td>
                                    <td class="py-3 px-4 text-center text-red-600">{{ $course['canceled_subscriptions'] }}</td>
                                    <td class="py-3 px-4 text-right font-medium">RM {{ number_format($course['total_revenue'], 2) }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="@if($course['churn_rate'] > 20) text-red-600 @elseif($course['churn_rate'] > 10) text-amber-600 @else text-emerald-600 @endif">
                                            {{ $course['churn_rate'] }}%
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-medium">RM {{ number_format($course['average_revenue_per_user'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @else
            <flux:card>
                <div class="text-center py-12">
                    <flux:icon icon="academic-cap" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <flux:heading size="md" class="text-gray-600 mb-2">No course data found</flux:heading>
                    <flux:text class="text-gray-600">No course performance data matches your current filters.</flux:text>
                </div>
            </flux:card>
        @endif
    @endif

    @if($reportType === 'payments')
        <!-- Payment Analytics Report -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Total Orders</flux:heading>
                        <flux:heading size="xl" class="text-blue-600">{{ number_format($paymentAnalytics['total_orders']) }}</flux:heading>
                    </div>
                    <flux:icon icon="clipboard-document-list" class="w-8 h-8 text-blue-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Successful Payments</flux:heading>
                        <flux:heading size="xl" class="text-emerald-600">{{ number_format($paymentAnalytics['successful_payments']) }}</flux:heading>
                    </div>
                    <flux:icon icon="check-circle" class="w-8 h-8 text-emerald-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Failed Payments</flux:heading>
                        <flux:heading size="xl" class="text-red-600">{{ number_format($paymentAnalytics['failed_payments']) }}</flux:heading>
                    </div>
                    <flux:icon icon="x-circle" class="w-8 h-8 text-red-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-gray-600">Success Rate</flux:heading>
                        <flux:heading size="xl" class="text-blue-600">{{ number_format($paymentAnalytics['success_rate'], 1) }}%</flux:heading>
                    </div>
                    <flux:icon icon="shield-check" class="w-8 h-8 text-blue-500" />
                </div>
            </flux:card>
        </div>

        @if(!empty($paymentAnalytics['payment_trends']))
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Payment Trends</flux:heading>
                </flux:header>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4">Month</th>
                                <th class="text-center py-3 px-4">Total</th>
                                <th class="text-center py-3 px-4">Successful</th>
                                <th class="text-center py-3 px-4">Failed</th>
                                <th class="text-center py-3 px-4">Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paymentAnalytics['payment_trends'] as $month => $trend)
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium">{{ $month }}</td>
                                    <td class="py-3 px-4 text-center">{{ $trend['total'] }}</td>
                                    <td class="py-3 px-4 text-center text-emerald-600">{{ $trend['successful'] }}</td>
                                    <td class="py-3 px-4 text-center text-red-600">{{ $trend['failed'] }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="@if($trend['success_rate'] >= 95) text-emerald-600 @elseif($trend['success_rate'] >= 85) text-amber-600 @else text-red-600 @endif">
                                            {{ $trend['success_rate'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif
    @endif

    @if($reportType === 'growth')
        <!-- Growth Metrics Report -->
        @if(!empty($growth['new_subscriptions']))
            <div class="grid gap-6 lg:grid-cols-2 mb-6">
                <flux:card>
                    <flux:header>
                        <flux:heading size="lg">New Subscriptions</flux:heading>
                    </flux:header>

                    <div class="space-y-3">
                        @foreach($growth['new_subscriptions'] as $month => $count)
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <flux:text class="font-medium">{{ $month }}</flux:text>
                                <flux:text class="font-medium text-emerald-600">{{ $count }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                @if(!empty($growth['growth_rates']))
                    <flux:card>
                        <flux:header>
                            <flux:heading size="lg">Growth Rates</flux:heading>
                        </flux:header>

                        <div class="space-y-3">
                            @foreach($growth['growth_rates'] as $month => $rate)
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <flux:text class="font-medium">{{ $month }}</flux:text>
                                    <flux:text class="font-medium @if($rate > 0) text-emerald-600 @elseif($rate < 0) text-red-600 @else text-gray-600 @endif">
                                        {{ $rate > 0 ? '+' : '' }}{{ $rate }}%
                                    </flux:text>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @endif
            </div>

            @if(!empty($growth['net_growth']))
                <flux:card>
                    <flux:header>
                        <flux:heading size="lg">Net Growth (New - Canceled)</flux:heading>
                    </flux:header>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4">Month</th>
                                    <th class="text-center py-3 px-4">New</th>
                                    <th class="text-center py-3 px-4">Canceled</th>
                                    <th class="text-center py-3 px-4">Net Growth</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($growth['net_growth'] as $month => $net)
                                    <tr class="border-b border-gray-100">
                                        <td class="py-3 px-4 font-medium">{{ $month }}</td>
                                        <td class="py-3 px-4 text-center text-emerald-600">{{ $growth['new_subscriptions'][$month] ?? 0 }}</td>
                                        <td class="py-3 px-4 text-center text-red-600">{{ $growth['canceled_subscriptions'][$month] ?? 0 }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="@if($net > 0) text-emerald-600 @elseif($net < 0) text-red-600 @else text-gray-600 @endif">
                                                {{ $net > 0 ? '+' : '' }}{{ $net }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            @endif
        @else
            <flux:card>
                <div class="text-center py-12">
                    <flux:icon icon="chart-bar" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <flux:heading size="md" class="text-gray-600 mb-2">No growth data found</flux:heading>
                    <flux:text class="text-gray-600">No growth data matches your current filters.</flux:text>
                </div>
            </flux:card>
        @endif
    @endif
</div>