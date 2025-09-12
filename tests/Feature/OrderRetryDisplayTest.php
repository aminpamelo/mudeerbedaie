<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('order display hides failure reason when payment is successfully retried', function () {
    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_retry_test',
        'subscription_status' => 'active',
    ]);

    // Create an order that initially failed
    $order = Order::factory()->failed()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 10.00,
        'failed_at' => now(),
        'failure_reason' => [
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined.',
        ],
    ]);

    // Verify initial failed state
    expect($order->isFailed())->toBeTrue();
    expect($order->failure_reason)->not->toBeNull();

    // Test the admin orders show component with failed order
    $failedOrderComponent = Volt::test('admin.orders-show', ['order' => $order]);
    $failedOrderComponent->assertSee('Failure Reason');
    $failedOrderComponent->assertSee('Your card was declined.');

    // Now simulate successful payment retry
    $order->markAsPaid();
    $order->refresh();

    // Verify order is now paid and failure data is cleared
    expect($order->isPaid())->toBeTrue();
    expect($order->isFailed())->toBeFalse();
    expect($order->failure_reason)->toBeNull();
    expect($order->failed_at)->toBeNull();
    expect($order->paid_at)->not->toBeNull();

    // Test the admin orders show component with paid order
    $paidOrderComponent = Volt::test('admin.orders-show', ['order' => $order]);
    $paidOrderComponent->assertDontSee('Failure Reason');
    $paidOrderComponent->assertDontSee('Your card was declined.');
    $paidOrderComponent->assertSee('Paid'); // Should show paid status
});

test('order display shows failure reason only for currently failed orders', function () {
    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_currently_failed',
        'subscription_status' => 'active',
    ]);

    // Create an order that is currently failed
    $failedOrder = Order::factory()->failed()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 15.00,
        'failed_at' => now(),
        'failure_reason' => [
            'failure_code' => 'insufficient_funds',
            'failure_message' => 'Your card has insufficient funds.',
        ],
    ]);

    // Create another order that was paid (simulating old failure_reason data that wasn't cleared)
    $paidOrderWithOldFailure = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => Order::STATUS_PAID,
        'amount' => 15.00,
        'paid_at' => now(),
        'failure_reason' => [
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined.',
        ], // This simulates old data where failure_reason wasn't cleared
    ]);

    // Test failed order - should show failure reason
    $failedComponent = Volt::test('admin.orders-show', ['order' => $failedOrder]);
    $failedComponent->assertSee('Failure Reason');
    $failedComponent->assertSee('Your card has insufficient funds.');

    // Test paid order with old failure data - should NOT show failure reason
    $paidComponent = Volt::test('admin.orders-show', ['order' => $paidOrderWithOldFailure]);
    $paidComponent->assertDontSee('Failure Reason');
    $paidComponent->assertDontSee('Your card was declined.');
    $paidComponent->assertSee('Paid');
});

test('markAsPaid method clears failure data correctly', function () {
    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Create a failed order
    $order = Order::factory()->failed()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 20.00,
        'failed_at' => now()->subHour(),
        'failure_reason' => [
            'failure_code' => 'processing_error',
            'failure_message' => 'An error occurred while processing your card.',
        ],
    ]);

    // Verify initial failed state
    expect($order->status)->toBe(Order::STATUS_FAILED);
    expect($order->failed_at)->not->toBeNull();
    expect($order->failure_reason)->not->toBeNull();
    expect($order->paid_at)->toBeNull();

    // Mark as paid
    $order->markAsPaid();
    $order->refresh();

    // Verify all failure data is cleared and paid data is set
    expect($order->status)->toBe(Order::STATUS_PAID);
    expect($order->paid_at)->not->toBeNull();
    expect($order->failed_at)->toBeNull();
    expect($order->failure_reason)->toBeNull();
    expect($order->isPaid())->toBeTrue();
    expect($order->isFailed())->toBeFalse();
});
