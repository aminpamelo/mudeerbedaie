<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'teacher_id',
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
}
