<?php

namespace App\Models;

use App\Observers\ContentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ContentObserver::class)]
class Content extends Model
{
    /** @use HasFactory<\Database\Factories\ContentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'stage',
        'due_date',
        'priority',
        'tiktok_url',
        'tiktok_post_id',
        'video_url',
        'platform_account_id',
        'is_flagged_for_ads',
        'is_marked_for_ads',
        'marked_by',
        'marked_at',
        'created_by',
        'posted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'marked_at' => 'datetime',
            'is_flagged_for_ads' => 'boolean',
            'is_marked_for_ads' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Content $content): void {
            if ($content->isDirty('tiktok_url') && $content->tiktok_url) {
                $videoId = \App\Services\TikTok\TikTokUrlParser::extractVideoId($content->tiktok_url);
                if ($videoId) {
                    $content->tiktok_post_id = $videoId;
                }
            }
        });
    }

    /**
     * Get the employee who created this content
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Get the employee who marked this content for ads
     */
    public function markedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'marked_by');
    }

    public function platformPosts(): HasMany
    {
        return $this->hasMany(CmsContentPlatformPost::class);
    }

    /**
     * Get the stages for this content
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ContentStage::class);
    }

    /**
     * Get the stats for this content
     */
    public function stats(): HasMany
    {
        return $this->hasMany(ContentStat::class);
    }

    /**
     * Get the latest stats for this content
     */
    public function latestStats(): HasMany
    {
        return $this->hasMany(ContentStat::class)->latest('fetched_at')->limit(1);
    }

    /**
     * Get the ad campaigns for this content
     */
    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    /**
     * Get the TikTok Shop account this content was posted from.
     */
    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    /**
     * Get color based on content priority
     */
    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }

    /**
     * Get numeric order based on content stage
     */
    public function getStageOrderAttribute(): int
    {
        return match ($this->stage) {
            'idea' => 1,
            'shooting' => 2,
            'editing' => 3,
            'posting' => 4,
            'posted' => 5,
            default => 0,
        };
    }
}
