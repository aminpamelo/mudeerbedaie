<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ClassStudent extends Model
{
    protected $fillable = [
        'class_id',
        'student_id',
        'enrolled_at',
        'left_at',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    // Note: Boot events commented out to prevent issues with existing data
    // Add these back after data migration/cleanup if needed
    /*
    protected static function boot()
    {
        parent::boot();

        // Validate enrollment before creating ClassStudent record
        static::creating(function (ClassStudent $classStudent) {
            $classStudent->validateEnrollment();
        });

        // Validate enrollment before updating ClassStudent record (if status changes to active)
        static::updating(function (ClassStudent $classStudent) {
            if ($classStudent->isDirty('status') && $classStudent->status === 'active') {
                $classStudent->validateEnrollment();
            }
        });
    }
    */

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'student_id', 'student_id')
            ->whereColumn('enrollments.course_id', 'classes.course_id')
            ->whereHas('class', function ($query) {
                $query->whereColumn('classes.course_id', 'enrollments.course_id');
            });
    }

    // Get the active enrollment for this student in the class's course
    public function getActiveEnrollment(): ?Enrollment
    {
        if (! $this->class) {
            return null;
        }

        return Enrollment::where('student_id', $this->student_id)
            ->where('course_id', $this->class->course_id)
            ->whereIn('status', ['enrolled', 'active'])
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasLeft(): bool
    {
        return in_array($this->status, ['transferred', 'quit', 'completed']);
    }

    public function markAsTransferred(?string $reason = null): void
    {
        $this->update([
            'status' => 'transferred',
            'left_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function markAsQuit(?string $reason = null): void
    {
        $this->update([
            'status' => 'quit',
            'left_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'left_at' => now(),
        ]);
    }

    // Validation methods
    public function validateEnrollment(): void
    {
        $enrollment = $this->getActiveEnrollment();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'Student must have an active enrollment in the course before joining this class.',
            ]);
        }

        if (! $enrollment->isActive()) {
            throw ValidationException::withMessages([
                'enrollment' => 'Student enrollment is not active. Current status: '.$enrollment->status,
            ]);
        }
    }

    public function hasValidEnrollment(): bool
    {
        try {
            $this->validateEnrollment();

            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    public function getEnrollmentValidationMessage(): ?string
    {
        try {
            $this->validateEnrollment();

            return null;
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeActiveOnDate($query, $date)
    {
        return $query->where('status', 'active')
            ->where('enrolled_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('left_at')
                    ->orWhere('left_at', '>', $date);
            });
    }
}
