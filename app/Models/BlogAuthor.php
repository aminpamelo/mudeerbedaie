<?php

namespace App\Models;

use Database\Factories\BlogAuthorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A byline the blog can attribute posts to. Independent of the `users` table,
 * so a pen-name or guest writer needs no login account.
 */
class BlogAuthor extends Model
{
    /** @use HasFactory<BlogAuthorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'avatar_path',
    ];

    protected static function booted(): void
    {
        static::saving(function (BlogAuthor $author): void {
            if (blank($author->slug) && filled($author->name)) {
                $author->slug = static::uniqueSlug($author->name, $author->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'author';
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

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'blog_author_id');
    }

    public function publishedPosts(): HasMany
    {
        return $this->posts()->published();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }
}
