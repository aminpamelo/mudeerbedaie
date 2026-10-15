<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A current-affairs headline captured from a news feed. AI scores how well it
 * suits a religious commentary article (relevance 0–10) and proposes an
 * angle; the team (or the daily auto-writer) turns good ones into drafts.
 */
class BlogIssue extends Model
{
    /** @use HasFactory<\Database\Factories\BlogIssueFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_WRITING = 'writing';

    public const STATUS_WRITTEN = 'written';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISMISSED = 'dismissed';

    /** Minimum AI relevance score for an issue to be suggested / auto-written. */
    public const MIN_RELEVANCE = 6;

    protected $fillable = [
        'title',
        'summary',
        'source',
        'url',
        'url_hash',
        'published_at',
        'relevance',
        'angle',
        'status',
        'error',
        'blog_post_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'relevance' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /**
     * Issues suitable for an article that nobody has written or dismissed yet.
     */
    public function scopeSuggested(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW)
            ->where('relevance', '>=', self::MIN_RELEVANCE);
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', trim($url));
    }
}
