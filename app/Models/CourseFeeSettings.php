<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseFeeSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'fee_amount',
        'billing_cycle',
        'billing_day',
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
            'billing_day' => 'integer',
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

    // Billing day methods
    public function hasBillingDay(): bool
    {
        return ! is_null($this->billing_day) && $this->billing_day >= 1 && $this->billing_day <= 31;
    }

    public function getBillingDayLabel(): string
    {
        if (! $this->hasBillingDay()) {
            return 'Default (billing cycle start)';
        }

        $suffix = match ($this->billing_day % 10) {
            1 => $this->billing_day === 11 ? 'th' : 'st',
            2 => $this->billing_day === 12 ? 'th' : 'nd',
            3 => $this->billing_day === 13 ? 'th' : 'rd',
            default => 'th',
        };

        return $this->billing_day.$suffix.' of each '.str_replace('ly', '', $this->billing_cycle);
    }

    public function getValidatedBillingDay(): ?int
    {
        if (is_null($this->billing_day)) {
            return null;
        }

        // Ensure billing day is within valid range
        return max(1, min(31, $this->billing_day));
    }
}
