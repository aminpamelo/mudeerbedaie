<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short key (e.g. "testimoni-1") pointing at a Media Library image/video
 * that the flow AI sends when the flow instructions say so.
 */
class CekbotMedia extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotMediaFactory> */
    use HasFactory;

    /** What WhatsApp accepts: images JPG/PNG up to 5 MB, videos MP4/3GP up to 16 MB. */
    public const IMAGE_MIMES = ['image/jpeg', 'image/png'];

    public const VIDEO_MIMES = ['video/mp4', 'video/3gpp'];

    public const IMAGE_MAX_BYTES = 5 * 1024 * 1024;

    public const VIDEO_MAX_BYTES = 16 * 1024 * 1024;

    protected $table = 'cekbot_media';

    protected $fillable = [
        'key',
        'media_id',
        'title',
        'description',
        'created_by',
    ];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function url(): string
    {
        return (string) $this->media?->url;
    }

    public function isVideo(): bool
    {
        return (bool) $this->media?->isVideo();
    }

    public function mime(): ?string
    {
        return $this->media?->mime_type;
    }

    /**
     * Whether the linked library file is still something WhatsApp can deliver.
     */
    public function isSendable(): bool
    {
        $media = $this->media;

        if (! $media) {
            return false;
        }

        return $media->isVideo()
            ? in_array($media->mime_type, self::VIDEO_MIMES, true) && $media->file_size <= self::VIDEO_MAX_BYTES
            : $media->type === 'image' && in_array($media->mime_type, self::IMAGE_MIMES, true) && $media->file_size <= self::IMAGE_MAX_BYTES;
    }

    /**
     * Narrow a Media Library query to items WhatsApp can actually deliver.
     *
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public static function constrainToSendable(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where(fn (Builder $q) => $q->where('type', 'image')->whereIn('mime_type', self::IMAGE_MIMES)->where('file_size', '<=', self::IMAGE_MAX_BYTES))
                ->orWhere(fn (Builder $q) => $q->where('type', 'video')->whereIn('mime_type', self::VIDEO_MIMES)->where('file_size', '<=', self::VIDEO_MAX_BYTES));
        });
    }
}
