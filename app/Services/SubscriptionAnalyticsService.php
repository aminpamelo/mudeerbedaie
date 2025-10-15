<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionAnalyticsService
{
    public function getOverviewMetrics(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);

        return [
            'total_subscriptions' => $this->getTotalSubscriptions($filters),
            'active_subscriptions' => $this->getActiveSubscriptions($filters),
            'monthly_revenue' => $this->getMonthlyRevenue($filters),
            'total_revenue' => $this->getTotalRevenue($filters),
            'churn_rate' => $this->getChurnRate($filters),
            'payment_success_rate' => $this->getPaymentSuccessRate($filters),
        ];
    }

    public function getRevenueMetrics(array $filters = []): array
    {
        $query = Order::query()
            ->where('status', 'paid')
            ->with(['course:id,name']);

        $this->applyFilters($query, $filters);

        $orders = $query->get();

        // Monthly Recurring Revenue
        $mrr = $orders->groupBy(function ($order) {
            return Carbon::parse($order->paid_at)->format('Y-m');
        })->map->sum('amount');

        // Revenue by Course
        $revenueByCourse = $orders->groupBy('course_id')
            ->map(function ($courseOrders) {
                return [
                    'course_name' => $courseOrders->first()->course?->name ?? 'Unknown',
                    'total_revenue' => $courseOrders->sum('amount'),
                    'order_count' => $courseOrders->count(),
                ];
            });

        return [
            'monthly_revenue' => $mrr,
            'revenue_by_course' => $revenueByCourse,
            'total_revenue' => $orders->sum('amount'),
            'average_order_value' => $orders->avg('amount'),
        ];
    }

    public function getSubscriptionStatusDistribution(array $filters = []): array
    {
        $query = Enrollment::query();
        $this->applyFilters($query, $filters);

        $distribution = $query->select('subscription_status', DB::raw('count(*) as count'))
            ->whereNotNull('subscription_status')
            ->groupBy('subscription_status')
            ->get()
            ->pluck('count', 'subscription_status')
            ->toArray();

        return $distribution;
    }

    public function getCoursePerformance(array $filters = []): array
    {
        $query = Enrollment::query()
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->leftJoin('orders', function ($join) {
                $join->on('enrollments.id', '=', 'orders.enrollment_id')
                    ->where('orders.status', '=', 'paid');
            });

        $this->applyFilters($query, $filters);

        $performance = $query->select([
            'courses.id',
            'courses.name',
            DB::raw('COUNT(DISTINCT enrollments.id) as total_enrollments'),
            DB::raw('COUNT(CASE WHEN enrollments.subscription_status = "active" THEN 1 END) as active_subscriptions'),
            DB::raw('COUNT(CASE WHEN enrollments.subscription_status = "canceled" THEN 1 END) as canceled_subscriptions'),
            DB::raw('COALESCE(SUM(orders.amount), 0) as total_revenue'),
            DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
        ])
            ->groupBy('courses.id', 'courses.name')
            ->get();

        return $performance->map(function ($course) {
            $course->churn_rate = $course->total_enrollments > 0
                ? round(($course->canceled_subscriptions / $course->total_enrollments) * 100, 2)
                : 0;
            $course->average_revenue_per_user = $course->total_enrollments > 0
                ? round($course->total_revenue / $course->total_enrollments, 2)
                : 0;

            return $course;
        })->toArray();
    }

    public function getPaymentAnalytics(array $filters = []): array
    {
        $query = Order::query();
        $this->applyFilters($query, $filters);

        $orders = $query->get();
        $totalOrders = $orders->count();

        if ($totalOrders === 0) {
            return [
                'total_orders' => 0,
                'successful_payments' => 0,
                'failed_payments' => 0,
                'success_rate' => 0,
                'failure_rate' => 0,
                'payment_trends' => [],
            ];
        }

        $successfulPayments = $orders->where('status', 'paid')->count();
        $failedPayments = $orders->where('status', 'failed')->count();

        // Payment trends by month
        $paymentTrends = $orders->groupBy(function ($order) {
            return Carbon::parse($order->created_at)->format('Y-m');
        })->map(function ($monthOrders) {
            $total = $monthOrders->count();
            $successful = $monthOrders->where('status', 'paid')->count();
            $failed = $monthOrders->where('status', 'failed')->count();

            return [
                'total' => $total,
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            ];
        });

        return [
            'total_orders' => $totalOrders,
            'successful_payments' => $successfulPayments,
            'failed_payments' => $failedPayments,
            'success_rate' => round(($successfulPayments / $totalOrders) * 100, 2),
            'failure_rate' => round(($failedPayments / $totalOrders) * 100, 2),
            'payment_trends' => $paymentTrends,
        ];
    }

    public function getGrowthMetrics(array $filters = []): array
    {
        $query = Enrollment::query();
        $this->applyFilters($query, $filters);

        // New subscriptions by month
        $newSubscriptions = $query->select(
            DB::raw($this->getDateFormatSql('created_at').' as month'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month');

        // Canceled subscriptions by month
        $canceledQuery = Enrollment::query()
            ->whereNotNull('subscription_cancel_at');

        $this->applyFilters($canceledQuery, $filters);

        $canceledSubscriptions = $canceledQuery->select(
            DB::raw($this->getDateFormatSql('subscription_cancel_at').' as month'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month');

        // Calculate growth rate
        $growthRates = [];
        $months = $newSubscriptions->keys()->sort();

        foreach ($months as $index => $month) {
            if ($index > 0) {
                $previousMonth = $months[$index - 1];
                $currentCount = $newSubscriptions[$month] ?? 0;
                $previousCount = $newSubscriptions[$previousMonth] ?? 0;

                if ($previousCount > 0) {
                    $growthRate = round((($currentCount - $previousCount) / $previousCount) * 100, 2);
                } else {
                    $growthRate = $currentCount > 0 ? 100 : 0;
                }

                $growthRates[$month] = $growthRate;
            }
        }

        return [
            'new_subscriptions' => $newSubscriptions,
            'canceled_subscriptions' => $canceledSubscriptions,
            'growth_rates' => $growthRates,
            'net_growth' => $newSubscriptions->map(function ($new, $month) use ($canceledSubscriptions) {
                $canceled = $canceledSubscriptions[$month] ?? 0;

                return $new - $canceled;
            }),
        ];
    }

    public function exportReportData(array $filters = [], string $format = 'csv'): array
    {
        $overview = $this->getOverviewMetrics($filters);
        $revenue = $this->getRevenueMetrics($filters);
        $coursePerformance = $this->getCoursePerformance($filters);
        $paymentAnalytics = $this->getPaymentAnalytics($filters);

        return [
            'overview' => $overview,
            'revenue' => $revenue,
            'course_performance' => $coursePerformance,
            'payment_analytics' => $paymentAnalytics,
            'exported_at' => now()->toDateTimeString(),
            'filters_applied' => $filters,
        ];
    }

    private function getTotalSubscriptions(array $filters = []): int
    {
        $query = Enrollment::query()->whereNotNull('stripe_subscription_id');
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    private function getActiveSubscriptions(array $filters = []): int
    {
        $query = Enrollment::query()->where('subscription_status', 'active');
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    private function getMonthlyRevenue(array $filters = []): float
    {
        $currentMonth = now()->format('Y-m');
        $query = Order::query()
            ->where('status', 'paid')
            ->whereRaw($this->getDateFormatSql('paid_at').' = ?', [$currentMonth]);

        $this->applyFilters($query, $filters);

        return (float) $query->sum('amount');
    }

    private function getTotalRevenue(array $filters = []): float
    {
        $query = Order::query()->where('status', 'paid');
        $this->applyFilters($query, $filters);

        return (float) $query->sum('amount');
    }

    private function getChurnRate(array $filters = []): float
    {
        $totalQuery = Enrollment::query()->whereNotNull('stripe_subscription_id');
        $canceledQuery = clone $totalQuery;
        $canceledQuery->where('subscription_status', 'canceled');

        $this->applyFilters($totalQuery, $filters);
        $this->applyFilters($canceledQuery, $filters);

        $total = $totalQuery->count();
        $canceled = $canceledQuery->count();

        return $total > 0 ? round(($canceled / $total) * 100, 2) : 0;
    }

    private function getPaymentSuccessRate(array $filters = []): float
    {
        $query = Order::query();
        $this->applyFilters($query, $filters);

        $total = $query->count();
        $successful = $query->where('status', 'paid')->count();

        return $total > 0 ? round(($successful / $total) * 100, 2) : 0;
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        if (! empty($filters['year'])) {
            // Determine which table's created_at to use based on the query structure
            $tableName = $this->getMainTableName($query);
            $query->whereYear($tableName.'.created_at', $filters['year']);
        }

        if (! empty($filters['month'])) {
            $tableName = $this->getMainTableName($query);
            $query->whereMonth($tableName.'.created_at', $filters['month']);
        }

        if (! empty($filters['date_from'])) {
            $tableName = $this->getMainTableName($query);
            $query->whereDate($tableName.'.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $tableName = $this->getMainTableName($query);
            $query->whereDate($tableName.'.created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['subscription_status'])) {
            $query->where('subscription_status', $filters['subscription_status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('status', $filters['payment_status']);
        }
    }

    private function getMainTableName($query): string
    {
        // Extract the table name from the query
        $sql = $query->toSql();

        // Check if this is a complex query with joins
        if (str_contains($sql, 'inner join') || str_contains($sql, 'left join')) {
            // For course performance queries, use enrollments as the main table
            if (str_contains($sql, 'enrollments')) {
                return 'enrollments';
            }
            // For order-based queries, use orders
            if (str_contains($sql, 'orders')) {
                return 'orders';
            }
        }

        // Default fallback based on the model
        $model = $query->getModel();
        if ($model instanceof \App\Models\Enrollment) {
            return 'enrollments';
        }
        if ($model instanceof \App\Models\Order) {
            return 'orders';
        }

        // Final fallback
        return 'enrollments';
    }

    private function getDateRange(array $filters): array
    {
        $from = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])
            : now()->subYear();

        $to = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])
            : now();

        return [$from, $to];
    }

    private function getDateFormatSql(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => "DATE_FORMAT({$column}, \"%Y-%m\")",
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, \"%Y-%m\")",
        };
    }
}
