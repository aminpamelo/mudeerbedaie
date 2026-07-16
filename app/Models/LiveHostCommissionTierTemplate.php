<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable, named commission tier ladder that a PIC can apply to any host on
 * any platform. Applying COPIES the tiers into that host's own
 * LiveHostPlatformCommissionTier schedule — the template is not a live link, so
 * later edits to the template never touch hosts already set up.
 *
 * @property array<int, array{tier_number: int, min_gmv_myr: float, max_gmv_myr: float|null, internal_percent: float, l1_percent: float, l2_percent: float}> $tiers
 */
class LiveHostCommissionTierTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'tiers',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tiers' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
