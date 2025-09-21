<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\SubscriptionAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->analyticsService = app(SubscriptionAnalyticsService::class);
});

test('getOverviewMetrics returns correct subscription metrics', function () {
    // Create test data
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create active enrollment
    $activeEnrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'active',
        'stripe_subscription_id' => 'sub_test_active',
    ]);

    // Create canceled enrollment
    $canceledEnrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'canceled',
        'stripe_subscription_id' => 'sub_test_canceled',
    ]);

    // Create orders
    Order::factory()->create([
        'enrollment_id' => $activeEnrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 100.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    Order::factory()->create([
        'enrollment_id' => $canceledEnrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 50.00,
        'status' => 'failed',
        'failed_at' => now(),
    ]);

    $metrics = $this->analyticsService->getOverviewMetrics();

    expect($metrics['total_subscriptions'])->toBe(2);
    expect($metrics['active_subscriptions'])->toBe(1);
    expect($metrics['total_revenue'])->toBe(100.0);
    expect($metrics['churn_rate'])->toBe(50.0); // 1 canceled out of 2 total
    expect($metrics['payment_success_rate'])->toBe(50.0); // 1 successful out of 2 orders
});

test('getRevenueMetrics calculates revenue correctly', function () {
    $course1 = Course::factory()->create(['name' => 'Course A']);
    $course2 = Course::factory()->create(['name' => 'Course B']);
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    $enrollment1 = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course1->id,
    ]);

    $enrollment2 = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course2->id,
    ]);

    // Create orders for different months
    Order::factory()->create([
        'enrollment_id' => $enrollment1->id,
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'amount' => 100.00,
        'status' => 'paid',
        'paid_at' => Carbon::parse('2024-01-15'),
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment2->id,
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'amount' => 150.00,
        'status' => 'paid',
        'paid_at' => Carbon::parse('2024-02-15'),
    ]);

    $revenueMetrics = $this->analyticsService->getRevenueMetrics();

    expect($revenueMetrics['total_revenue'])->toBe(250.0);
    expect($revenueMetrics['average_order_value'])->toBe(125.0);
    expect($revenueMetrics['monthly_revenue'])->toHaveCount(2);
    expect($revenueMetrics['monthly_revenue']['2024-01'])->toBe(100.0);
    expect($revenueMetrics['monthly_revenue']['2024-02'])->toBe(150.0);
    expect($revenueMetrics['revenue_by_course'])->toHaveCount(2);
});

test('getSubscriptionStatusDistribution returns correct distribution', function () {
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create enrollments with different statuses
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'active',
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'active',
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'canceled',
    ]);

    $distribution = $this->analyticsService->getSubscriptionStatusDistribution();

    expect($distribution['active'])->toBe(2);
    expect($distribution['canceled'])->toBe(1);
});

test('getCoursePerformance calculates course metrics correctly', function () {
    $course1 = Course::factory()->create(['name' => 'High Performance Course']);
    $course2 = Course::factory()->create(['name' => 'Low Performance Course']);
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Course 1: 2 active, 1 canceled
    $enrollment1a = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'subscription_status' => 'active',
    ]);

    $enrollment1b = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'subscription_status' => 'active',
    ]);

    $enrollment1c = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'subscription_status' => 'canceled',
    ]);

    // Course 2: 1 active
    $enrollment2a = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'subscription_status' => 'active',
    ]);

    // Create revenue orders
    Order::factory()->create([
        'enrollment_id' => $enrollment1a->id,
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'amount' => 300.00,
        'status' => 'paid',
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment2a->id,
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'amount' => 100.00,
        'status' => 'paid',
    ]);

    $coursePerformance = $this->analyticsService->getCoursePerformance();

    expect($coursePerformance)->toHaveCount(2);

    $course1Performance = collect($coursePerformance)->firstWhere('name', 'High Performance Course');
    expect($course1Performance['total_enrollments'])->toBe(3);
    expect($course1Performance['active_subscriptions'])->toBe(2);
    expect($course1Performance['canceled_subscriptions'])->toBe(1);
    expect($course1Performance['total_revenue'])->toBe(300.0);
    expect($course1Performance['churn_rate'])->toBe(33.33); // 1/3 * 100
    expect($course1Performance['average_revenue_per_user'])->toBe(100.0); // 300/3

    $course2Performance = collect($coursePerformance)->firstWhere('name', 'Low Performance Course');
    expect($course2Performance['total_enrollments'])->toBe(1);
    expect($course2Performance['active_subscriptions'])->toBe(1);
    expect($course2Performance['churn_rate'])->toBe(0.0);
});

