<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateCourseAssignment extends Model
{
    protected $fillable = [
        'certificate_id',
        'course_id',
        'class_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    // Relationships

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    // Helper methods

    public function isForCourse(): bool
    {
        return $this->course_id !== null;
    }

    public function isForClass(): bool
    {
        return $this->class_id !== null;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function setAsDefault(): void
    {
        // First, remove default flag from other assignments
        if ($this->isForCourse()) {
            static::where('course_id', $this->course_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);
        }

        if ($this->isForClass()) {
            static::where('class_id', $this->class_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);
        }

        // Set this as default
        $this->update(['is_default' => true]);
    }

    // Scopes

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
