<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $appends = ['url'];

    protected $fillable = [
        'folder_id',
        'title',
        'alt_text',
        'original_filename',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'type',
        'file_size',
        'width',
        'height',
        'duration',
        'tags',
        'metadata',
        'uploader_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getUrlAttribute(): string
    {
        $pathParts = explode('/', $this->file_path);
        $filename = array_pop($pathParts);
        $encodedPath = implode('/', $pathParts).'/'.rawurlencode($filename);

        return Storage::disk($this->disk ?? 'public')->url(ltrim($encodedPath, '/'));
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (float) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2).' '.$units[$index];
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        return gmdate($this->duration >= 3600 ? 'H:i:s' : 'i:s', $this->duration);
    }

    public function isImage(): bool
    {
        return $this->type === 'image' || str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return $this->type === 'video' || str_starts_with((string) $this->mime_type, 'video/');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('type', 'image');
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('type', 'video');
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function scopeInFolder(Builder $query, $folderId): Builder
    {
        if ($folderId === 'none') {
            return $query->whereNull('folder_id');
        }

        return $folderId ? $query->where('folder_id', $folderId) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('original_filename', 'like', "%{$term}%")
                ->orWhere('alt_text', 'like', "%{$term}%")
                ->orWhere('tags', 'like', "%{$term}%");
        });
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            if (empty($media->uploader_id) && auth()->check()) {
                $media->uploader_id = auth()->id();
            }
        });

        static::deleting(function (Media $media): void {
            if ($media->file_path) {
                Storage::disk($media->disk ?? 'public')->delete($media->file_path);
            }
        });
    }
}
