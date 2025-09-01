<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'payment_method_id',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'status',
        'type',
        'amount',
        'currency',
        'stripe_fee',
        'net_amount',
        'description',
        'stripe_metadata',
        'failure_reason',
        'paid_at',
        'failed_at',
        'notes',
        'receipt_url',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'stripe_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'stripe_metadata' => 'array',
        'failure_reason' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // Payment statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const STATUS_REQUIRES_ACTION = 'requires_action';

    public const STATUS_REQUIRES_PAYMENT_METHOD = 'requires_payment_method';

    // Payment types
    public const TYPE_STRIPE_CARD = 'stripe_card';

    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    public const TYPE_CASH = 'cash';

    public const TYPE_CHEQUE = 'cheque';

    public const TYPE_ONLINE_TRANSFER = 'online_transfer';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SUCCEEDED => 'Succeeded',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded',
            self::STATUS_REQUIRES_ACTION => 'Requires Action',
            self::STATUS_REQUIRES_PAYMENT_METHOD => 'Requires Payment Method',
        ];
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_STRIPE_CARD => 'Stripe Card',
            self::TYPE_BANK_TRANSFER => 'Bank Transfer',
            self::TYPE_CASH => 'Cash',
            self::TYPE_CHEQUE => 'Cheque',
            self::TYPE_ONLINE_TRANSFER => 'Online Transfer',
        ];
    }

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // Status check methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isRefunded(): bool
    {
        return in_array($this->status, [self::STATUS_REFUNDED, self::STATUS_PARTIALLY_REFUNDED]);
    }

    public function requiresAction(): bool
    {
        return $this->status === self::STATUS_REQUIRES_ACTION;
    }

    // Type check methods
    public function isStripePayment(): bool
    {
        return $this->type === self::TYPE_STRIPE_CARD;
    }

    public function isBankTransfer(): bool
    {
        return $this->type === self::TYPE_BANK_TRANSFER;
    }

    // Accessors
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::getStatuses()[$this->status] ?? $this->status
        );
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::getTypes()[$this->type] ?? $this->type
        );
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->amount, 2).' '.strtoupper($this->currency)
        );
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCEEDED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeStripePayments($query)
    {
        return $query->where('type', self::TYPE_STRIPE_CARD);
    }

    public function scopeBankTransfers($query)
    {
        return $query->where('type', self::TYPE_BANK_TRANSFER);
    }
}
