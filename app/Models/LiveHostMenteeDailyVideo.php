<?php

namespace App\Models;

use Database\Factories\LiveHostMenteeDailyVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostMenteeDailyVideo extends Model
{
    /** @use HasFactory<LiveHostMenteeDailyVideoFactory> */
    use HasFactory;

    /**
     * The content category a host assigns to a video, keyed by its stored slug.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'tarik_live' => 'Tarik live',
        'engagement' => 'Engagement',
        'tunjuk_buku' => 'Tunjuk buku',
        'lakonan' => 'Lakonan',
        'podcast' => 'Podcast',
    ];

    protected $table = 'live_host_mentee_daily_videos';

    protected $fillable = [
        'mentee_id', 'video_date', 'title', 'category', 'link', 'logged_by',
    ];

    /** Human label for the stored category slug (null when unset/unknown). */
    public function categoryLabel(): ?string
    {
        return self::CATEGORIES[$this->category] ?? null;
    }

    protected function casts(): array
    {
        return [
            'video_date' => 'date',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function loggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    /**
     * The two-way feedback thread on this video (staff + host).
     *
     * @return HasMany<LiveHostMenteeVideoComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(LiveHostMenteeVideoComment::class, 'video_id');
    }
}
