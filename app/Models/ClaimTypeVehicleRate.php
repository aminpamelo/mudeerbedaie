<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimTypeVehicleRate extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimTypeVehicleRateFactory> */
    use HasFactory;

    protected $fillable = [
        'claim_type_id',
        'name',
        'rate_per_km',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_km' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function claimType(): BelongsTo
    {
        return $this->belongsTo(ClaimType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
