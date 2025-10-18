<?php

namespace App\Models;

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
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postcode',
        'country',
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
        return $this->belongsToMany(ClassModel::class, 'class_students', 'student_id', 'class_id')
            ->withPivot(['enrolled_at', 'left_at', 'status', 'reason'])
            ->withTimestamps();
    }

    public function activeClasses()
    {
        return $this->belongsToMany(ClassModel::class, 'class_students', 'student_id', 'class_id')
            ->withPivot(['enrolled_at', 'left_at', 'status', 'reason'])
            ->withTimestamps()
            ->wherePivot('status', 'active');
    }

    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class);
    }

    public function activeClassStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class)->where('status', 'active');
    }

    public function certificateIssues(): HasMany
    {
        return $this->hasMany(\App\Models\CertificateIssue::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ProductOrder::class);
    }

    public function paidOrders(): HasMany
    {
        return $this->hasMany(ProductOrder::class)->whereNotNull('paid_time');
    }

    public function pendingOrders(): HasMany
    {
        return $this->hasMany(ProductOrder::class)->where('status', 'pending');
    }

    public function failedOrders(): HasMany
    {
        return $this->hasMany(ProductOrder::class)->where('status', 'cancelled');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getNameAttribute(): string
    {
        return $this->user->name ?? '';
    }

    public function getEmailAttribute(): ?string
    {
        return $this->user->email ?? null;
    }

    public function getPhoneNumberAttribute(): ?string
    {
        // Return phone from student profile first, fallback to user phone
        return $this->phone ?: ($this->user->phone ?? null);
    }

    public function getFullAddressAttribute(): string
    {
        $addressParts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $addressParts);
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

    // Order-related utility methods
    public function getTotalPaidAmountAttribute(): float
    {
        return $this->paidOrders()->sum('total_amount');
    }

    public function getTotalPendingAmountAttribute(): float
    {
        return $this->pendingOrders()->sum('total_amount');
    }

    public function getOrderCountAttribute(): int
    {
        return $this->orders()->count();
    }

    public function getPaidOrderCountAttribute(): int
    {
        return $this->paidOrders()->count();
    }

    public function getFailedOrderCountAttribute(): int
    {
        return $this->failedOrders()->count();
    }

    public function hasFailedPayments(): bool
    {
        return $this->failedOrders()->exists();
    }

    public function getFormattedTotalPendingAttribute(): string
    {
        return 'RM '.number_format($this->total_pending_amount, 2);
    }

    public function getFormattedTotalPaidAttribute(): string
    {
        return 'RM '.number_format($this->total_paid_amount, 2);
    }
}
