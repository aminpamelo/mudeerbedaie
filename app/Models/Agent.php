<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    // Type constants
    public const TYPE_AGENT = 'agent';

    public const TYPE_COMPANY = 'company';

    public const TYPE_BOOKSTORE = 'bookstore';

    // Pricing tier constants
    public const PRICING_TIER_STANDARD = 'standard';

    public const PRICING_TIER_PREMIUM = 'premium';

    public const PRICING_TIER_VIP = 'vip';

    // Tier discount percentages
    public const TIER_DISCOUNTS = [
        self::PRICING_TIER_STANDARD => 10,
        self::PRICING_TIER_PREMIUM => 15,
        self::PRICING_TIER_VIP => 20,
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(ProductOrder::class);
    }

    public function paidOrders(): HasMany
    {
        return $this->orders()->whereHas('payments', function ($query) {
            $query->where('status', 'completed');
        });
    }

    public function pendingOrders(): HasMany
    {
        return $this->orders()->where('status', 'pending');
    }

    public function completedOrders(): HasMany
    {
        return $this->orders()->where('status', 'delivered');
    }

    public function customPricing(): HasMany
    {
        return $this->hasMany(AgentPricing::class);
    }

    protected $fillable = [
        'agent_code',
        'name',
        'type',
        'pricing_tier',
        'commission_rate',
        'credit_limit',
        'consignment_enabled',
        'company_name',
        'registration_number',
        'contact_person',
        'email',
        'phone',
        'address',
        'payment_terms',
        'bank_details',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'bank_details' => 'array',
            'is_active' => 'boolean',
            'consignment_enabled' => 'boolean',
            'commission_rate' => 'decimal:2',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isAgent(): bool
    {
        return $this->type === 'agent';
    }

    public function isCompany(): bool
    {
        return $this->type === 'company';
    }

    public function isBookstore(): bool
    {
        return $this->type === self::TYPE_BOOKSTORE;
    }

    /**
     * Get the tier discount percentage for this agent.
     */
    public function getTierDiscountPercentage(): int
    {
        return self::TIER_DISCOUNTS[$this->pricing_tier] ?? self::TIER_DISCOUNTS[self::PRICING_TIER_STANDARD];
    }

    /**
     * Get the price for a product, considering custom pricing and tier discount.
     *
     * @param  int  $productId
     * @param  int  $quantity
     * @return float|null Returns custom price if available, otherwise null (use tier discount)
     */
    public function getPriceForProduct(int $productId, int $quantity = 1): ?float
    {
        // First check for custom pricing
        $customPrice = $this->customPricing()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($customPrice) {
            return (float) $customPrice->price;
        }

        return null; // Will use tier discount in calling code
    }

    /**
     * Calculate the discounted price for a product using tier discount.
     */
    public function calculateTierPrice(float $originalPrice): float
    {
        $discountPercentage = $this->getTierDiscountPercentage();

        return $originalPrice * (1 - ($discountPercentage / 100));
    }

    public function getFormattedAddressAttribute(): string
    {
        $address = $this->address;
        if (! $address) {
            return '';
        }

        $parts = [];
        if (! empty($address['street'])) {
            $parts[] = $address['street'];
        }
        if (! empty($address['city'])) {
            $parts[] = $address['city'];
        }
        if (! empty($address['state'])) {
            $parts[] = $address['state'];
        }
        if (! empty($address['postal_code'])) {
            $parts[] = $address['postal_code'];
        }
        if (! empty($address['country'])) {
            $parts[] = $address['country'];
        }

        return implode(', ', $parts);
    }

    public function getTotalConsignmentStockAttribute(): int
    {
        return $this->warehouses()
            ->withSum('stockLevels', 'quantity')
            ->get()
            ->sum('stock_levels_sum_quantity') ?? 0;
    }

    public function getTotalConsignmentValueAttribute(): float
    {
        $total = 0;
        foreach ($this->warehouses as $warehouse) {
            foreach ($warehouse->stockLevels as $stockLevel) {
                $total += $stockLevel->quantity * $stockLevel->average_cost;
            }
        }

        return $total;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAgents($query)
    {
        return $query->where('type', 'agent');
    }

    public function scopeCompanies($query)
    {
        return $query->where('type', 'company');
    }

    public function scopeBookstores($query)
    {
        return $query->where('type', self::TYPE_BOOKSTORE);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('agent_code', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%");
        });
    }

    public static function generateAgentCode(): string
    {
        $prefix = 'AGT';
        $lastAgent = self::where('agent_code', 'like', $prefix.'%')
            ->orderBy('agent_code', 'desc')
            ->first();

        if ($lastAgent) {
            $lastNumber = (int) substr($lastAgent->agent_code, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique bookstore code (KB prefix).
     */
    public static function generateBookstoreCode(): string
    {
        $prefix = 'KB';
        $lastBookstore = self::where('agent_code', 'like', $prefix.'%')
            ->orderBy('agent_code', 'desc')
            ->first();

        if ($lastBookstore) {
            $lastNumber = (int) substr($lastBookstore->agent_code, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get total orders count.
     */
    public function getTotalOrdersAttribute(): int
    {
        return $this->orders()->count();
    }

    /**
     * Get total revenue from completed orders.
     */
    public function getTotalRevenueAttribute(): float
    {
        return (float) $this->orders()
            ->where('status', 'delivered')
            ->sum('total_amount');
    }

    /**
     * Get pending orders count.
     */
    public function getPendingOrdersCountAttribute(): int
    {
        return $this->orders()
            ->whereIn('status', ['pending', 'processing'])
            ->count();
    }

    /**
     * Get outstanding balance (sum of unpaid orders).
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->orders()
            ->where('payment_status', '!=', 'paid')
            ->sum('total_amount');
    }

    /**
     * Get available credit (credit_limit - outstanding_balance).
     */
    public function getAvailableCreditAttribute(): float
    {
        return max(0, (float) $this->credit_limit - $this->outstanding_balance);
    }

    /**
     * Check if order amount would exceed credit limit.
     */
    public function wouldExceedCreditLimit(float $orderAmount): bool
    {
        return ($this->outstanding_balance + $orderAmount) > $this->credit_limit;
    }
}
