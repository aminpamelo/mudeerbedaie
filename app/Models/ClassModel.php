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
}
