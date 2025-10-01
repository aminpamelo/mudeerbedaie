<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'platform_customer_id',
        'username',
        'name',
        'email',
        'phone',
        'country',
        'state',
        'city',
        'postal_code',
        'addresses',
        'customer_metadata',
        'preferences',
        'total_orders',
        'total_spent',
        'first_order_at',
        'last_order_at',
        'status',
        'is_verified',
        'last_sync_at',
        'sync_metadata',
    ];

    protected function casts(): array
    {
        return [
            'addresses' => 'array',
            'customer_metadata' => 'array',
            'preferences' => 'array',
            'total_orders' => 'integer',
            'total_spent' => 'decimal:2',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'is_verified' => 'boolean',
            'last_sync_at' => 'datetime',
            'sync_metadata' => 'array',
        ];
    }

    // Relationships
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function platformOrders(): HasMany
    {
        return $this->hasMany(ProductOrder::class, 'customer_name', 'name')
            ->where('platform_id', $this->platform_id);
    }

    // Helper methods
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->username ?: 'Unknown Customer';
    }

    public function getFullAddressAttribute(): ?string
    {
        if (! $this->city && ! $this->state && ! $this->country) {
            return null;
        }

        $parts = array_filter([
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    public function updateOrderStatistics(): void
    {
        $orderStats = $this->platformOrders()
            ->selectRaw('COUNT(*) as order_count, SUM(total_amount) as total_amount, MIN(order_date) as first_order, MAX(order_date) as last_order')
            ->first();

        $this->update([
            'total_orders' => $orderStats->order_count ?: 0,
            'total_spent' => $orderStats->total_amount ?: 0,
            'first_order_at' => $orderStats->first_order,
            'last_order_at' => $orderStats->last_order,
        ]);
    }

    public function addOrder(ProductOrder $order): void
    {
        $this->increment('total_orders');
        $this->increment('total_spent', $order->total_amount);

        if (! $this->first_order_at || $order->order_date < $this->first_order_at) {
            $this->update(['first_order_at' => $order->order_date]);
        }

        if (! $this->last_order_at || $order->order_date > $this->last_order_at) {
            $this->update(['last_order_at' => $order->order_date]);
        }
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function hasContactInfo(): bool
    {
        return ! empty($this->email) || ! empty($this->phone);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeByPlatform(Builder $query, int $platformId): Builder
    {
        return $query->where('platform_id', $platformId);
    }

    public function scopeByPlatformAccount(Builder $query, int $platformAccountId): Builder
    {
        return $query->where('platform_account_id', $platformAccountId);
    }

    public function scopeWithOrders(Builder $query): Builder
    {
        return $query->where('total_orders', '>', 0);
    }

    public function scopeTopSpenders(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('total_spent', 'desc')->limit($limit);
    }

    public function scopeRecentCustomers(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('platform_customer_id', 'like', "%{$search}%");
        });
    }

    // Static methods
    public static function findOrCreateFromOrderData(array $orderData, int $platformId, ?int $platformAccountId = null): self
    {
        $customer = static::where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->where(function ($q) use ($orderData) {
                if (! empty($orderData['username'])) {
                    $q->where('username', $orderData['username']);
                } elseif (! empty($orderData['email'])) {
                    $q->where('email', $orderData['email']);
                } elseif (! empty($orderData['phone'])) {
                    $q->where('phone', $orderData['phone']);
                }
            })
            ->first();

        if (! $customer) {
            $customer = static::create([
                'platform_id' => $platformId,
                'platform_account_id' => $platformAccountId,
                'platform_customer_id' => $orderData['platform_customer_id'] ?? null,
                'username' => $orderData['username'] ?? null,
                'name' => $orderData['name'] ?? null,
                'email' => $orderData['email'] ?? null,
                'phone' => $orderData['phone'] ?? null,
                'country' => $orderData['country'] ?? null,
                'state' => $orderData['state'] ?? null,
                'city' => $orderData['city'] ?? null,
                'postal_code' => $orderData['postal_code'] ?? null,
                'addresses' => $orderData['addresses'] ?? null,
                'customer_metadata' => $orderData['metadata'] ?? null,
            ]);
        } else {
            // Update customer info if we have new data
            $updateData = [];
            foreach (['name', 'email', 'phone', 'country', 'state', 'city', 'postal_code'] as $field) {
                if (! empty($orderData[$field]) && empty($customer->$field)) {
                    $updateData[$field] = $orderData[$field];
                }
            }

            if (! empty($updateData)) {
                $customer->update($updateData);
            }
        }

        return $customer;
    }
}
