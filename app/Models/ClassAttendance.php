<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAttendance extends Model
{
    use HasFactory;

    protected $table = 'class_attendance';

    protected $fillable = [
        'session_id',
        'student_id',
        'status',
        'checked_in_at',
        'checked_out_at',
        'notes',
        'teacher_remarks',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function class(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->session?->class,
        );
    }

    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function isAbsent(): bool
    {
        return $this->status === 'absent';
    }

    public function isLate(): bool
    {
        return $this->status === 'late';
    }

    public function isExcused(): bool
    {
        return $this->status === 'excused';
    }

    public function markPresent(): void
    {
        $this->update([
            'status' => 'present',
            'checked_in_at' => now(),
        ]);
    }

    public function markAbsent(): void
    {
        $this->update([
            'status' => 'absent',
            'checked_in_at' => null,
            'checked_out_at' => null,
        ]);
    }

    public function markLate(): void
    {
        $this->update([
            'status' => 'late',
            'checked_in_at' => now(),
        ]);
    }

    public function markExcused(): void
    {
        $this->update([
            'status' => 'excused',
            'checked_in_at' => null,
            'checked_out_at' => null,
        ]);
    }

    public function checkOut(): void
    {
        $this->update([
            'checked_out_at' => now(),
        ]);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'present' => 'badge-green',
            'late' => 'badge-yellow',
            'excused' => 'badge-blue',
            'absent' => 'badge-red',
            default => 'badge-gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'present' => 'Present',
            'late' => 'Late',
            'excused' => 'Excused',
            'absent' => 'Absent',
            default => ucfirst($this->status),
        };
    }

    public function getFormattedCheckedInTimeAttribute(): ?string
    {
        return $this->checked_in_at?->format('g:i A');
    }

    public function getFormattedCheckedOutTimeAttribute(): ?string
    {
        return $this->checked_out_at?->format('g:i A');
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if ($this->checked_in_at && $this->checked_out_at) {
            return $this->checked_in_at->diffInMinutes($this->checked_out_at);
        }

        return null;
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->whereHas('session', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });
    }
}
