<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Demonstration test that shows how subscription payment scheduling works
 * without requiring actual Stripe integration. This test demonstrates the
 * complete subscription lifecycle and payment scheduling.
 */
test('complete subscription payment lifecycle demonstration', function () {
    // Step 1: Create a student enrollment with monthly subscription
    $user = User::factory()->create(['name' => 'Test Student']);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create(['name' => 'Quran Reading Course']);
    $feeSettings = CourseFeeSettings::factory()->create([
        'course_id' => $course->id,
        'fee_amount' => 50.00,
        'billing_cycle' => 'monthly',
        'currency' => 'MYR',
    ]);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'enrolled',
        'stripe_subscription_id' => 'sub_demo123',
        'subscription_status' => 'active',
    ]);

    // Step 2: Simulate the first payment (subscription creation)
    $firstPaymentDate = Carbon::parse('2024-01-01');
    $firstOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 50.00,
        'status' => Order::STATUS_PAID,
        'period_start' => $firstPaymentDate,
        'period_end' => $firstPaymentDate->copy()->addMonth(),
        'billing_reason' => Order::REASON_SUBSCRIPTION_CREATE,
        'paid_at' => $firstPaymentDate,
    ]);

    // Step 3: Simulate monthly recurring payments
    $payments = collect();
    for ($month = 1; $month <= 6; $month++) {
        $periodStart = $firstPaymentDate->copy()->addMonths($month);
        $periodEnd = $periodStart->copy()->addMonth();
        $paymentDate = $periodStart->copy()->addDays(1); // Payment processes 1 day into period

        $order = Order::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => 50.00,
            'status' => Order::STATUS_PAID,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'billing_reason' => Order::REASON_SUBSCRIPTION_CYCLE,
            'paid_at' => $paymentDate,
        ]);

        $payments->push($order);
    }

    // Step 4: Simulate a failed payment in month 7
    $failedPeriodStart = $firstPaymentDate->copy()->addMonths(7);
    $failedOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 50.00,
        'status' => Order::STATUS_FAILED,
        'period_start' => $failedPeriodStart,
        'period_end' => $failedPeriodStart->copy()->addMonth(),
        'billing_reason' => Order::REASON_SUBSCRIPTION_CYCLE,
        'failed_at' => $failedPeriodStart->copy()->addDays(1),
        'failure_reason' => [
            'code' => 'card_declined',
            'message' => 'Insufficient funds',
        ],
    ]);

    // Update enrollment to past_due status
    $enrollment->update(['subscription_status' => 'past_due']);

    // Step 5: Verify payment history and calculations
    $allOrders = $enrollment->orders()->orderBy('period_start')->get();
    expect($allOrders)->toHaveCount(8); // 1 creation + 6 successful + 1 failed

    // Verify total paid amount
    $totalPaid = $enrollment->getTotalPaidAmountAttribute();
    expect($totalPaid)->toBe(350.00); // 7 successful payments × 50.00

    // Verify payment pattern
    $paidOrders = $enrollment->paidOrders()->orderBy('period_start')->get();
    expect($paidOrders)->toHaveCount(7);

    // Check billing intervals
    for ($i = 1; $i < $paidOrders->count(); $i++) {
        $previousOrder = $paidOrders[$i - 1];
        $currentOrder = $paidOrders[$i];

        $daysBetween = $previousOrder->period_end->diffInDays($currentOrder->period_start);
        expect($daysBetween)->toBeLessThanOrEqual(1); // Should be same day or next day
    }

    // Step 6: Demonstrate "Next Payment" calculation
    $latestSuccessfulOrder = $paidOrders->last();
    $nextPaymentDue = $latestSuccessfulOrder->period_end;
    $daysUntilNextPayment = now()->diffInDays($nextPaymentDue);

    if ($nextPaymentDue->isFuture()) {
        // This would show "In X days" in the UI
        expect($daysUntilNextPayment)->toBeGreaterThan(0);
    } else {
        // Payment is overdue
        expect($nextPaymentDue->isPast())->toBeTrue();
    }

    // Step 7: Simulate subscription cancellation (first restore to active status)
    $enrollment->update(['subscription_status' => 'active']);
    $cancellationDate = now()->addDays(15);
    $enrollment->update([
        'subscription_cancel_at' => $cancellationDate,
    ]);

    expect($enrollment->isPendingCancellation())->toBeTrue();
    expect($enrollment->getSubscriptionStatusLabel())->toBe('Pending Cancellation');
});

