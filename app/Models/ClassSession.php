<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected static function boot()
    {
        parent::boot();

        // When a session is created, update class status to active if it was draft
        static::created(function ($session) {
            $class = $session->class;
            if ($class && $class->isDraft()) {
                $class->update(['status' => 'active']);
            }
        });

        // When a session status is updated, check if class should be completed
        static::updated(function ($session) {
            if ($session->isDirty('status')) {
                $session->updateClassStatusIfNeeded();
            }
        });
    }

    protected $fillable = [
        'class_id',
        'session_date',
        'session_time',
        'duration_minutes',
        'status',
        'teacher_notes',
        'completed_at',
        'started_at',
        'allowance_amount',
        'verified_at',
        'verified_by',
        'verifier_role',
        'payout_status',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'session_time' => 'datetime:H:i',
            'completed_at' => 'datetime',
            'started_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class, 'session_id');
    }

    public function presentAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class, 'session_id')->where('status', 'present');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOngoing(): bool
    {
        return $this->status === 'ongoing';
    }

    public function isNoShow(): bool
    {
        return $this->status === 'no_show';
    }

    public function isRescheduled(): bool
    {
        return $this->status === 'rescheduled';
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function canBeVerified(): bool
    {
        return $this->isCompleted() && ! $this->isVerified();
    }

    public function verify(User $verifier): void
    {
        if (! $this->canBeVerified()) {
            throw new \Exception('Session cannot be verified. Must be completed and not already verified.');
        }

        $this->update([
            'verified_at' => now(),
            'verified_by' => $verifier->id,
            'verifier_role' => $verifier->role,
        ]);
    }

    public function unverify(): void
    {
        if (! $this->isVerified()) {
            throw new \Exception('Session is not verified.');
        }

        $this->update([
            'verified_at' => null,
            'verified_by' => null,
            'verifier_role' => null,
        ]);
    }

    public function markCompleted(?string $notes = null): void
    {
        $allowanceAmount = $this->calculateTeacherAllowance();

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'teacher_notes' => $notes ?: $this->teacher_notes,
            'allowance_amount' => $allowanceAmount,
        ]);
    }

    public function markAsOngoing(): void
    {
        $this->update([
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // Auto-create attendance records for all enrolled students with "present" status
        $this->createAttendanceRecords();
    }

    public function markAsNoShow(?string $notes = null): void
    {
        $this->update([
            'status' => 'no_show',
            'teacher_notes' => $notes ?: $this->teacher_notes,
        ]);
    }

    public function reschedule(\DateTime $newDate, \DateTime $newTime, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rescheduled',
            'session_date' => $newDate,
            'session_time' => $newTime,
            'teacher_notes' => $reason ?: $this->teacher_notes,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function createAttendanceRecords(): void
    {
        // Get all active students for this class
        $activeStudents = $this->class->activeStudents;

        foreach ($activeStudents as $classStudent) {
            // Check if attendance record already exists for this student and session
            $existingAttendance = $this->attendances()
                ->where('student_id', $classStudent->student_id)
                ->first();

            if (! $existingAttendance) {
                // Create new attendance record with "present" status
                $this->attendances()->create([
                    'student_id' => $classStudent->student_id,
                    'status' => 'present',
                    'checked_in_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function updateStudentAttendance(int $studentId, string $status): bool
    {
        $attendance = $this->attendances()
            ->where('student_id', $studentId)
            ->first();

        if (! $attendance) {
            return false;
        }

        $attendance->update([
            'status' => $status,
            'checked_in_at' => in_array($status, ['present', 'late']) ? now() : null,
        ]);

        return true;
    }

    public function getPresentCountAttribute(): int
    {
        return $this->presentAttendances()->count();
    }

    public function getTotalAttendanceCountAttribute(): int
    {
        return $this->attendances()->count();
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->session_date->format('M d, Y').' at '.$this->session_time->format('g:i A');
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

    public function getElapsedTimeInMinutes(): int
    {
        if (! $this->started_at || ! $this->isOngoing()) {
            return 0;
        }

        return now()->diffInMinutes($this->started_at);
    }

    public function getFormattedElapsedTimeAttribute(): string
    {
        if (! $this->started_at || ! $this->isOngoing()) {
            return '';
        }

        // Ensure we get a positive difference (absolute value)
        $diffInSeconds = abs(now()->diffInSeconds($this->started_at, false));

        // If the difference is very small (less than 1 second), show 0:00
        if ($diffInSeconds < 1) {
            return '0:00';
        }

        $hours = intval(floor($diffInSeconds / 3600));
        $minutes = intval(floor(($diffInSeconds % 3600) / 60));
        $seconds = intval($diffInSeconds % 60);

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%d:%02d', $minutes, $seconds);
        }
    }

    public function getElapsedTimeInSeconds(): int
    {
        if (! $this->started_at || ! $this->isOngoing()) {
            return 0;
        }

        return now()->diffInSeconds($this->started_at);
    }

    /**
     * Get the actual duration in minutes for completed sessions
     */
    public function getActualDurationInMinutes(): ?int
    {
        if (! $this->started_at || ! $this->completed_at || ! $this->isCompleted()) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Get the actual duration formatted as string (e.g., "1h 30m", "45m")
     */
    public function getFormattedActualDurationAttribute(): ?string
    {
        $actualMinutes = $this->getActualDurationInMinutes();

        if ($actualMinutes === null) {
            return null;
        }

        $hours = floor($actualMinutes / 60);
        $minutes = $actualMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'m';
        } elseif ($hours > 0) {
            return $hours.'h';
        } else {
            return $minutes.'m';
        }
    }

    /**
     * Get the duration variance (actual - estimated) in minutes
     */
    public function getDurationVarianceInMinutes(): ?int
    {
        $actualMinutes = $this->getActualDurationInMinutes();

        if ($actualMinutes === null) {
            return null;
        }

        return $actualMinutes - $this->duration_minutes;
    }

    /**
     * Check if the session met the duration KPI (within acceptable variance)
     *
     * @param  int  $toleranceMinutes  Acceptable variance in minutes (default: 10)
     */
    public function meetsKpi(int $toleranceMinutes = 10): ?bool
    {
        $variance = $this->getDurationVarianceInMinutes();

        if ($variance === null) {
            return null; // Cannot determine for incomplete sessions
        }

        return abs($variance) <= $toleranceMinutes;
    }

    /**
     * Get KPI status as string
     */
    public function getKpiStatusAttribute(): string
    {
        $meetsKpi = $this->meetsKpi();

        if ($meetsKpi === null) {
            return 'pending';
        }

        return $meetsKpi ? 'met' : 'missed';
    }

    /**
     * Get KPI badge class for styling
     */
    public function getKpiBadgeClassAttribute(): string
    {
        return match ($this->kpi_status) {
            'met' => 'badge-green',
            'missed' => 'badge-red',
            'pending' => 'badge-gray',
            default => 'badge-gray',
        };
    }

    /**
     * Get duration comparison text
     */
    public function getDurationComparisonAttribute(): string
    {
        $variance = $this->getDurationVarianceInMinutes();

        if ($variance === null) {
            return '—';
        }

        if ($variance > 0) {
            return '+'.$variance.'m longer';
        } elseif ($variance < 0) {
            return abs($variance).'m shorter';
        } else {
            return 'Exact match';
        }
    }

    public function calculateTeacherAllowance(): float
    {
        return match ($this->class->rate_type) {
            'per_class' => $this->class->teacher_rate,
            'per_student' => $this->class->teacher_rate * $this->present_count,
            'per_session' => $this->calculateSessionAllowance(),
            default => 0,
        };
    }

    public function getTeacherAllowanceAmount(): float
    {
        if ($this->isCompleted() && $this->allowance_amount !== null) {
            return (float) $this->allowance_amount;
        }

        return $this->calculateTeacherAllowance();
    }

    private function calculateSessionAllowance(): float
    {
        $courseSettings = $this->class->course->classSettings;
        if (! $courseSettings) {
            return 0;
        }

        $sessionFee = match ($courseSettings->billing_type) {
            'per_session' => $courseSettings->price_per_session ?? 0,
            'per_month' => ($courseSettings->price_per_month ?? 0) / ($courseSettings->sessions_per_month ?? 1),
            'per_minute' => ($courseSettings->price_per_minute ?? 0) * $this->duration_minutes,
            default => 0,
        };

        return match ($this->class->commission_type) {
            'percentage' => $sessionFee * ($this->class->commission_value / 100),
            'fixed' => $this->class->commission_value,
            default => 0,
        };
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeNoShow($query)
    {
        return $query->where('status', 'no_show');
    }

    public function scopeRescheduled($query)
    {
        return $query->where('status', 'rescheduled');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('session_date', '>', now()->toDateString())
            ->where('status', 'scheduled');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('session_date', now()->toDateString());
    }

    public function scopePast($query)
    {
        return $query->where('session_date', '<', now()->toDateString());
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    public function scopeVerifiableForPayroll($query)
    {
        return $query->where('status', 'completed')
            ->whereNotNull('allowance_amount')
            ->whereNull('verified_at');
    }

    protected function updateClassStatusIfNeeded(): void
    {
        $class = $this->class;
        if (! $class || ! $class->isActive()) {
            return; // Only auto-update active classes
        }

        $totalSessions = $class->sessions()->count();
        $finishedSessions = $class->sessions()
            ->whereIn('status', ['completed', 'cancelled', 'no_show'])
            ->count();

        // If all sessions are finished, mark class as completed
        if ($totalSessions > 0 && $finishedSessions === $totalSessions) {
            $class->update(['status' => 'completed']);
        }
    }

    public function updateBookmark(string $bookmark): void
    {
        $this->update(['teacher_notes' => $bookmark]);
    }

    public function getBookmarkAttribute(): ?string
    {
        return $this->teacher_notes;
    }

    public function hasBookmark(): bool
    {
        return ! empty($this->teacher_notes);
    }

    public function getFormattedBookmarkAttribute(): string
    {
        if (! $this->hasBookmark()) {
            return 'No bookmark';
        }

        return strlen($this->teacher_notes) > 50
            ? substr($this->teacher_notes, 0, 50).'...'
            : $this->teacher_notes;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'badge-blue',
            'ongoing' => 'badge-green',
            'completed' => 'badge-gray',
            'cancelled' => 'badge-red',
            'no_show' => 'badge-yellow',
            'rescheduled' => 'badge-orange',
            default => 'badge-gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'Scheduled',
            'ongoing' => 'Ongoing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            'rescheduled' => 'Rescheduled',
            default => ucfirst($this->status),
        };
    }

    // Payslip relationships
    public function payslipSessions(): HasMany
    {
        return $this->hasMany(PayslipSession::class, 'session_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'payslip_id');
    }

    public function payslips()
    {
        return $this->belongsToMany(Payslip::class, 'payslip_sessions', 'session_id', 'payslip_id')
            ->withPivot(['amount', 'included_at'])
            ->withTimestamps();
    }

    // Payout status methods
    public function isUnpaid(): bool
    {
        return $this->payout_status === 'unpaid';
    }

    public function isIncludedInPayslip(): bool
    {
        return $this->payout_status === 'included_in_payslip';
    }

    public function isPaid(): bool
    {
        return $this->payout_status === 'paid';
    }

    public function canBeIncludedInPayslip(): bool
    {
        return $this->isCompleted()
               && $this->isVerified()
               && $this->isUnpaid()
               && $this->allowance_amount !== null;
    }

    public function getPayoutStatusBadgeClassAttribute(): string
    {
        return match ($this->payout_status) {
            'unpaid' => 'badge-red',
            'included_in_payslip' => 'badge-yellow',
            'paid' => 'badge-green',
            default => 'badge-gray',
        };
    }

    public function getPayoutStatusLabelAttribute(): string
    {
        return match ($this->payout_status) {
            'unpaid' => 'Unpaid',
            'included_in_payslip' => 'In Payslip',
            'paid' => 'Paid',
            default => ucfirst(str_replace('_', ' ', $this->payout_status)),
        };
    }

    // Enhanced scopes for payslip functionality
    public function scopeEligibleForPayslip($query)
    {
        return $query->where('status', 'completed')
            ->whereNotNull('verified_at')
            ->whereNotNull('allowance_amount')
            ->where('payout_status', 'unpaid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payout_status', 'unpaid');
    }

    public function scopeIncludedInPayslip($query)
    {
        return $query->where('payout_status', 'included_in_payslip');
    }

    public function scopePaidOut($query)
    {
        return $query->where('payout_status', 'paid');
    }
}
