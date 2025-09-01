<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseFeeSettings extends Model
{
    protected $fillable = [
        'course_id',
        'fee_amount',
        'billing_cycle',
        'currency',
        'is_recurring',
        'stripe_price_id',
        'trial_period_days',
        'setup_fee',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'is_recurring' => 'boolean',
            'trial_period_days' => 'integer',
            'setup_fee' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function getFormattedFeeAttribute(): string
    {
        return 'RM '.number_format($this->fee_amount, 2);
    }

    public function getBillingCycleLabelAttribute(): string
    {
        return match ($this->billing_cycle) {
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'yearly' => 'Yearly',
            default => ucfirst($this->billing_cycle),
        };
    }

    public function getFormattedSetupFeeAttribute(): string
    {
        return 'RM '.number_format($this->setup_fee, 2);
    }

    // Stripe price methods
    public function hasSyncedStripePrice(): bool
    {
        return ! empty($this->stripe_price_id);
    }

    public function hasTrialPeriod(): bool
    {
        return $this->trial_period_days > 0;
    }

    public function hasSetupFee(): bool
    {
        return $this->setup_fee > 0;
    }

    public function getStripeInterval(): string
    {
        return match ($this->billing_cycle) {
            'monthly' => 'month',
            'quarterly' => 'month', // Stripe doesn't have quarter, use 3 months
            'yearly' => 'year',
            default => 'month',
        };
    }

    public function getStripeIntervalCount(): int
    {
        return match ($this->billing_cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 1,
            default => 1,
        };
    }
}
