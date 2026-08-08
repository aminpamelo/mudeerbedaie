<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = ['class_id', 'author_id', 'title', 'body', 'is_pinned', 'published_at'];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ClassAnnouncementRead::class, 'announcement_id');
    }

    public function scopePublished($query)
    {
        return $query->where('published_at', '<=', now());
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('published_at');
    }

    public function isReadBy(Student $student): bool
    {
        return $this->reads()->where('student_id', $student->id)->exists();
    }

    public function markAsRead(Student $student): ClassAnnouncementRead
    {
        return ClassAnnouncementRead::firstOrCreate(
            ['announcement_id' => $this->id, 'student_id' => $student->id],
            ['read_at' => now()]
        );
    }

    public function getReadCountAttribute(): int
    {
        return $this->reads()->count();
    }
}
