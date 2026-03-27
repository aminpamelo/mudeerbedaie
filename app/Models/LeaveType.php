<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    /** @use HasFactory<\Database\Factories\LeaveTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_paid',
        'is_attachment_required',
        'is_system',
        'is_active',
        'max_consecutive_days',
        'gender_restriction',
        'color',
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
            'is_paid' => 'boolean',
            'is_attachment_required' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'max_consecutive_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the entitlements for this leave type.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    /**
     * Get the balances for this leave type.
     */
    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get the requests for this leave type.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Scope to filter active leave types.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter system leave types.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }
}
