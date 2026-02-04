<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    /** @use HasFactory<\Database\Factories\PosSaleFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'salesperson_id',
        'subtotal',
        'discount_amount',
        'discount_type',
        'total_amount',
        'payment_method',
        'payment_reference',
        'payment_status',
        'notes',
        'sale_date',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sale_date' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isWalkIn(): bool
    {
        return $this->customer_id === null;
    }

    public function getCustomerDisplayNameAttribute(): string
    {
        if ($this->customer) {
            return $this->customer->name;
        }

        return $this->customer_name ?? 'Walk-in Customer';
    }

    /**
     * Generate a unique sale number.
     */
    public static function generateSaleNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "POS-{$date}-";

        $lastSale = static::where('sale_number', 'like', "{$prefix}%")
            ->orderByDesc('sale_number')
            ->first();

        if ($lastSale) {
            $lastNumber = (int) substr($lastSale->sale_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('sale_date', today());
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeBySalesperson($query, int $userId)
    {
        return $query->where('salesperson_id', $userId);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('sale_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhereHas('customer', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }
}
