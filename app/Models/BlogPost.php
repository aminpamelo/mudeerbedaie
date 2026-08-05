<?php

namespace App\Models;

use App\Services\Blog\MarkdownService;
use Carbon\CarbonInterface;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A blog article. Content is authored in Markdown (`content`) and the rendered,
 * sanitised HTML is cached in `content_html` so public reads never re-parse.
 */
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /** Average adult reading speed, used to derive `reading_time`. */
    private const WORDS_PER_MINUTE = 200;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'content_html',
        'featured_image_id',
        'category_id',
        'author_id',
        'locale',
        'status',
        'published_at',
        'reading_time',
        'view_count',
        'is_featured',
        'allow_comments',
        'meta_title',
        'meta_description',
        'focus_keyword',
        'canonical_url',
        'og_image_id',
        'noindex',
        'seo_score',
        'seo_report',
        'seo_checked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'seo_checked_at' => 'datetime',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'noindex' => 'boolean',
            'reading_time' => 'integer',
            'view_count' => 'integer',
            'seo_score' => 'integer',
            'seo_report' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BlogPost $post): void {
            if (blank($post->slug) && filled($post->title)) {
                $post->slug = static::uniqueSlug($post->title, $post->id);
            }

            // Re-render the HTML cache and reading time whenever the Markdown moves.
            if ($post->isDirty('content')) {
                $post->content_html = app(MarkdownService::class)->toHtml((string) $post->content);
                $post->reading_time = static::estimateReadingTime((string) $post->content);
            }

            // Publishing without an explicit date should stamp "now", not leave a
            // published post invisible to the `published` scope.
            if ($post->status === self::STATUS_PUBLISHED && blank($post->published_at)) {
                $post->published_at = now();
            }
        });
    }

    /**
     * Build a slug that does not collide with an existing (or trashed) post.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function estimateReadingTime(string $markdown): int
    {
        $words = str_word_count(strip_tags($markdown));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    // ---------------------------------------------------------------- relations

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    /**
     * Users are soft-deletable, so the author is resolved `withTrashed()` —
     * otherwise a deactivated staff account turns every one of their published
     * articles into a null-relationship crash on render.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag')->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'blog_post_product')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('blog_post_product.sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(BlogComment::class)
            ->where('status', BlogComment::STATUS_APPROVED)
            ->whereNull('parent_id')
            ->latest();
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(BlogSubscriber::class);
    }

    // ------------------------------------------------------------------ scopes

    /** Live articles only: published status and a due publish date. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        return $query->where('locale', $locale ?? app()->getLocale());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content', 'like', $like)
                ->orWhere('focus_keyword', 'like', $like);
        });
    }

    // --------------------------------------------------------------- accessors

    public function getUrlAttribute(): string
    {
        return route('blog.show', $this->slug);
    }

    /** Null-safe: the author account may have been soft-deleted or detached. */
    public function getAuthorNameAttribute(): string
    {
        return $this->author?->name ?? __('blog.author_team', ['store' => config('store.name')]);
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->featuredImage?->url;
    }

    /** The <title> value: explicit meta title, else the headline. */
    public function getSeoTitleAttribute(): string
    {
        return filled($this->meta_title) ? $this->meta_title : (string) $this->title;
    }

    /** The meta description: explicit value, else the excerpt, else trimmed body. */
    public function getSeoDescriptionAttribute(): string
    {
        if (filled($this->meta_description)) {
            return $this->meta_description;
        }

        if (filled($this->excerpt)) {
            return Str::limit(strip_tags($this->excerpt), 155);
        }

        return Str::limit(trim(strip_tags((string) $this->content_html)), 155);
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    public function getPublishedDateAttribute(): ?CarbonInterface
    {
        return $this->published_at;
    }

    /**
     * Status → Flux badge colour, shared by the admin list and editor.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'emerald',
            self::STATUS_SCHEDULED => 'blue',
            self::STATUS_ARCHIVED => 'zinc',
            default => 'amber',
        };
    }

    public function incrementViews(): void
    {
        // Direct increment without touching timestamps, so a read never bumps
        // updated_at or re-triggers the Markdown render hook.
        static::withoutTimestamps(fn () => $this->increment('view_count'));
    }
}
