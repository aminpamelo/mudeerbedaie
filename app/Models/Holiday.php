<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Holiday extends Model
{
    /** @use HasFactory<\Database\Factories\HolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'type',
        'states',
        'year',
        'is_recurring',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'states' => 'array',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Scope to filter holidays for a specific year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter national holidays.
     */
    public function scopeNational(Builder $query): Builder
    {
        return $query->where('type', 'national');
    }

    /**
     * Scope to filter holidays for a specific state.
     */
    public function scopeForState(Builder $query, string $state): Builder
    {
        return $query->whereJsonContains('states', $state);
    }

    /**
     * Scope to filter holidays for a specific date.
     */
    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('date', $date);
    }
}
