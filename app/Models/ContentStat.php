<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentStat extends Model
{
    protected $fillable = [
        'content_id',
        'views',
        'likes',
        'comments',
        'shares',
        'source',
        'raw_response',
        'fetched_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'likes' => 'integer',
            'comments' => 'integer',
            'shares' => 'integer',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Get the content this stat belongs to
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * Get the engagement rate as a percentage
     */
    public function getEngagementRateAttribute(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->likes + $this->comments + $this->shares) / $this->views * 100, 2);
    }
}
