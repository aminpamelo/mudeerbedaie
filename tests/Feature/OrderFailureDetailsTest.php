<?php

declare(strict_types=1);

use App\Models\Order;

it('returns default failure details when failure_reason is null', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => null,
    ]);

    $details = $order->getFailureDetails();

    expect($details)
        ->toHaveKeys(['code', 'message', 'explanation', 'next_steps', 'severity'])
        ->and($details['code'])->toBeNull()
        ->and($details['severity'])->toBe('unknown');
});

it('returns correct details for card_declined failure code', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined.',
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['code'])->toBe('card_declined')
        ->and($details['message'])->toBe('Your card was declined.')
        ->and($details['severity'])->toBe('high')
        ->and($details['explanation'])->toContain('issuing bank');
});

it('returns correct details for insufficient_funds failure code', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'failure_code' => 'insufficient_funds',
            'failure_message' => 'Your card has insufficient funds.',
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['code'])->toBe('insufficient_funds')
        ->and($details['severity'])->toBe('medium');
});

it('returns correct details for expired_card failure code', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'failure_code' => 'expired_card',
            'failure_message' => 'Your card has expired.',
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['code'])->toBe('expired_card')
        ->and($details['severity'])->toBe('medium')
        ->and($details['explanation'])->toContain('expired');
});

it('returns critical severity for fraudulent failure code', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'failure_code' => 'fraudulent',
            'failure_message' => 'This payment was flagged as fraudulent.',
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['severity'])->toBe('critical');
});

it('returns critical severity for stolen_card failure code', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'failure_code' => 'stolen_card',
            'failure_message' => 'The card has been reported as stolen.',
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['severity'])->toBe('critical');
});

it('handles alternative failure_reason key format with code/message keys', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'code' => 'processing_error',
            'message' => 'An error occurred while processing.',
            'reason' => null,
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['code'])->toBe('processing_error')
        ->and($details['message'])->toBe('An error occurred while processing.')
        ->and($details['severity'])->toBe('low');
});

it('returns fallback details for unknown failure codes', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'failure_code' => 'some_unknown_code',
            'failure_message' => 'Something happened.',
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['code'])->toBe('some_unknown_code')
        ->and($details['message'])->toBe('Something happened.')
        ->and($details['severity'])->toBe('unknown');
});

it('returns manual_rejection details for admin-rejected payments', function () {
    $order = Order::factory()->make([
        'status' => Order::STATUS_FAILED,
        'failure_reason' => [
            'failure_code' => 'manual_rejection',
            'failure_message' => 'Payment verification failed',
            'rejected_by_admin' => true,
        ],
    ]);

    $details = $order->getFailureDetails();

    expect($details['code'])->toBe('manual_rejection')
        ->and($details['severity'])->toBe('medium')
        ->and($details['explanation'])->toContain('manually rejected');
});

it('prioritizes outcome reason over failure code when available', function () {
    $details = Order::getStripeFailureCodeDetails('card_declined', 'highest_risk_level');

    expect($details['severity'])->toBe('critical')
        ->and($details['explanation'])->toContain('highest risk level');
});
