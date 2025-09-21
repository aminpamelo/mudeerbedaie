<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('admin can access subscription reports page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin/reports/subscriptions');

    $response->assertStatus(200);
    $response->assertSee('Subscription Reports');
    $response->assertSee('Overview');
    $response->assertSee('Revenue');
    $response->assertSee('Courses');
    $response->assertSee('Payments');
    $response->assertSee('Growth');
});

test('non-admin users cannot access subscription reports', function () {
    $student = User::factory()->student()->create();
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($student)->get('/admin/reports/subscriptions')->assertStatus(403);
    $this->actingAs($teacher)->get('/admin/reports/subscriptions')->assertStatus(403);
});

test('guests are redirected to login', function () {
    $response = $this->get('/admin/reports/subscriptions');
    $response->assertRedirect('/login');
});

test('subscription reports component loads with default overview data', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create test data
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'active',
        'stripe_subscription_id' => 'sub_test',
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 100.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->assertSet('reportType', 'overview')
        ->assertSee('Total Subscriptions')
        ->assertSee('Active Subscriptions')
        ->assertSee('Monthly Revenue')
        ->assertSee('Total Revenue')
        ->assertSee('1') // Should show 1 subscription
        ->assertSee('RM 100.00'); // Should show revenue
});

test('report type switching works correctly', function () {
    $admin = User::factory()->admin()->create();

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->assertSet('reportType', 'overview')
        ->call('$set', 'reportType', 'revenue')
        ->assertSet('reportType', 'revenue')
        ->call('$set', 'reportType', 'courses')
        ->assertSet('reportType', 'courses')
        ->call('$set', 'reportType', 'payments')
        ->assertSet('reportType', 'payments')
        ->call('$set', 'reportType', 'growth')
        ->assertSet('reportType', 'growth');
});

test('course filtering works correctly', function () {
    $admin = User::factory()->admin()->create();
    $course1 = Course::factory()->create(['name' => 'Course A']);
    $course2 = Course::factory()->create(['name' => 'Course B']);
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create enrollments for both courses
    $enrollment1 = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'subscription_status' => 'active',
    ]);

    $enrollment2 = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'subscription_status' => 'active',
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment1->id,
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'amount' => 100.00,
        'status' => 'paid',
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment2->id,
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'amount' => 200.00,
        'status' => 'paid',
    ]);

    $component = Volt::test('admin.subscription-reports')
        ->actingAs($admin);

    // Test no filter (should see both courses data)
    $component->assertSee('RM 300.00'); // Total revenue

    // Test course filter
    $component->set('selectedCourse', $course1->id)
        ->assertSee('Course A')
        ->assertSee('RM 100.00'); // Should show only course1 revenue
});

test('year and month filtering works correctly', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'active',
    ]);

    // Create orders in different years/months
    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 100.00,
        'status' => 'paid',
        'created_at' => Carbon::parse('2024-01-15'),
        'paid_at' => Carbon::parse('2024-01-15'),
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 200.00,
        'status' => 'paid',
        'created_at' => Carbon::parse('2023-12-15'),
        'paid_at' => Carbon::parse('2023-12-15'),
    ]);

    $component = Volt::test('admin.subscription-reports')
        ->actingAs($admin);

    // Test year filter
    $component->set('selectedYear', '2024')
        ->assertSee('RM 100.00'); // Should show only 2024 revenue

    // Test month filter
    $component->set('selectedMonth', '1') // January
        ->assertSee('RM 100.00'); // Should show only January revenue
});

test('subscription status filtering works correctly', function () {
    $admin = User::factory()->admin()->create();
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
        'subscription_status' => 'canceled',
    ]);

    $component = Volt::test('admin.subscription-reports')
        ->actingAs($admin);

    // Test status filter
    $component->set('subscriptionStatus', 'active')
        ->assertSee('1'); // Should show 1 active subscription

    $component->set('subscriptionStatus', 'canceled')
        ->assertSee('1'); // Should show 1 canceled subscription
});

