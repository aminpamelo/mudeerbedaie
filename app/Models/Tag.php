<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'type',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_tags')
            ->withPivot(['applied_by', 'source', 'workflow_id', 'created_at'])
            ->withTimestamps();
    }

    public function studentTags(): HasMany
    {
        return $this->hasMany(StudentTag::class);
    }

    public function getStudentCountAttribute(): int
    {
        return $this->students()->count();
    }

    public function isManual(): bool
    {
        return $this->type === 'manual';
    }

    public function isAuto(): bool
    {
        return $this->type === 'auto';
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }
}