test('subscription payment timing matches expected schedule', function () {
    // Test that demonstrates exactly what happens in the "In 2 days" scenario
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->withActiveSubscription()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Current billing period (what the student has already paid for)
    $currentPeriodStart = now()->subDays(28); // Started 28 days ago
    $currentPeriodEnd = now()->addDays(2);    // Ends in 2 days

    $currentOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'amount' => 50.00,
        'status' => Order::STATUS_PAID,
        'period_start' => $currentPeriodStart,
        'period_end' => $currentPeriodEnd,
        'paid_at' => $currentPeriodStart->copy()->addDay(),
    ]);

    // Calculate when next payment should be processed
    $daysUntilNextPayment = (int) now()->diffInDays($currentPeriodEnd, false);

    expect($daysUntilNextPayment)->toBeBetween(1, 2); // Allow for slight timing differences

    // Simulate what happens when Stripe processes the next payment (in 2 days)
    $nextPeriodStart = $currentPeriodEnd;
    $nextPeriodEnd = $nextPeriodStart->copy()->addMonth();

    // This is what would happen when Stripe sends the invoice.payment_succeeded webhook
    $nextOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'amount' => 50.00,
        'status' => Order::STATUS_PAID,
        'period_start' => $nextPeriodStart,
        'period_end' => $nextPeriodEnd,
        'billing_reason' => Order::REASON_SUBSCRIPTION_CYCLE,
        'paid_at' => $nextPeriodStart->copy()->addHour(), // Processed shortly after period starts
    ]);

    // Verify the payment timeline
    expect($nextOrder->period_start->toDateString())->toBe($currentOrder->period_end->toDateString());
    expect($nextOrder->period_start->diffInDays($nextOrder->period_end))->toBeBetween(28, 31);

    // After this payment, the next payment would be due in ~30 days
    $followingPaymentDue = $nextOrder->period_end;
    expect((int) now()->diffInDays($followingPaymentDue, false))->toBeBetween(28, 32);
});

test('subscription handles different billing cycles correctly', function () {
    $testScenarios = [
        ['monthly', 30, 'month'],
        ['quarterly', 90, 'quarter'],
        ['yearly', 365, 'year'],
    ];

    foreach ($testScenarios as [$cycle, $expectedDays, $period]) {
        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create(['name' => "Test {$cycle} course"]);

        $feeSettings = CourseFeeSettings::factory()->create([
            'course_id' => $course->id,
            'billing_cycle' => $cycle,
            'fee_amount' => $cycle === 'monthly' ? 50.00 : ($cycle === 'quarterly' ? 150.00 : 600.00),
        ]);

        $enrollment = Enrollment::factory()->withActiveSubscription()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        // Create payment history for this billing cycle
        $startDate = now()->subDays($expectedDays + 10); // Started over a full cycle ago

        $firstOrder = Order::factory()->create([
            'enrollment_id' => $enrollment->id,
            'amount' => $feeSettings->fee_amount,
            'status' => Order::STATUS_PAID,
            'period_start' => $startDate,
            'period_end' => $startDate->copy()->add(1, $period),
            'paid_at' => $startDate->copy()->addHour(),
        ]);

        // Verify billing period length
        $actualDays = $firstOrder->period_start->diffInDays($firstOrder->period_end);

        if ($cycle === 'monthly') {
            expect($actualDays)->toBeBetween(28, 31);
        } elseif ($cycle === 'quarterly') {
            expect($actualDays)->toBeBetween(89, 92);
        } else { // yearly
            expect($actualDays)->toBeBetween(365, 366);
        }

        // Verify Stripe interval mapping
        expect($feeSettings->getStripeInterval())->toBe($cycle === 'quarterly' ? 'month' : ($cycle === 'yearly' ? 'year' : 'month'));
        expect($feeSettings->getStripeIntervalCount())->toBe($cycle === 'quarterly' ? 3 : 1);
    }
});

