<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable, global weekly grid of time windows. Applied to a slot override in
 * one pick (copy-on-apply) so a PIC need not rebuild the same set of times by
 * hand. The windows live in the {@see $slots} JSON blueprint — templates are
 * never referenced by assignments.
 */
class LiveSlotTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slots',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
