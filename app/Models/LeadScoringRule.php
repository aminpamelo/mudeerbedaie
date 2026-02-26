<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadScoringRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'event_type',
        'conditions',
        'points',
        'is_active',
        'expires_after_days',
        'max_occurrences',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'points' => 'integer',
            'is_active' => 'boolean',
            'expires_after_days' => 'integer',
            'max_occurrences' => 'integer',
        ];
    }

    public function scoreHistory(): HasMany
    {
        return $this->hasMany(LeadScoreHistory::class, 'rule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function isPositive(): bool
    {
        return $this->points > 0;
    }

    public function isNegative(): bool
    {
        return $this->points < 0;
    }

    public function hasExpiry(): bool
    {
        return $this->expires_after_days !== null;
    }

    public function hasMaxOccurrences(): bool
    {
        return $this->max_occurrences !== null;
    }
}
