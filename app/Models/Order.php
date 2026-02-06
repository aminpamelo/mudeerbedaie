<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'enrollment_id',
        'student_id',
        'course_id',
        'stripe_invoice_id',
        'stripe_charge_id',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'status',
        'period_start',
        'period_end',
        'billing_reason',
        'payment_method',
        'paid_at',
        'failed_at',
        'failure_reason',
        'receipt_url',
        'stripe_fee',
        'net_amount',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'stripe_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'failure_reason' => 'array',
        'metadata' => 'array',
    ];

    // Order statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_VOID = 'void';

    // Billing reasons
    public const REASON_SUBSCRIPTION_CREATE = 'subscription_create';

    public const REASON_SUBSCRIPTION_CYCLE = 'subscription_cycle';

    public const REASON_SUBSCRIPTION_UPDATE = 'subscription_update';

    public const REASON_SUBSCRIPTION_THRESHOLD = 'subscription_threshold';

    public const REASON_MANUAL = 'manual';

    // Payment methods
    public const PAYMENT_METHOD_STRIPE = 'stripe';

    public const PAYMENT_METHOD_MANUAL = 'manual';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PAID => 'Paid',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_VOID => 'Void',
        ];
    }

    public static function getBillingReasons(): array
    {
        return [
            self::REASON_SUBSCRIPTION_CREATE => 'Subscription Created',
            self::REASON_SUBSCRIPTION_CYCLE => 'Subscription Cycle',
            self::REASON_SUBSCRIPTION_UPDATE => 'Subscription Updated',
            self::REASON_SUBSCRIPTION_THRESHOLD => 'Usage Threshold',
            self::REASON_MANUAL => 'Manual',
        ];
    }

    public static function getPaymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_STRIPE => 'Stripe Card',
            self::PAYMENT_METHOD_MANUAL => 'Manual Payment',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    // Relationships
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Status check methods
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeInPeriod($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('period_start', [$startDate, $endDate]);
    }

    // Helper methods
    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function markAsFailed(?array $failureReason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $failureReason,
        ]);
    }

    public function markAsRefunded(): void
    {
        $this->update([
            'status' => self::STATUS_REFUNDED,
        ]);
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'RM '.number_format($this->amount, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? ucfirst($this->status);
    }

    public function getBillingReasonLabelAttribute(): string
    {
        return self::getBillingReasons()[$this->billing_reason] ?? ucfirst(str_replace('_', ' ', $this->billing_reason));
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getPaymentMethods()[$this->payment_method] ?? ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    public function getPeriodDescription(): string
    {
        return $this->period_start->format('M j').' - '.$this->period_end->format('M j, Y');
    }

    // Financial breakdown helpers
    public function getItemsSubtotal(): float
    {
        return $this->items->sum('total_price');
    }

    public function getDiscountAmount(): float
    {
        return $this->metadata['discount_amount'] ?? 0;
    }

    public function getShippingCost(): float
    {
        return $this->metadata['shipping_cost'] ?? 0;
    }

    public function getTaxAmount(): float
    {
        return $this->metadata['tax_amount'] ?? 0;
    }

    public function getSubtotalBeforeDiscount(): float
    {
        $itemsSubtotal = $this->getItemsSubtotal();
        $discount = $this->getDiscountAmount();

        return $discount > 0 ? ($itemsSubtotal + $discount) : $itemsSubtotal;
    }

    public function hasDiscount(): bool
    {
        return $this->getDiscountAmount() > 0;
    }

    public function getCouponCode(): ?string
    {
        return $this->metadata['coupon_code'] ?? null;
    }

    // Formatted financial values
    public function getFormattedSubtotalAttribute(): string
    {
        return 'RM '.number_format($this->getItemsSubtotal(), 2);
    }

    public function getFormattedDiscountAttribute(): string
    {
        return 'RM '.number_format($this->getDiscountAmount(), 2);
    }

    public function getFormattedShippingAttribute(): string
    {
        return 'RM '.number_format($this->getShippingCost(), 2);
    }

    public function getFormattedTaxAttribute(): string
    {
        return 'RM '.number_format($this->getTaxAmount(), 2);
    }

    public function getFormattedSubtotalBeforeDiscountAttribute(): string
    {
        return 'RM '.number_format($this->getSubtotalBeforeDiscount(), 2);
    }

    /**
     * Get detailed failure information with human-readable explanations.
     *
     * @return array{code: ?string, message: string, explanation: string, next_steps: string, severity: string}
     */
    public function getFailureDetails(): array
    {
        $failureReason = $this->failure_reason;

        if (empty($failureReason)) {
            return [
                'code' => null,
                'message' => 'Payment failed',
                'explanation' => 'The payment could not be processed.',
                'next_steps' => 'Please contact the student to retry the payment.',
                'severity' => 'unknown',
            ];
        }

        $code = $failureReason['failure_code'] ?? $failureReason['code'] ?? null;
        $message = $failureReason['failure_message'] ?? $failureReason['message'] ?? 'Payment failed';
        $reason = $failureReason['reason'] ?? $failureReason['decline_code'] ?? null;

        // Use seller_message from Stripe if available (most human-readable)
        $sellerMessage = $failureReason['seller_message'] ?? null;

        $details = self::getStripeFailureCodeDetails($code, $reason);

        return [
            'code' => $code,
            'message' => $sellerMessage ?: $message,
            'explanation' => $details['explanation'],
            'next_steps' => $details['next_steps'],
            'severity' => $details['severity'],
        ];
    }

    /**
     * Map Stripe failure codes to human-readable explanations.
     *
     * @return array{explanation: string, next_steps: string, severity: string}
     */
    public static function getStripeFailureCodeDetails(?string $code, ?string $reason = null): array
    {
        $codeMap = [
            'card_declined' => [
                'explanation' => 'The card was declined by the issuing bank. This can happen for various reasons including insufficient funds, card restrictions, or fraud prevention.',
                'next_steps' => 'Ask the student to contact their bank for details, or try a different payment method.',
                'severity' => 'high',
            ],
            'insufficient_funds' => [
                'explanation' => 'The card does not have enough funds to complete the payment.',
                'next_steps' => 'Ask the student to add funds to their account or use a different card.',
                'severity' => 'medium',
            ],
            'expired_card' => [
                'explanation' => 'The card has expired and is no longer valid for transactions.',
                'next_steps' => 'Ask the student to update their payment method with a valid, non-expired card.',
                'severity' => 'medium',
            ],
            'incorrect_cvc' => [
                'explanation' => 'The CVC/CVV security code entered was incorrect.',
                'next_steps' => 'Ask the student to re-enter their card details with the correct CVC code.',
                'severity' => 'low',
            ],
            'incorrect_number' => [
                'explanation' => 'The card number entered is incorrect or invalid.',
                'next_steps' => 'Ask the student to re-enter their card number carefully.',
                'severity' => 'low',
            ],
            'processing_error' => [
                'explanation' => 'A temporary error occurred while processing the payment. This is usually a transient issue with the payment processor.',
                'next_steps' => 'The payment can be retried. If the issue persists, contact Stripe support.',
                'severity' => 'low',
            ],
            'authentication_required' => [
                'explanation' => 'The card requires 3D Secure authentication (SCA) but the authentication was not completed.',
                'next_steps' => 'Ask the student to retry the payment and complete the authentication step when prompted.',
                'severity' => 'medium',
            ],
            'card_not_supported' => [
                'explanation' => 'The card type is not supported for this type of transaction.',
                'next_steps' => 'Ask the student to use a different card (Visa, Mastercard, etc.).',
                'severity' => 'medium',
            ],
            'currency_not_supported' => [
                'explanation' => 'The card does not support the requested currency (MYR).',
                'next_steps' => 'Ask the student to use a card that supports MYR transactions.',
                'severity' => 'medium',
            ],
            'do_not_honor' => [
                'explanation' => 'The card issuing bank declined the transaction without providing a specific reason.',
                'next_steps' => 'Ask the student to contact their bank to authorize the transaction, or try a different card.',
                'severity' => 'high',
            ],
            'fraudulent' => [
                'explanation' => 'The payment was flagged as potentially fraudulent by Stripe\'s fraud detection system.',
                'next_steps' => 'Review the transaction carefully. If legitimate, the student should contact their bank.',
                'severity' => 'critical',
            ],
            'generic_decline' => [
                'explanation' => 'The card was declined for an unspecified reason by the issuing bank.',
                'next_steps' => 'Ask the student to contact their bank for more details or try a different payment method.',
                'severity' => 'high',
            ],
            'invalid_account' => [
                'explanation' => 'The card or account associated with the card is invalid.',
                'next_steps' => 'Ask the student to use a different card or contact their bank.',
                'severity' => 'high',
            ],
            'lost_card' => [
                'explanation' => 'The card has been reported as lost and is no longer active.',
                'next_steps' => 'Ask the student to use a different card.',
                'severity' => 'critical',
            ],
            'stolen_card' => [
                'explanation' => 'The card has been reported as stolen and is no longer active.',
                'next_steps' => 'Ask the student to use a different card.',
                'severity' => 'critical',
            ],
            'card_velocity_exceeded' => [
                'explanation' => 'The card has exceeded its transaction limit (too many transactions in a short period).',
                'next_steps' => 'Ask the student to wait and try again later, or use a different card.',
                'severity' => 'medium',
            ],
            'withdrawal_count_limit_exceeded' => [
                'explanation' => 'The card has exceeded the number of allowed transactions for the period.',
                'next_steps' => 'Ask the student to try again later or use a different card.',
                'severity' => 'medium',
            ],
            'manual_rejection' => [
                'explanation' => 'The payment was manually rejected by an administrator.',
                'next_steps' => 'Review the rejection notes for details.',
                'severity' => 'medium',
            ],
        ];

        // Check decline reason (from charge outcome)
        $reasonMap = [
            'highest_risk_level' => [
                'explanation' => 'Stripe\'s fraud detection flagged this payment as the highest risk level.',
                'next_steps' => 'Review carefully before allowing a retry. The student should verify their identity.',
                'severity' => 'critical',
            ],
            'elevated_risk_level' => [
                'explanation' => 'Stripe\'s fraud detection flagged this payment as elevated risk.',
                'next_steps' => 'Proceed with caution. Ask the student to verify their identity if needed.',
                'severity' => 'high',
            ],
        ];

        if ($reason && isset($reasonMap[$reason])) {
            return $reasonMap[$reason];
        }

        if ($code && isset($codeMap[$code])) {
            return $codeMap[$code];
        }

        return [
            'explanation' => 'The payment could not be processed. The payment processor returned an error.',
            'next_steps' => 'Ask the student to try again or use a different payment method. Contact support if the issue persists.',
            'severity' => 'unknown',
        ];
    }

    // Generate unique order number
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');

        // Get the last order number for today
        $lastOrder = self::where('order_number', 'like', $prefix.$date.'%')
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.$date.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Create order from Stripe invoice
    public static function createFromStripeInvoice(array $stripeInvoice, Enrollment $enrollment): self
    {
        // Use total amount for failed invoices, amount_paid for successful ones
        $amount = $stripeInvoice['status'] === 'paid'
            ? $stripeInvoice['amount_paid'] / 100
            : ($stripeInvoice['total'] ?? $stripeInvoice['amount_due'] ?? $stripeInvoice['amount_paid']) / 100;

        // Determine status based on Stripe invoice status
        $status = match ($stripeInvoice['status']) {
            'paid' => self::STATUS_PAID,
            'open' => self::STATUS_PENDING,
            'draft' => self::STATUS_PENDING,
            'uncollectible' => self::STATUS_FAILED,
            'void' => self::STATUS_VOID,
            default => self::STATUS_FAILED,
        };

        return self::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'stripe_invoice_id' => $stripeInvoice['id'],
            'stripe_charge_id' => $stripeInvoice['charge'] ?? null,
            'stripe_payment_intent_id' => $stripeInvoice['payment_intent'] ?? null,
            'amount' => $amount,
            'currency' => strtoupper($stripeInvoice['currency']),
            'status' => $status,
            'period_start' => Carbon::createFromTimestamp($stripeInvoice['period_start']),
            'period_end' => Carbon::createFromTimestamp($stripeInvoice['period_end']),
            'billing_reason' => $stripeInvoice['billing_reason'] ?? self::REASON_SUBSCRIPTION_CYCLE,
            'payment_method' => self::PAYMENT_METHOD_STRIPE,
            'paid_at' => $stripeInvoice['status'] === 'paid' ? now() : null,
            'failed_at' => in_array($stripeInvoice['status'], ['uncollectible']) ? now() : null,
            'receipt_url' => $stripeInvoice['hosted_invoice_url'] ?? null,
            'stripe_fee' => isset($stripeInvoice['application_fee_amount']) ? $stripeInvoice['application_fee_amount'] / 100 : null,
            'net_amount' => ($stripeInvoice['amount_paid'] - ($stripeInvoice['application_fee_amount'] ?? 0)) / 100,
            'metadata' => [
                'stripe_subscription_id' => $stripeInvoice['subscription'] ?? null,
                'stripe_customer_id' => $stripeInvoice['customer'] ?? null,
            ],
        ]);
    }
}
