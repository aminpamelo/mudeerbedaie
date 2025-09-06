<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'ic_number',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'nationality',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'status' => 'string',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->student_id)) {
                $student->student_id = self::generateStudentId();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'enrollments')->withPivot(['status', 'enrollment_date', 'completion_date', 'notes']);
    }

    public function activeCourses()
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot(['status', 'enrollment_date', 'completion_date', 'notes'])
            ->wherePivotIn('status', ['enrolled', 'active']);
    }

    public function classAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class);
    }

    public function presentAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class)->where('status', 'present');
    }

    public function absentAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class)->where('status', 'absent');
    }

    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'class_attendance', 'student_id', 'class_id')
            ->withPivot(['status', 'checked_in_at', 'checked_out_at', 'notes', 'teacher_remarks']);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->where('status', 'paid');
    }

    public function pendingInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->whereIn('status', ['draft', 'sent']);
    }

    public function overdueInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->where(function ($query) {
            $query->where('status', 'overdue')
                ->orWhere(function ($q) {
                    $q->where('status', '!=', 'paid')
                        ->where('due_date', '<', Carbon::today());
                });
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullNameAttribute(): string
    {
        return $this->user->name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    public static function generateStudentId(): string
    {
        do {
            $studentId = 'STU'.date('Y').str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('student_id', $studentId)->exists());

        return $studentId;
    }

    // Invoice-related utility methods
    public function getTotalPaidAmountAttribute(): float
    {
        return $this->paidInvoices()->sum('amount');
    }

    public function getTotalOutstandingAmountAttribute(): float
    {
        return $this->pendingInvoices()->sum('amount') + $this->overdueInvoices()->sum('amount');
    }

    public function getInvoiceCountAttribute(): int
    {
        return $this->invoices()->count();
    }

    public function getPaidInvoiceCountAttribute(): int
    {
        return $this->paidInvoices()->count();
    }

    public function getOverdueInvoiceCountAttribute(): int
    {
        return $this->overdueInvoices()->count();
    }

    public function hasOverduePayments(): bool
    {
        return $this->overdueInvoices()->exists();
    }

    public function getFormattedTotalOutstandingAttribute(): string
    {
        return 'RM '.number_format($this->total_outstanding_amount, 2);
    }

    public function getFormattedTotalPaidAttribute(): string
    {
        return 'RM '.number_format($this->total_paid_amount, 2);
    }
}
