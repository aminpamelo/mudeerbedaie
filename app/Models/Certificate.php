<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'size',
        'orientation',
        'width',
        'height',
        'background_image',
        'background_color',
        'elements',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'elements' => 'array',
        ];
    }

    // Relationships

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'certificate_course_assignments')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'certificate_course_assignments', 'certificate_id', 'class_id')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CertificateCourseAssignment::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(CertificateIssue::class);
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(CertificateIssue::class)->where('status', 'issued');
    }

    // Status methods

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    // Size and dimension methods

    public function getDimensionsInPixels(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    public function getFormattedSizeAttribute(): string
    {
        $sizeName = strtoupper($this->size);
        $orientation = ucfirst($this->orientation);

        return "{$sizeName} - {$orientation} ({$this->width}x{$this->height}px)";
    }

    // Element management

    public function getElementsArray(): array
    {
        return $this->elements ?? [];
    }

    public function hasElements(): bool
    {
        return ! empty($this->elements);
    }

    public function updateElements(array $elements): void
    {
        $this->update(['elements' => $elements]);
    }

    public function addElement(array $element): void
    {
        $elements = $this->getElementsArray();
        $elements[] = $element;
        $this->updateElements($elements);
    }

    public function removeElement(string $elementId): void
    {
        $elements = collect($this->getElementsArray())
            ->reject(fn ($el) => $el['id'] === $elementId)
            ->values()
            ->all();

        $this->updateElements($elements);
    }

    // Assignment methods

    public function canBeAssignedTo($courseOrClass): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return true;
    }

    public function assignToCourse(Course $course, bool $isDefault = false): void
    {
        $this->courses()->attach($course->id, ['is_default' => $isDefault]);
    }

    public function assignToClass(ClassModel $class, bool $isDefault = false): void
    {
        $this->classes()->attach($class->id, ['is_default' => $isDefault]);
    }

    public function isAssignedToCourse(Course $course): bool
    {
        return $this->courses()->where('course_id', $course->id)->exists();
    }

    public function isAssignedToClass(ClassModel $class): bool
    {
        return $this->classes()->where('class_id', $class->id)->exists();
    }

    // Statistics

    public function getTotalIssuesCountAttribute(): int
    {
        return $this->issues()->count();
    }

    public function getActiveIssuesCountAttribute(): int
    {
        return $this->issuedCertificates()->count();
    }

    public function getRevokedIssuesCountAttribute(): int
    {
        return $this->issues()->where('status', 'revoked')->count();
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->whereHas('courses', fn ($q) => $q->where('course_id', $courseId));
    }

    public function scopeForClass($query, $classId)
    {
        return $query->whereHas('classes', fn ($q) => $q->where('class_id', $classId));
    }

    // Helper methods for preview

    public function generatePreview(array $sampleData = []): array
    {
        $defaultData = [
            'student_name' => 'John Doe',
            'student_id' => 'STU20250001',
            'course_name' => 'Sample Course',
            'class_name' => 'Sample Class',
            'certificate_number' => 'CERT-2025-0001',
            'issue_date' => now()->format('F j, Y'),
            'completion_date' => now()->format('F j, Y'),
            'teacher_name' => 'Jane Smith',
            'current_date' => now()->format('F j, Y'),
            'current_year' => now()->year,
        ];

        return array_merge($defaultData, $sampleData);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'badge-gray',
            'active' => 'badge-green',
            'archived' => 'badge-yellow',
            default => 'badge-gray',
        };
    }
}