test('subscription payment failure recovery workflow', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_recovery_test',
        'subscription_status' => 'past_due',
    ]);

    // Step 1: First payment attempt fails
    $failedOrder = Order::factory()->failed()->create([
        'enrollment_id' => $enrollment->id,
        'amount' => 50.00,
        'period_start' => now()->subDays(3),
        'period_end' => now()->addDays(27),
        'failed_at' => now()->subDays(3),
        'failure_reason' => [
            'code' => 'insufficient_funds',
            'message' => 'Your card has insufficient funds.',
        ],
    ]);

    expect($enrollment->isSubscriptionPastDue())->toBeTrue();
    expect($enrollment->failedOrders)->toHaveCount(1);

    // Step 2: Stripe retries payment after customer updates payment method
    // This would happen through webhook when payment succeeds
    $recoveredOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'amount' => 50.00,
        'status' => Order::STATUS_PAID,
        'period_start' => $failedOrder->period_start,
        'period_end' => $failedOrder->period_end,
        'billing_reason' => Order::REASON_SUBSCRIPTION_CYCLE,
        'paid_at' => now()->subHours(2),
    ]);

    // Update subscription status back to active
    $enrollment->update(['subscription_status' => 'active']);

    expect($enrollment->isSubscriptionActive())->toBeTrue();
    expect($enrollment->paidOrders)->toHaveCount(1);
    expect($enrollment->getTotalPaidAmountAttribute())->toBe(50.00);

    // Next payment should be scheduled normally from the recovered period end
    $nextPaymentDue = $recoveredOrder->period_end;
    expect($nextPaymentDue->isFuture())->toBeTrue();
    expect((int) now()->diffInDays($nextPaymentDue, false))->toBeBetween(25, 30);
});

test('subscription with trial period payment scheduling', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();

    $feeSettings = CourseFeeSettings::factory()->withTrial(14)->create([
        'course_id' => $course->id,
        'fee_amount' => 75.00,
        'billing_cycle' => 'monthly',
    ]);

    $enrollment = Enrollment::factory()->withTrialSubscription()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    expect($enrollment->isSubscriptionTrialing())->toBeTrue();
    expect($feeSettings->hasTrialPeriod())->toBeTrue();
    expect($feeSettings->trial_period_days)->toBe(14);

    // During trial, no orders should be created yet
    expect($enrollment->orders)->toHaveCount(0);

    // Simulate trial ending and first payment
    $trialEndDate = now()->subDays(1); // Trial ended yesterday
    $enrollment->update(['subscription_status' => 'active']);

    $firstPaidOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'amount' => 75.00,
        'status' => Order::STATUS_PAID,
        'period_start' => $trialEndDate,
        'period_end' => $trialEndDate->copy()->addMonth(),
        'billing_reason' => Order::REASON_SUBSCRIPTION_CYCLE,
        'paid_at' => $trialEndDate->copy()->addHours(2),
    ]);

    expect($enrollment->fresh()->isSubscriptionActive())->toBeTrue();
    expect($enrollment->paidOrders)->toHaveCount(1);
    expect((float) $firstPaidOrder->amount)->toBe(75.00);

    // Next payment should be scheduled normally from here
    $nextPaymentDue = $firstPaidOrder->period_end;
    expect((int) now()->diffInDays($nextPaymentDue, false))->toBeBetween(28, 32);
});
