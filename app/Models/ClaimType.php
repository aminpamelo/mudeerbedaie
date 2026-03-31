<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimType extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'monthly_limit',
        'yearly_limit',
        'requires_receipt',
        'is_mileage_type',
        'is_active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_limit' => 'decimal:2',
            'yearly_limit' => 'decimal:2',
            'requires_receipt' => 'boolean',
            'is_mileage_type' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get claim requests associated with this claim type.
     */
    public function claimRequests(): HasMany
    {
        return $this->hasMany(ClaimRequest::class);
    }

    /**
     * Get vehicle rates associated with this claim type.
     */
    public function vehicleRates(): HasMany
    {
        return $this->hasMany(ClaimTypeVehicleRate::class);
    }

    /**
     * Scope to filter active claim types.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order then name.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
