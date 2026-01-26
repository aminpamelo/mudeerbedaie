<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FunnelMedia extends Model
{
    use HasFactory;

    protected $table = 'funnel_media';

    protected $fillable = [
        'funnel_id',
        'user_id',
        'filename',
        'original_filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        // For now, return the same URL. Could implement thumbnail generation later.
        return $this->url;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2).' '.$units[$index];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // Scopes
    public function scopeForFunnel($query, ?int $funnelId)
    {
        if ($funnelId) {
            return $query->where('funnel_id', $funnelId);
        }

        return $query->whereNull('funnel_id');
    }

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Delete file when model is deleted
    protected static function booted(): void
    {
        static::deleting(function (FunnelMedia $media) {
            Storage::disk($media->disk)->delete($media->path);
        });
    }
}
