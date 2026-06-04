<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\StripeService;
use Carbon\Carbon;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('reconcile button imports missing paid orders for the visible students', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    $student = Student::factory()->create();
    ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $class->course_id,
        'stripe_subscription_id' => 'sub_volt_recon',
        'subscription_status' => 'active',
    ]);

    $invoice = [
        'id' => 'in_volt_may',
        'subscription' => 'sub_volt_recon',
        'status' => 'paid',
        'currency' => 'myr',
        'amount_paid' => 5600,
        'period_start' => Carbon::parse('2026-04-25')->timestamp,
        'period_end' => Carbon::parse('2026-05-25')->timestamp,
        'lines' => ['data' => [[
            'id' => 'il_volt_may',
            'amount' => 5600,
            'description' => 'KELAS VIP',
            'price' => ['id' => 'price_x', 'product' => 'prod_x'],
            'period' => [
                'start' => Carbon::parse('2026-05-25')->timestamp,
                'end' => Carbon::parse('2026-06-25')->timestamp,
            ],
        ]]],
    ];

    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')->andReturn('sk_test_dummy');
    app()->instance(SettingsService::class, $settings);

    $stripeService = Mockery::mock(StripeService::class)->makePartial();
    $stripeService->shouldReceive('listSubscriptionInvoices')->andReturn([$invoice]);
    app()->instance(StripeService::class, $stripeService);

    Volt::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->set('activeTab', 'payment-reports')
        ->call('reconcilePayments')
        ->assertHasNoErrors()
        ->assertSet('reconcileResult.status', 'success')
        ->assertSee('Imported 1 missing payment');

    $order = Order::where('stripe_invoice_id', 'in_volt_may')->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->period_start->format('Y-m'))->toBe('2026-05');
});

it('reconcile button surfaces the error message when Stripe fails', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    $student = Student::factory()->create();
    ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $class->course_id,
        'stripe_subscription_id' => 'sub_volt_fail',
        'subscription_status' => 'active',
    ]);

    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')->andReturn('sk_test_dummy');
    app()->instance(SettingsService::class, $settings);

    $stripeService = Mockery::mock(StripeService::class)->makePartial();
    $stripeService->shouldReceive('reconcileSubscriptionOrders')
        ->andThrow(new \Exception('No such subscription: sub_volt_fail'));
    app()->instance(StripeService::class, $stripeService);

    Volt::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->set('activeTab', 'payment-reports')
        ->call('reconcilePayments')
        ->assertHasNoErrors()
        ->assertSet('reconcileResult.status', 'error')
        ->assertSee('No such subscription: sub_volt_fail');

    expect(Order::count())->toBe(0);
});

it('reconcile button warns when there are no stripe-backed enrollments', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    $student = Student::factory()->create();
    ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    // Manual enrollment (no Stripe subscription) should be ignored.
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $class->course_id,
        'stripe_subscription_id' => null,
    ]);

    Volt::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->set('activeTab', 'payment-reports')
        ->call('reconcilePayments')
        ->assertHasNoErrors()
        ->assertSet('reconcileResult.status', 'warning')
        ->assertSee('Nothing to reconcile');

    expect(Order::count())->toBe(0);
});
