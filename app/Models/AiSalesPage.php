<?php

namespace App\Models;

use Database\Factories\AiSalesPageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AiSalesPage extends Model
{
    /** @use HasFactory<AiSalesPageFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'slug',
        'prompt',
        'target_audience',
        'tone',
        'design_notes',
        'style_preset',
        'model',
        'html',
        'custom_css',
        'custom_js',
        'meta_title',
        'meta_description',
        'og_image_media_id',
        'generation_status',
        'generation_error',
        'status',
        'published_version_id',
        'published_at',
        'funnel_id',
        'funnel_step_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_media_id');
    }

    /** @return HasMany<AiSalesPageVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AiSalesPageVersion::class)->orderByDesc('version');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(AiSalesPageVersion::class, 'published_version_id');
    }

    /** @return BelongsToMany<Media, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'ai_sales_page_media')->withTimestamps();
    }

    /** @return HasMany<AiSalesPageMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiSalesPageMessage::class)->orderBy('id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class, 'funnel_id');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('prompt', 'like', "%{$term}%");
        });
    }

    public function isProcessing(): bool
    {
        return $this->generation_status === 'processing';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function getPublicUrl(): string
    {
        return url(config('ai_sales_pages.public_prefix', 'p').'/'.$this->slug);
    }

    public function nextVersionNumber(): int
    {
        return (int) $this->versions()->max('version') + 1;
    }

    /**
     * Snapshot the current working draft into an immutable version.
     */
    public function snapshotVersion(string $generatedBy = 'human', ?string $label = null, ?int $userId = null): AiSalesPageVersion
    {
        return $this->versions()->create([
            'version' => $this->nextVersionNumber(),
            'label' => $label,
            'html' => $this->html,
            'custom_css' => $this->custom_css,
            'custom_js' => $this->custom_js,
            'generated_by' => $generatedBy,
            'prompt_snapshot' => $this->prompt,
            'model' => $this->model,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (AiSalesPage $page): void {
            if (empty($page->uuid)) {
                $page->uuid = (string) Str::uuid();
            }

            if (empty($page->user_id) && auth()->check()) {
                $page->user_id = auth()->id();
            }

            if (empty($page->slug)) {
                $page->slug = static::uniqueSlug($page->title ?: 'sales-page');
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'sales-page';
        $slug = $base;
        $i = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
