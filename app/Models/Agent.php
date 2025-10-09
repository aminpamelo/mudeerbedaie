<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_code',
        'name',
        'type',
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
}
