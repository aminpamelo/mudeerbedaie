<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ClassResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'session_id',
        'uploaded_by',
        'title',
        'type',
        'file_path',
        'url',
        'content',
        'sort_order',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ClassResourceView::class, 'class_resource_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function isAvailable(): bool
    {
        return $this->is_published
            && (blank($this->published_at) || $this->published_at->isPast());
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getAccessibleUrlAttribute(): ?string
    {
        return $this->file_url ?? $this->url;
    }

    public function recordView(Student $student): ClassResourceView
    {
        return ClassResourceView::updateOrCreate(
            [
                'class_resource_id' => $this->id,
                'student_id' => $student->id,
            ],
            [
                'last_viewed_at' => now(),
                'first_viewed_at' => now(),
            ]
        );
    }

    public function getViewCountAttribute(): int
    {
        return $this->views()->count();
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'recording' => 'video-camera',
            'pdf' => 'document-text',
            'audio' => 'musical-note',
            'image' => 'photo',
            'link' => 'link',
            'note' => 'pencil-square',
            default => 'document',
        };
    }
}
