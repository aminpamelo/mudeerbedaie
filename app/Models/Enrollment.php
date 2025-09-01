<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'enrolled_by',
        'status',
        'enrollment_date',
        'start_date',
        'end_date',
        'completion_date',
        'enrollment_fee',
        'notes',
        'progress_data',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_cancel_at',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'completion_date' => 'date',
            'enrollment_fee' => 'decimal:2',
            'progress_data' => 'json',
            'subscription_cancel_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($enrollment) {
            if (empty($enrollment->enrollment_date)) {
                $enrollment->enrollment_date = Carbon::today();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function paidOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('status', Order::STATUS_PAID);
    }

    public function pendingOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('status', Order::STATUS_PENDING);
    }

    public function failedOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('status', Order::STATUS_FAILED);
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

    public function isActive(): bool
    {
        return in_array($this->status, ['enrolled', 'active']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDropped(): bool
    {
        return $this->status === 'dropped';
    }

    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => 'completed',
            'completion_date' => Carbon::today(),
        ]);
    }

    public function markAsDropped(): bool
    {
        return $this->update([
            'status' => 'dropped',
        ]);
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInDays($this->end_date);
        }

        if ($this->start_date && $this->completion_date) {
            return $this->start_date->diffInDays($this->completion_date);
        }

        return null;
    }

    public function getFormattedEnrollmentFeeAttribute(): string
    {
        if (! $this->enrollment_fee) {
            return 'RM 0.00';
        }

        return 'RM '.number_format($this->enrollment_fee, 2);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'enrolled' => 'badge-blue',
            'active' => 'badge-green',
            'completed' => 'badge-emerald',
            'dropped' => 'badge-red',
            'suspended' => 'badge-yellow',
            'pending' => 'badge-gray',
            default => 'badge-gray',
        };
    }

    // Subscription-related utility methods
    public function hasActiveSubscription(): bool
    {
        return ! empty($this->stripe_subscription_id) &&
               in_array($this->subscription_status, ['active', 'trialing']);
    }

    public function isSubscriptionActive(): bool
    {
        return $this->subscription_status === 'active';
    }

    public function isSubscriptionTrialing(): bool
    {
        return $this->subscription_status === 'trialing';
    }

    public function isSubscriptionPastDue(): bool
    {
        return $this->subscription_status === 'past_due';
    }

    public function isSubscriptionCanceled(): bool
    {
        return in_array($this->subscription_status, ['canceled', 'incomplete_expired']);
    }

    public function isPendingCancellation(): bool
    {
        return $this->subscription_cancel_at !== null &&
               $this->subscription_cancel_at->isFuture() &&
               in_array($this->subscription_status, ['active', 'trialing']);
    }

    public function getFormattedCancellationDate(): ?string
    {
        if (! $this->subscription_cancel_at) {
            return null;
        }

        return $this->subscription_cancel_at->format('M d, Y \a\t g:i A');
    }

    public function getSubscriptionStatusLabel(): string
    {
        // Check if subscription is pending cancellation
        if ($this->isPendingCancellation()) {
            return 'Pending Cancellation';
        }

        return match ($this->subscription_status) {
            'active' => 'Active',
            'trialing' => 'Trial',
            'past_due' => 'Past Due',
            'canceled' => 'Canceled',
            'unpaid' => 'Unpaid',
            'incomplete' => 'Incomplete',
            'incomplete_expired' => 'Expired',
            default => ucfirst($this->subscription_status ?? 'none'),
        };
    }

    public function updateSubscriptionStatus(string $status): void
    {
        $this->update(['subscription_status' => $status]);
    }

    public function updateSubscriptionCancellation(?Carbon $cancelAt): void
    {
        $this->update(['subscription_cancel_at' => $cancelAt]);
    }

    public function getSubscriptionEvents()
    {
        if (! $this->stripe_subscription_id) {
            return collect([]);
        }

        return WebhookEvent::where(function ($query) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.data.object.id')) = ?", [$this->stripe_subscription_id])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.data.object.subscription')) = ?", [$this->stripe_subscription_id])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.object.subscription')) = ?", [$this->stripe_subscription_id]);
        })
            ->whereIn('type', [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'invoice.payment_succeeded',
                'invoice.payment_failed',
                'invoice.payment_action_required',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    // Order-related utility methods
    public function getTotalPaidAmountAttribute(): float
    {
        return $this->paidOrders()->sum('amount');
    }

    public function getTotalFailedAmountAttribute(): float
    {
        return $this->failedOrders()->sum('amount');
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['enrolled', 'active']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
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
