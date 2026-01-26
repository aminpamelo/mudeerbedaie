<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    // Validation
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    public function canBeUsed(): bool
    {
        return $this->isValid();
    }

    // Calculation
    public function calculateDiscount(float $amount): float
    {
        if (! $this->isValid()) {
            return 0;
        }

        if ($this->type === 'percentage') {
            return round($amount * ($this->value / 100), 2);
        }

        return min($this->value, $amount);
    }

    public function applyDiscount(float $amount): float
    {
        return max(0, $amount - $this->calculateDiscount($amount));
    }

    // Usage tracking
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    public function getRemainingUses(): ?int
    {
        if (! $this->max_uses) {
            return null;
        }

        return max(0, $this->max_uses - $this->used_count);
    }

    // Display helpers
    public function getFormattedValue(): string
    {
        if ($this->type === 'percentage') {
            return $this->value.'%';
        }

        return 'RM '.number_format($this->value, 2);
    }

    public function isPercentage(): bool
    {
        return $this->type === 'percentage';
    }

    public function isFixed(): bool
    {
        return $this->type === 'fixed';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                    ->orWhereColumn('used_count', '<', 'max_uses');
            });
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
