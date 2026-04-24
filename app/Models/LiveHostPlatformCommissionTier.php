<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostPlatformCommissionTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform_id',
        'tier_number',
        'min_gmv_myr',
        'max_gmv_myr',
        'internal_percent',
        'l1_percent',
        'l2_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tier_number' => 'integer',
            'min_gmv_myr' => 'decimal:2',
            'max_gmv_myr' => 'decimal:2',
            'internal_percent' => 'decimal:2',
            'l1_percent' => 'decimal:2',
            'l2_percent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
