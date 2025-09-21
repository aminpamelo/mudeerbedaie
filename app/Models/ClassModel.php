<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClassModel extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'course_id',
        'teacher_id',
        'title',
        'description',
        'date_time',
        'duration_minutes',
        'class_type',
        'max_capacity',
        'location',
        'meeting_url',
        'whatsapp_group_link',
        'teacher_rate',
        'rate_type',
        'commission_type',
        'commission_value',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_time' => 'datetime',
            'teacher_rate' => 'decimal:2',
            'commission_value' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function timetable(): HasOne
    {
        return $this->hasOne(ClassTimetable::class, 'class_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'class_id');
    }

    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id');
    }

    public function activeStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id')->active();
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'class_students', 'class_id', 'student_id')
            ->withPivot(['enrolled_at', 'left_at', 'status', 'reason'])
            ->withTimestamps();
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            ClassAttendance::class,
            ClassSession::class,
            'class_id', // Foreign key on sessions table
            'session_id', // Foreign key on attendances table
            'id', // Local key on classes table
            'id' // Local key on sessions table
        );
    }

    public function isIndividual(): bool
    {
        return $this->class_type === 'individual';
    }

    public function isGroup(): bool
    {
        return $this->class_type === 'group';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function getComputedStatusAttribute(): string
    {
        // Return the computed status based on session states
        if ($this->isCancelled() || $this->isCompleted()) {
            return $this->status; // These are final states
        }

        if ($this->isSuspended()) {
            return $this->status; // Manual suspension
        }

        if ($this->isDraft()) {
            // Check if it should be active (has sessions)
            if ($this->sessions()->exists()) {
                return 'active';
            }

            return 'draft';
        }

        // For active classes, analyze session states
        $totalSessions = $this->total_sessions;
        $completedSessions = $this->completed_sessions;

        if ($totalSessions === 0) {
            return 'draft'; // No sessions means still in draft
        }

        if ($completedSessions === $totalSessions) {
            return 'completed'; // All sessions completed
        }

        return 'active'; // Has sessions, some pending/ongoing
    }

    public function suspend(?string $reason = null): void
    {
        if ($this->isActive()) {
            $this->update(['status' => 'suspended']);
            // Optionally store reason in notes field
            if ($reason) {
                $this->update(['notes' => $reason]);
            }
        }
    }

    public function reactivate(): void
    {
        if ($this->isSuspended()) {
            $this->update(['status' => 'active']);
        }
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);

        // Also mark any remaining scheduled sessions as cancelled
        $this->sessions()
            ->where('status', 'scheduled')
            ->update(['status' => 'cancelled']);
    }

    public function getTotalSessionsAttribute(): int
    {
        return $this->sessions()->count();
    }

    public function getCompletedSessionsAttribute(): int
    {
        return $this->sessions()->completed()->count();
    }

    public function getUpcomingSessionsAttribute(): int
    {
        return $this->sessions()->upcoming()->count();
    }

    public function getActiveStudentCountAttribute(): int
    {
        return $this->activeStudents()->count();
    }

    public function getTotalAttendanceCountAttribute(): int
    {
        return $this->attendances()->count();
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->date_time->format('M d, Y \a\t g:i A');
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'m';
        } elseif ($hours > 0) {
            return $hours.'h';
        } else {
            return $minutes.'m';
        }
    }

    public function calculateTotalTeacherAllowance(): float
    {
        $total = 0;

        foreach ($this->sessions()->completed()->get() as $session) {
            $total += $session->getTeacherAllowanceAmount();
        }

        return $total;
    }

    public function addStudent(Student $student): ClassStudent
    {
        return $this->classStudents()->create([
            'student_id' => $student->id,
            'enrolled_at' => now(),
            'status' => 'active',
        ]);
    }

    public function removeStudent(Student $student, ?string $reason = null): void
    {
        $classStudent = $this->classStudents()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if ($classStudent) {
            $classStudent->markAsQuit($reason);
        }
    }

    public function canAddStudent(): bool
    {
        if ($this->isIndividual()) {
            return $this->activeStudents()->count() < 1;
        }

        return $this->max_capacity === null || $this->activeStudents()->count() < $this->max_capacity;
    }

    private function calculateSessionAllowance(): float
    {
        $courseSettings = $this->course->classSettings;
        if (! $courseSettings) {
            return 0;
        }

        $sessionFee = match ($courseSettings->billing_type) {
            'per_session' => $courseSettings->price_per_session ?? 0,
            'per_month' => ($courseSettings->price_per_month ?? 0) / ($courseSettings->sessions_per_month ?? 1),
            'per_minute' => ($courseSettings->price_per_minute ?? 0) * $this->duration_minutes,
            default => 0,
        };

        return match ($this->commission_type) {
            'percentage' => $sessionFee * ($this->commission_value / 100),
            'fixed' => $this->commission_value,
            default => 0,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'badge-gray',
            'active' => 'badge-green',
            'completed' => 'badge-emerald',
            'cancelled' => 'badge-red',
            'suspended' => 'badge-yellow',
            default => 'badge-gray',
        };
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'active')
            ->whereHas('sessions', function ($q) {
                $q->where('session_date', '>', now()->toDateString())
                    ->where('status', 'scheduled');
            });
    }

    public function scopePast($query)
    {
        return $query->where('date_time', '<', now());
    }

    public function createSessionsFromTimetable(): int
    {
        if (! $this->timetable) {
            return 0;
        }

        $sessionsData = $this->timetable->generateSessions();

        if (empty($sessionsData)) {
            return 0;
        }

        // Insert sessions in bulk for better performance
        ClassSession::insert($sessionsData);

        return count($sessionsData);
    }

    public function hasTimetable(): bool
    {
        return $this->timetable !== null;
    }

    // Enrollment verification and management methods

    public function getEligibleEnrollments(): \Illuminate\Database\Eloquent\Collection
    {
        // Get all active enrollments for this class's course that are not already in this class
        return $this->course->activeEnrollments()
            ->whereNotIn('student_id', function ($query) {
                $query->select('student_id')
                    ->from('class_students')
                    ->where('class_id', $this->id)
                    ->where('status', 'active');
            })
            ->with('student')
            ->get();
    }

    public function enrollAllEligibleStudents(): array
    {
        return $this->course->autoEnrollEligibleStudents($this);
    }

    public function getEnrollmentRequiredStudents(): \Illuminate\Database\Eloquent\Collection
    {
        // Students who are in this class but don't have active enrollment
        return $this->students()
            ->whereDoesntHave('enrollments', function ($query) {
                $query->where('course_id', $this->course_id)
                    ->whereIn('status', ['enrolled', 'active']);
            })
            ->get();
    }

    public function validateAllStudentEnrollments(): array
    {
        $validStudents = [];
        $invalidStudents = [];

        foreach ($this->classStudents()->with('student')->get() as $classStudent) {
            if ($classStudent->hasValidEnrollment()) {
                $validStudents[] = $classStudent;
            } else {
                $invalidStudents[] = [
                    'class_student' => $classStudent,
                    'message' => $classStudent->getEnrollmentValidationMessage(),
                ];
            }
        }

        return [
            'valid' => $validStudents,
            'invalid' => $invalidStudents,
            'total_valid' => count($validStudents),
            'total_invalid' => count($invalidStudents),
        ];
    }

    public function getEnrollmentStats(): array
    {
        $totalEnrolledStudents = $this->activeStudents()->count();
        $eligibleEnrollments = $this->getEligibleEnrollments()->count();
        $totalCourseEnrollments = $this->course->activeEnrollments()->count();

        return [
            'enrolled_in_class' => $totalEnrolledStudents,
            'eligible_for_class' => $eligibleEnrollments,
            'total_course_enrollments' => $totalCourseEnrollments,
            'enrollment_rate' => $totalCourseEnrollments > 0 ?
                round(($totalEnrolledStudents / $totalCourseEnrollments) * 100, 2) : 0,
            'capacity_utilization' => $this->max_capacity ?
                round(($totalEnrolledStudents / $this->max_capacity) * 100, 2) : 0,
        ];
    }

    public function addStudentWithEnrollmentCheck(Student $student): array
    {
        // Check if student has active enrollment first
        $enrollment = $this->course->getStudentEnrollmentForClass($student, $this);

        if (! $enrollment) {
            return [
                'success' => false,
                'message' => 'Student does not have an active enrollment in this course.',
                'class_student' => null,
            ];
        }

        // Use enrollment's method for proper validation
        $classStudent = $enrollment->joinClass($this);

        if ($classStudent) {
            return [
                'success' => true,
                'message' => 'Student successfully added to class.',
                'class_student' => $classStudent,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to add student to class. Class may be full or student already enrolled.',
            'class_student' => null,
        ];
    }
}
