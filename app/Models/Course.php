<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
        'stripe_product_id',
        'stripe_sync_status',
        'stripe_last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'stripe_last_synced_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function feeSettings(): HasOne
    {
        return $this->hasOne(CourseFeeSettings::class);
    }

    public function classSettings(): HasOne
    {
        return $this->hasOne(CourseClassSettings::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->whereIn('status', ['enrolled', 'active']);
    }

    public function completedEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->where('status', 'completed');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class);
    }

    public function upcomingClasses(): HasMany
    {
        return $this->hasMany(ClassModel::class)->upcoming();
    }

    public function scheduledClasses(): HasMany
    {
        return $this->hasMany(ClassModel::class)->scheduled();
    }

    public function completedClasses(): HasMany
    {
        return $this->hasMany(ClassModel::class)->completed();
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments')->withPivot(['status', 'enrollment_date', 'completion_date', 'notes']);
    }

    public function activeStudents()
    {
        return $this->belongsToMany(Student::class, 'enrollments')
            ->withPivot(['status', 'enrollment_date', 'completion_date', 'notes'])
            ->wherePivotIn('status', ['enrolled', 'active']);
    }

    public function certificates(): BelongsToMany
    {
        return $this->belongsToMany(Certificate::class, 'certificate_course_assignments')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function getEnrollmentCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    public function getActiveEnrollmentCountAttribute(): int
    {
        return $this->activeEnrollments()->count();
    }

    public function getFormattedFeeAttribute(): string
    {
        if (! $this->feeSettings) {
            return 'RM 0.00';
        }

        return 'RM '.number_format($this->feeSettings->fee_amount, 2);
    }

    // Stripe sync status methods
    public function isSyncedToStripe(): bool
    {
        return ! empty($this->stripe_product_id) && $this->stripe_sync_status === 'synced';
    }

    public function isStripeSyncPending(): bool
    {
        return $this->stripe_sync_status === 'pending';
    }

    public function hasStripeSyncFailed(): bool
    {
        return $this->stripe_sync_status === 'failed';
    }

    public function markStripeSyncAsPending(): void
    {
        $this->update(['stripe_sync_status' => 'pending']);
    }

    public function markStripeSyncAsCompleted(string $stripeProductId): void
    {
        $this->update([
            'stripe_product_id' => $stripeProductId,
            'stripe_sync_status' => 'synced',
            'stripe_last_synced_at' => now(),
        ]);
    }

    public function markStripeSyncAsFailed(): void
    {
        $this->update(['stripe_sync_status' => 'failed']);
    }

    // Enrollment-Class management helper methods

    public function enrollStudentInClass(Student $student, ClassModel $class): ?ClassStudent
    {
        // First check if student has active enrollment in this course
        $enrollment = $this->enrollments()
            ->where('student_id', $student->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->first();

        if (! $enrollment) {
            return null; // No active enrollment
        }

        // Use the enrollment's joinClass method for validation and enrollment
        return $enrollment->joinClass($class);
    }

    public function getStudentEnrollmentForClass(Student $student, ClassModel $class): ?Enrollment
    {
        return $this->enrollments()
            ->where('student_id', $student->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->first();
    }

    public function getEligibleStudentsForClass(ClassModel $class): \Illuminate\Database\Eloquent\Collection
    {
        // Get all students with active enrollment in this course who are not already in the class
        return $this->activeStudents()
            ->whereNotIn('students.id', function ($query) use ($class) {
                $query->select('student_id')
                    ->from('class_students')
                    ->where('class_id', $class->id)
                    ->where('status', 'active');
            })
            ->get();
    }

    public function autoEnrollEligibleStudents(ClassModel $class): array
    {
        $eligibleStudents = $this->getEligibleStudentsForClass($class);
        $enrolled = [];
        $failed = [];

        foreach ($eligibleStudents as $student) {
            $classStudent = $this->enrollStudentInClass($student, $class);

            if ($classStudent) {
                $enrolled[] = $student;
            } else {
                $failed[] = $student;
            }

            // Stop if class reaches capacity
            if (! $class->canAddStudent()) {
                break;
            }
        }

        return [
            'enrolled' => $enrolled,
            'failed' => $failed,
            'total_enrolled' => count($enrolled),
            'total_failed' => count($failed),
        ];
    }

    public function getClassEnrollmentSummary(): array
    {
        $classes = $this->classes()->with('classStudents')->get();
        $totalClasses = $classes->count();
        $totalActiveEnrollments = $this->activeEnrollments()->count();

        $classStats = $classes->map(function ($class) {
            return [
                'class_id' => $class->id,
                'class_title' => $class->title,
                'enrolled_students' => $class->activeStudents()->count(),
                'max_capacity' => $class->max_capacity,
                'utilization_rate' => $class->max_capacity ?
                    round(($class->activeStudents()->count() / $class->max_capacity) * 100, 2) : 0,
            ];
        });

        return [
            'total_classes' => $totalClasses,
            'total_course_enrollments' => $totalActiveEnrollments,
            'classes' => $classStats,
            'average_utilization' => $classStats->avg('utilization_rate'),
        ];
    }
}
