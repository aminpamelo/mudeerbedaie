<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostMentoringLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'color', 'position', 'is_top', 'description',
        'min_sessions', 'min_hours', 'min_gmv_myr', 'min_attendance_pct', 'monthly_sales_target', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_top' => 'boolean',
            'is_active' => 'boolean',
            'min_sessions' => 'integer',
            'min_hours' => 'decimal:2',
            'min_gmv_myr' => 'decimal:2',
            'min_attendance_pct' => 'integer',
            'monthly_sales_target' => 'integer',
        ];
    }

    public function mentees(): HasMany
    {
        return $this->hasMany(LiveHostMentee::class, 'level_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }
}