test('getPaymentAnalytics calculates payment metrics correctly', function () {
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Create orders with different statuses
    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'paid',
        'created_at' => Carbon::parse('2024-01-15'),
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'paid',
        'created_at' => Carbon::parse('2024-01-20'),
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'failed',
        'created_at' => Carbon::parse('2024-02-15'),
    ]);

    $paymentAnalytics = $this->analyticsService->getPaymentAnalytics();

    expect($paymentAnalytics['total_orders'])->toBe(3);
    expect($paymentAnalytics['successful_payments'])->toBe(2);
    expect($paymentAnalytics['failed_payments'])->toBe(1);
    expect($paymentAnalytics['success_rate'])->toBe(66.67);
    expect($paymentAnalytics['failure_rate'])->toBe(33.33);
    expect($paymentAnalytics['payment_trends'])->toHaveCount(2);
    expect($paymentAnalytics['payment_trends']['2024-01']['total'])->toBe(2);
    expect($paymentAnalytics['payment_trends']['2024-02']['total'])->toBe(1);
});

test('getGrowthMetrics calculates growth rates correctly', function () {
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create enrollments in different months
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'created_at' => Carbon::parse('2024-01-15'),
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'created_at' => Carbon::parse('2024-01-20'),
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'created_at' => Carbon::parse('2024-02-15'),
    ]);

    // Create canceled enrollment
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'created_at' => Carbon::parse('2024-02-10'),
        'subscription_cancel_at' => Carbon::parse('2024-02-25'),
    ]);

    $growthMetrics = $this->analyticsService->getGrowthMetrics();

    expect($growthMetrics['new_subscriptions'])->toHaveCount(2);
    expect($growthMetrics['new_subscriptions']['2024-01'])->toBe(2);
    expect($growthMetrics['new_subscriptions']['2024-02'])->toBe(2);
    expect($growthMetrics['growth_rates'])->toBeArray();
    expect($growthMetrics['net_growth'])->toBeArray();
});

test('filtering works correctly across all methods', function () {
    $course1 = Course::factory()->create(['name' => 'Filtered Course']);
    $course2 = Course::factory()->create(['name' => 'Other Course']);
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create enrollments for both courses
    $enrollment1 = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'subscription_status' => 'active',
        'created_at' => Carbon::parse('2024-01-15'),
    ]);

    $enrollment2 = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'subscription_status' => 'active',
        'created_at' => Carbon::parse('2024-01-15'),
    ]);

    // Create orders for both
    Order::factory()->create([
        'enrollment_id' => $enrollment1->id,
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'amount' => 100.00,
        'status' => 'paid',
        'created_at' => Carbon::parse('2024-01-15'),
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment2->id,
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'amount' => 200.00,
        'status' => 'paid',
        'created_at' => Carbon::parse('2024-01-15'),
    ]);

    // Test course filter
    $filters = ['course_id' => $course1->id];

    $overview = $this->analyticsService->getOverviewMetrics($filters);
    expect($overview['total_subscriptions'])->toBe(1);
    expect($overview['total_revenue'])->toBe(100.0);

    $revenue = $this->analyticsService->getRevenueMetrics($filters);
    expect($revenue['total_revenue'])->toBe(100.0);

    $statusDistribution = $this->analyticsService->getSubscriptionStatusDistribution($filters);
    expect($statusDistribution['active'])->toBe(1);

    // Test year filter
    $yearFilters = ['year' => 2024];
    $yearOverview = $this->analyticsService->getOverviewMetrics($yearFilters);
    expect($yearOverview['total_subscriptions'])->toBe(2);
    expect($yearOverview['total_revenue'])->toBe(300.0);

    // Test year filter that excludes all data
    $noDataFilters = ['year' => 2023];
    $noDataOverview = $this->analyticsService->getOverviewMetrics($noDataFilters);
    expect($noDataOverview['total_subscriptions'])->toBe(0);
    expect($noDataOverview['total_revenue'])->toBe(0.0);
});

test('exportReportData includes all necessary data', function () {
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'active',
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 100.00,
        'status' => 'paid',
    ]);

    $exportData = $this->analyticsService->exportReportData();

    expect($exportData)->toHaveKeys([
        'overview',
        'revenue',
        'course_performance',
        'payment_analytics',
        'exported_at',
        'filters_applied',
    ]);

    expect($exportData['overview'])->toHaveKeys([
        'total_subscriptions',
        'active_subscriptions',
        'monthly_revenue',
        'total_revenue',
        'churn_rate',
        'payment_success_rate',
    ]);

    expect($exportData['exported_at'])->toBeString();
    expect($exportData['filters_applied'])->toBeArray();
});
