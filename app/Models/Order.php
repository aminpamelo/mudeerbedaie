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

    public function getPeriodDescription(): string
    {
        return $this->period_start->format('M j').' - '.$this->period_end->format('M j, Y');
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
        return self::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'stripe_invoice_id' => $stripeInvoice['id'],
            'stripe_charge_id' => $stripeInvoice['charge'] ?? null,
            'stripe_payment_intent_id' => $stripeInvoice['payment_intent'] ?? null,
            'amount' => $stripeInvoice['amount_paid'] / 100, // Convert from cents
            'currency' => strtoupper($stripeInvoice['currency']),
            'status' => $stripeInvoice['status'] === 'paid' ? self::STATUS_PAID : self::STATUS_PENDING,
            'period_start' => Carbon::createFromTimestamp($stripeInvoice['period_start']),
            'period_end' => Carbon::createFromTimestamp($stripeInvoice['period_end']),
            'billing_reason' => $stripeInvoice['billing_reason'] ?? self::REASON_SUBSCRIPTION_CYCLE,
            'paid_at' => $stripeInvoice['status'] === 'paid' ? now() : null,
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
