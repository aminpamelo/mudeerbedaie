<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOrderNote extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'message',
        'is_visible_to_customer',
        'system_action',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_to_customer' => 'boolean',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isCustomerNote(): bool
    {
        return $this->type === 'customer';
    }

    public function isInternalNote(): bool
    {
        return $this->type === 'internal';
    }

    public function isSystemNote(): bool
    {
        return $this->type === 'system';
    }

    public function isPaymentNote(): bool
    {
        return $this->type === 'payment';
    }

    public function isShippingNote(): bool
    {
        return $this->type === 'shipping';
    }

    public function getAuthorName(): string
    {
        if ($this->isSystemNote()) {
            return 'System';
        }

        return $this->user?->name ?? 'Unknown User';
    }

    public function getTypeDisplay(): string
    {
        return match ($this->type) {
            'customer' => 'Customer Note',
            'internal' => 'Internal Note',
            'system' => 'System Note',
            'payment' => 'Payment Note',
            'shipping' => 'Shipping Note',
            default => ucfirst($this->type).' Note'
        };
    }

    public function getFormattedMessage(): string
    {
        // For system notes with actions, we might want to format them differently
        if ($this->isSystemNote() && $this->system_action) {
            return $this->message;
        }

        return $this->message;
    }

    public function shouldShowToCustomer(): bool
    {
        return $this->is_visible_to_customer;
    }

    // Scopes
    public function scopeVisibleToCustomer($query)
    {
        return $query->where('is_visible_to_customer', true);
    }

    public function scopeInternalOnly($query)
    {
        return $query->where('is_visible_to_customer', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSystemNotes($query)
    {
        return $query->where('type', 'system');
    }

    public function scopeCustomerNotes($query)
    {
        return $query->where('type', 'customer');
    }

    public function scopeInternalNotes($query)
    {
        return $query->where('type', 'internal');
    }
}
