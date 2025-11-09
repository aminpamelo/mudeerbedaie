<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveAnalytics extends Model
{
    /** @use HasFactory<\Database\Factories\LiveAnalyticsFactory> */
    use HasFactory;

    protected $fillable = [
        'live_session_id',
        'viewers_peak',
        'viewers_avg',
        'total_likes',
        'total_comments',
        'total_shares',
        'gifts_value',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'gifts_value' => 'decimal:2',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function getEngagementRateAttribute(): float
    {
        if ($this->viewers_avg === 0) {
            return 0;
        }

        $totalEngagement = $this->total_likes + $this->total_comments + $this->total_shares;

        return round(($totalEngagement / $this->viewers_avg) * 100, 2);
    }

    public function getTotalEngagementAttribute(): int
    {
        return $this->total_likes + $this->total_comments + $this->total_shares;
    }
}
