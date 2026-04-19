<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LiveSessionAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\LiveSessionAttachmentFactory> */
    use HasFactory;

    /**
     * Marks the attachment as the TikTok Shop backend screenshot a host
     * uploads to substantiate their self-reported GMV for a session.
     */
    public const TYPE_TIKTOK_SHOP_SCREENSHOT = 'tiktok_shop_screenshot';

    /**
     * Known attachment types. A NULL `attachment_type` is a generic
     * attachment (e.g. proof-of-live footage) and remains the default.
     *
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_TIKTOK_SHOP_SCREENSHOT,
    ];

    protected $fillable = [
        'live_session_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'attachment_type',
        'description',
    ];

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->file_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->file_type, 'video/');
    }

    public function isPdf(): bool
    {
        return $this->file_type === 'application/pdf';
    }

    public function isTikTokShopScreenshot(): bool
    {
        return $this->attachment_type === self::TYPE_TIKTOK_SHOP_SCREENSHOT;
    }
}
