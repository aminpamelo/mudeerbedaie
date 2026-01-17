<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClassNotificationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_notification_setting_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'disk',
        'embed_in_email',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'embed_in_email' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ClassNotificationSetting::class, 'class_notification_setting_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk($this->disk)->path($this->file_path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->file_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->file_type === 'application/pdf';
    }

    public function isDocument(): bool
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return in_array($this->file_type, $documentTypes);
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->file_type, 'video/');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    public function getFileIconAttribute(): string
    {
        if ($this->isImage()) {
            return 'photo';
        }

        if ($this->isPdf()) {
            return 'document-text';
        }

        if ($this->isVideo()) {
            return 'film';
        }

        return 'document';
    }

    public function scopeImages($query)
    {
        return $query->where('file_type', 'like', 'image/%');
    }

    public function scopeEmbeddable($query)
    {
        return $query->where('embed_in_email', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
