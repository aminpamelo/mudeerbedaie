<?php

namespace App\Models;

use Database\Factories\BlogTagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class BlogTag extends Model
{
    /** @use HasFactory<BlogTagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (BlogTag $tag): void {
            if (blank($tag->slug) && filled($tag->name)) {
                $tag->slug = static::uniqueSlug($tag->name, $tag->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Resolve a free-text tag name to an existing tag, or create it.
     * Used by the editor's tag input, which accepts comma-separated names.
     */
    public static function findOrCreateByName(string $name): self
    {
        $slug = Str::slug(trim($name));

        return static::firstOrCreate(['slug' => $slug], ['name' => trim($name)]);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag')->withTimestamps();
    }

    public function getUrlAttribute(): string
    {
        return route('blog.tag', $this->slug);
    }
}
