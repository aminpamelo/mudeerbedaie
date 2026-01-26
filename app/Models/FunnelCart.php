<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'session_id',
        'step_id',
        'email',
        'phone',
        'cart_data',
        'total_amount',
        'abandoned_at',
        'recovery_status',
        'recovery_emails_sent',
        'recovered_at',
        'product_order_id',
    ];

    protected function casts(): array
    {
        return [
            'cart_data' => 'array',
            'total_amount' => 'decimal:2',
            'abandoned_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FunnelSession::class, 'session_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'step_id');
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->recovery_status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->recovery_status === 'sent';
    }

    public function isRecovered(): bool
    {
        return $this->recovery_status === 'recovered';
    }

    public function isExpired(): bool
    {
        return $this->recovery_status === 'expired';
    }

    // Recovery actions
    public function markAsSent(): void
    {
        $this->increment('recovery_emails_sent');
        $this->update(['recovery_status' => 'sent']);
    }

    public function markAsRecovered(ProductOrder $order): void
    {
        $this->update([
            'recovery_status' => 'recovered',
            'recovered_at' => now(),
            'product_order_id' => $order->id,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['recovery_status' => 'expired']);
    }

    // Recovery eligibility
    public function canSendRecoveryEmail(): bool
    {
        if ($this->isRecovered() || $this->isExpired()) {
            return false;
        }

        if ($this->recovery_emails_sent >= 3) {
            return false;
        }

        if (! $this->email) {
            return false;
        }

        return true;
    }

    public function getAbandonmentAge(): int
    {
        return $this->abandoned_at->diffInHours(now());
    }

    public function shouldExpire(): bool
    {
        return $this->getAbandonmentAge() > 72;
    }

    // Cart data helpers
    public function getItems(): array
    {
        return $this->cart_data['items'] ?? [];
    }

    public function getItemCount(): int
    {
        return count($this->getItems());
    }

    public function getRecoveryUrl(): string
    {
        return url("/f/{$this->funnel->slug}/recover/{$this->session->uuid}");
    }

    public function getFormattedTotal(): string
    {
        return 'RM '.number_format($this->total_amount, 2);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('recovery_status', 'pending');
    }

    public function scopeRecoverable($query)
    {
        return $query->whereIn('recovery_status', ['pending', 'sent'])
            ->whereNotNull('email')
            ->where('recovery_emails_sent', '<', 3)
            ->where('abandoned_at', '>=', now()->subHours(72));
    }

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }
}
