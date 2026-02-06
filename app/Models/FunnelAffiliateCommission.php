<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelAffiliateCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'funnel_id',
        'funnel_order_id',
        'product_order_id',
        'session_id',
        'commission_type',
        'commission_rate',
        'order_amount',
        'commission_amount',
        'status',
        'approved_at',
        'approved_by',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'order_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    // Relationships
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(FunnelAffiliate::class, 'affiliate_id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function funnelOrder(): BelongsTo
    {
        return $this->belongsTo(FunnelOrder::class);
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FunnelSession::class, 'session_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
    }

    public function reject(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $userId,
            'notes' => $notes,
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function getFormattedAmount(): string
    {
        return 'RM '.number_format($this->commission_amount, 2);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeForAffiliate($query, int $affiliateId)
    {
        return $query->where('affiliate_id', $affiliateId);
    }

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }
}
