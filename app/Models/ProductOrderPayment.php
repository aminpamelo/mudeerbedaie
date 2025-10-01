<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOrderPayment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_provider',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'reference_number',
        'gateway_response_id',
        'paid_at',
        'failed_at',
        'refunded_at',
        'failure_reason',
        'gateway_response',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'gateway_response' => 'array',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    // Status management
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->order->addSystemNote('Payment completed', [
            'payment_id' => $this->id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        $this->order->addSystemNote('Payment failed', [
            'payment_id' => $this->id,
            'reason' => $reason,
        ]);
    }

    public function markAsRefunded(): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        $this->order->addSystemNote('Payment refunded', [
            'payment_id' => $this->id,
            'amount' => $this->amount,
        ]);
    }

    // Helper methods
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeRefunded(): bool
    {
        return $this->isCompleted() && ! $this->isRefunded();
    }

    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }

    public function getPaymentMethodDisplay(): string
    {
        return match ($this->payment_method) {
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'fpx' => 'FPX',
            'grabpay' => 'GrabPay',
            'boost' => 'Boost',
            default => ucfirst(str_replace('_', ' ', $this->payment_method))
        };
    }
}
