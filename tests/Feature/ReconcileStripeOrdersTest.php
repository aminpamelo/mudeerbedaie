<?php

declare(strict_types=1);

use App\Models\Enrollment;
use App\Models\Order;
use App\Services\SettingsService;
use App\Services\StripeService;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Bind a partial StripeService whose listSubscriptionInvoices() returns the given invoice arrays.
 *
 * @param  array<int, array<string, mixed>>  $invoices
 */
function bindFakeStripe(array $invoices): void
{
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')->andReturn('sk_test_dummy');
    app()->instance(SettingsService::class, $settings);

    $stripeService = Mockery::mock(StripeService::class)->makePartial();
    $stripeService->shouldReceive('listSubscriptionInvoices')->andReturn($invoices);
    app()->instance(StripeService::class, $stripeService);
}

it('backfills a missing paid order and maps it to the line-item service month', function () {
    $enrollment = Enrollment::factory()->withActiveSubscription()->create([
        'stripe_subscription_id' => 'sub_test_recon',
    ]);

    // Invoice-level period lags one cycle behind (the Stripe renewal gotcha);
    // the true service window lives on the line item (25 May -> 25 Jun).
    $invoice = [
        'id' => 'in_may_renewal',
        'subscription' => 'sub_test_recon',
        'customer' => 'cus_test',
        'status' => 'paid',
        'currency' => 'myr',
        'amount_paid' => 5600,
        'total' => 5600,
        'amount_due' => 5600,
        'billing_reason' => 'subscription_cycle',
        'period_start' => Carbon::parse('2026-04-25')->timestamp,
        'period_end' => Carbon::parse('2026-05-25')->timestamp,
        'lines' => ['data' => [[
            'id' => 'il_may',
            'amount' => 5600,
            'description' => 'KELAS VIP',
            'price' => ['id' => 'price_x', 'product' => 'prod_x'],
            'period' => [
                'start' => Carbon::parse('2026-05-25')->timestamp,
                'end' => Carbon::parse('2026-06-25')->timestamp,
            ],
        ]]],
    ];

    bindFakeStripe([$invoice]);

    $this->artisan('stripe:reconcile-orders', ['--subscription' => 'sub_test_recon'])
        ->assertSuccessful();

    $order = Order::where('stripe_invoice_id', 'in_may_renewal')->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(Order::STATUS_PAID)
        ->and((float) $order->amount)->toBe(56.0)
        ->and($order->enrollment_id)->toBe($enrollment->id)
        // The fix: maps to May (line item) not April (invoice level).
        ->and($order->period_start->format('Y-m'))->toBe('2026-05');
});

it('does not duplicate an order that already exists', function () {
    $enrollment = Enrollment::factory()->withActiveSubscription()->create([
        'stripe_subscription_id' => 'sub_test_recon',
    ]);

    Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $enrollment->student_id,
        'course_id' => $enrollment->course_id,
        'stripe_invoice_id' => 'in_existing',
        'status' => Order::STATUS_PAID,
    ]);

    $invoice = [
        'id' => 'in_existing',
        'subscription' => 'sub_test_recon',
        'status' => 'paid',
        'currency' => 'myr',
        'amount_paid' => 5600,
        'period_start' => Carbon::parse('2026-05-25')->timestamp,
        'period_end' => Carbon::parse('2026-06-25')->timestamp,
        'lines' => ['data' => []],
    ];

    bindFakeStripe([$invoice]);

    $this->artisan('stripe:reconcile-orders', ['--subscription' => 'sub_test_recon'])
        ->assertSuccessful();

    expect(Order::where('stripe_invoice_id', 'in_existing')->count())->toBe(1);
});

it('creates nothing on a dry run', function () {
    Enrollment::factory()->withActiveSubscription()->create([
        'stripe_subscription_id' => 'sub_test_recon',
    ]);

    $invoice = [
        'id' => 'in_dry',
        'subscription' => 'sub_test_recon',
        'status' => 'paid',
        'currency' => 'myr',
        'amount_paid' => 5600,
        'period_start' => Carbon::parse('2026-05-25')->timestamp,
        'period_end' => Carbon::parse('2026-06-25')->timestamp,
        'lines' => ['data' => []],
    ];

    bindFakeStripe([$invoice]);

    $this->artisan('stripe:reconcile-orders', ['--subscription' => 'sub_test_recon', '--dry-run' => true])
        ->assertSuccessful();

    expect(Order::where('stripe_invoice_id', 'in_dry')->exists())->toBeFalse();
});
