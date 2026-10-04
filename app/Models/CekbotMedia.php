<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An image or video in the shared Cekbot media library. The flow AI sends it
 * by its short `key` (e.g. "testimoni-1") when the flow instructions say so.
 */
class CekbotMedia extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotMediaFactory> */
    use HasFactory;

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const DIRECTORY = 'cekbot-media';

    protected $table = 'cekbot_media';

    protected $fillable = [
        'key',
        'title',
        'description',
        'type',
        'path',
        'mime',
        'size',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
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
        return Storage::disk('public')->url($this->path);
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }
}
