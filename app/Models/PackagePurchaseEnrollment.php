<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePurchaseEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_purchase_id',
        'enrollment_id',
        'course_id',
        'student_id',
        'enrollment_status',
        'enrolled_at',
        'enrollment_notes',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
        ];
    }

    // Relationships
    public function packagePurchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // Helper methods
    public function isCreated(): bool
    {
        return $this->enrollment_status === 'created';
    }

    public function isFailed(): bool
    {
        return $this->enrollment_status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->enrollment_status === 'cancelled';
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'enrollment_status' => 'failed',
            'enrollment_notes' => $reason,
        ]);
    }

    public function markAsCancelled(string $reason): void
    {
        $this->update([
            'enrollment_status' => 'cancelled',
            'enrollment_notes' => $reason,
        ]);
    }

    // Scopes
    public function scopeCreated($query)
    {
        return $query->where('enrollment_status', 'created');
    }

    public function scopeFailed($query)
    {
        return $query->where('enrollment_status', 'failed');
    }

    public function scopeForPackagePurchase($query, $purchaseId)
    {
        return $query->where('package_purchase_id', $purchaseId);
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}
