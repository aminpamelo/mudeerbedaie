<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MediaFolder extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFolderFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    protected static function booted(): void
    {
        static::creating(function (MediaFolder $folder): void {
            if (empty($folder->slug)) {
                $folder->slug = static::uniqueSlug((string) $folder->name);
            }

            if (empty($folder->created_by) && auth()->check()) {
                $folder->created_by = auth()->id();
            }
        });
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'folder';
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