test('revenue report displays correctly', function () {
    $admin = User::factory()->admin()->create();
    $course1 = Course::factory()->create(['name' => 'Course Alpha']);
    $course2 = Course::factory()->create(['name' => 'Course Beta']);
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

    Order::factory()->create([
        'enrollment_id' => $enrollment1->id,
        'student_id' => $student->id,
        'course_id' => $course1->id,
        'amount' => 150.00,
        'status' => 'paid',
        'paid_at' => Carbon::parse('2024-01-15'),
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment2->id,
        'student_id' => $student->id,
        'course_id' => $course2->id,
        'amount' => 250.00,
        'status' => 'paid',
        'paid_at' => Carbon::parse('2024-02-15'),
    ]);

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->set('reportType', 'revenue')
        ->assertSee('Revenue Summary')
        ->assertSee('RM 400.00') // Total revenue
        ->assertSee('RM 200.00') // Average order value
        ->assertSee('Revenue by Course')
        ->assertSee('Course Alpha')
        ->assertSee('Course Beta')
        ->assertSee('Monthly Revenue Trend')
        ->assertSee('2024-01')
        ->assertSee('2024-02');
});

test('course performance report displays correctly', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create(['name' => 'Test Course']);
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create multiple enrollments
    $activeEnrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'active',
    ]);

    $canceledEnrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'subscription_status' => 'canceled',
    ]);

    Order::factory()->create([
        'enrollment_id' => $activeEnrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 300.00,
        'status' => 'paid',
    ]);

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->set('reportType', 'courses')
        ->assertSee('Course Performance Analysis')
        ->assertSee('Test Course')
        ->assertSee('2') // Total enrollments
        ->assertSee('1') // Active subscriptions
        ->assertSee('1') // Canceled subscriptions
        ->assertSee('RM 300.00') // Total revenue
        ->assertSee('50.0%') // Churn rate (1/2 * 100)
        ->assertSee('RM 150.00'); // ARPU (300/2)
});

test('payment analytics report displays correctly', function () {
    $admin = User::factory()->admin()->create();
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
        'created_at' => Carbon::parse('2024-01-25'),
    ]);

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->set('reportType', 'payments')
        ->assertSee('3') // Total orders
        ->assertSee('2') // Successful payments
        ->assertSee('1') // Failed payments
        ->assertSee('66.7%') // Success rate
        ->assertSee('Payment Trends')
        ->assertSee('2024-01');
});

test('growth metrics report displays correctly', function () {
    $admin = User::factory()->admin()->create();
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
        'created_at' => Carbon::parse('2024-02-15'),
    ]);

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->set('reportType', 'growth')
        ->assertSee('New Subscriptions')
        ->assertSee('2024-01')
        ->assertSee('2024-02')
        ->assertSee('Net Growth');
});

test('export functionality works', function () {
    $admin = User::factory()->admin()->create();
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

    $response = Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->call('exportReport');

    expect($response->response()->isRedirection())->toBeFalse();
    expect($response->response()->headers->get('content-type'))->toContain('text/csv');
    expect($response->response()->headers->get('content-disposition'))->toContain('subscription_report_');
});

test('clear filters functionality works', function () {
    $admin = User::factory()->admin()->create();

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->set('selectedCourse', '1')
        ->set('selectedMonth', '12')
        ->set('subscriptionStatus', 'active')
        ->set('paymentStatus', 'paid')
        ->call('clearFilters')
        ->assertSet('selectedCourse', '')
        ->assertSet('selectedMonth', '')
        ->assertSet('subscriptionStatus', '')
        ->assertSet('paymentStatus', '');
});

test('filter summary displays correctly', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create(['name' => 'Test Course']);

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->set('selectedCourse', $course->id)
        ->set('selectedYear', '2024')
        ->set('selectedMonth', '6')
        ->set('subscriptionStatus', 'active')
        ->assertSee('Active Filters:')
        ->assertSee('Course: Test Course')
        ->assertSee('Year: 2024')
        ->assertSee('Month: 6')
        ->assertSee('Subscription Status: active');
});

test('empty state displays when no data matches filters', function () {
    $admin = User::factory()->admin()->create();

    Volt::test('admin.subscription-reports')
        ->actingAs($admin)
        ->set('reportType', 'courses')
        ->assertSee('No course data found')
        ->assertSee('No course performance data matches your current filters');
});

test('subscription reports navigation link appears in sidebar for admin', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertSee('Subscription Reports');
    $response->assertSee(route('admin.reports.subscriptions'));
});

test('subscription reports navigation link does not appear for non-admin users', function () {
    $student = User::factory()->student()->create();

    $response = $this->actingAs($student)->get('/dashboard');

    $response->assertDontSee('Subscription Reports');
});
