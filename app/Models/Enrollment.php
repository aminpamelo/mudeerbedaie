<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory;

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
        'collection_status',
        'collection_paused_at',
        'subscription_cancel_at',
        'billing_cycle_anchor',
        'trial_end_at',
        'subscription_timezone',
        'proration_behavior',
        'next_payment_date',
        'payment_method_type',
        'manual_payment_required',
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
            'collection_paused_at' => 'datetime',
            'subscription_cancel_at' => 'datetime',
            'billing_cycle_anchor' => 'datetime',
            'trial_end_at' => 'datetime',
            'next_payment_date' => 'datetime',
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

    // Collection status utility methods
    public function isCollectionActive(): bool
    {
        return $this->collection_status === 'active';
    }

    public function isCollectionPaused(): bool
    {
        return $this->collection_status === 'paused';
    }

    public function getCollectionStatusLabel(): string
    {
        return match ($this->collection_status) {
            'active' => 'Active',
            'paused' => 'Collection Paused',
            default => ucfirst($this->collection_status ?? 'active'),
        };
    }

    public function getFormattedCollectionPausedDate(): ?string
    {
        if (! $this->collection_paused_at) {
            return null;
        }

        return $this->collection_paused_at->format('M d, Y \a\t g:i A');
    }

    public function updateCollectionStatus(string $status, ?\Carbon\Carbon $pausedAt = null): void
    {
        $this->update([
            'collection_status' => $status,
            'collection_paused_at' => $pausedAt,
        ]);
    }

    public function pauseCollection(): void
    {
        $this->updateCollectionStatus('paused', now());
    }

    public function resumeCollection(): void
    {
        $this->updateCollectionStatus('active', null);
    }

    public function getFullStatusDescription(): string
    {
        $subscriptionLabel = $this->getSubscriptionStatusLabel();
        $collectionLabel = $this->getCollectionStatusLabel();

        if ($this->isCollectionPaused() && $this->isSubscriptionActive()) {
            return 'Active (Collection Paused)';
        }

        return $subscriptionLabel;
    }

    public function updateSubscriptionStatus(string $status): void
    {
        $this->update(['subscription_status' => $status]);
    }

    public function updateSubscriptionCancellation(?Carbon $cancelAt): void
    {
        $this->update(['subscription_cancel_at' => $cancelAt]);
    }

    public function updateNextPaymentDate(?Carbon $nextPaymentDate): void
    {
        $this->update(['next_payment_date' => $nextPaymentDate]);
    }

    public function getNextPaymentDate(): ?Carbon
    {
        if (! $this->hasActiveSubscription()) {
            return null;
        }

        // Use stored next_payment_date if available
        if ($this->next_payment_date) {
            return $this->next_payment_date;
        }

        // Fallback to calculation from orders (for backward compatibility)
        $lastOrder = $this->paidOrders()
            ->orderBy('period_end', 'desc')
            ->first();

        if (! $lastOrder || ! $lastOrder->period_end) {
            return null;
        }

        // Next payment should be the day after the last billing period ended
        return $lastOrder->period_end->addDay();
    }

    public function getFormattedNextPaymentDate(): ?string
    {
        $nextPaymentDate = $this->getNextPaymentDate();

        if (! $nextPaymentDate) {
            return null;
        }

        $now = Carbon::now();

        // Check if it's today (same date, regardless of time)
        if ($nextPaymentDate->isSameDay($now)) {
            return 'Today';
        }

        // Check if it's tomorrow
        if ($nextPaymentDate->isSameDay($now->copy()->addDay())) {
            return 'Tomorrow';
        }

        // Check if it's yesterday
        if ($nextPaymentDate->isSameDay($now->copy()->subDay())) {
            return 'Yesterday';
        }

        // Calculate time difference
        $totalSeconds = $now->diffInSeconds($nextPaymentDate, false);
        $isPast = $totalSeconds < 0;
        $totalSeconds = abs($totalSeconds);

        // Convert to days, hours, and minutes
        $days = intval($totalSeconds / 86400);
        $remainingSeconds = $totalSeconds % 86400;
        $hours = intval($remainingSeconds / 3600);
        $minutes = intval(($remainingSeconds % 3600) / 60);

        // Build readable format
        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' day'.($days > 1 ? 's' : '');
        }

        if ($hours > 0) {
            $parts[] = $hours.' hour'.($hours > 1 ? 's' : '');
        }

        if ($minutes > 0 && $days == 0) { // Only show minutes if less than a day
            $parts[] = $minutes.' minute'.($minutes > 1 ? 's' : '');
        }

        if (empty($parts)) {
            return $isPast ? 'Just passed' : 'Very soon';
        }

        $result = implode(', ', $parts);

        return $isPast ? $result.' ago' : 'In '.$result;
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

    // Subscription scheduling helper methods
    public function getFormattedBillingCycleAnchor(): ?string
    {
        if (! $this->billing_cycle_anchor) {
            return null;
        }

        return $this->billing_cycle_anchor->format('M d, Y \a\t g:i A');
    }

    public function getFormattedTrialEnd(): ?string
    {
        if (! $this->trial_end_at) {
            return null;
        }

        return $this->trial_end_at->format('M d, Y \a\t g:i A');
    }

    public function isInTrial(): bool
    {
        return $this->trial_end_at && $this->trial_end_at->isFuture() &&
               in_array($this->subscription_status, ['trialing', 'active']);
    }

    public function hasCustomBillingAnchor(): bool
    {
        return $this->billing_cycle_anchor !== null;
    }

    public function getSubscriptionTimezone(): string
    {
        return $this->subscription_timezone ?? config('app.timezone', 'UTC');
    }

    public function updateSubscriptionSchedule(array $scheduleData): void
    {
        $this->update($scheduleData);
    }

    // Manual payment utility methods
    public function isManualPaymentType(): bool
    {
        return $this->payment_method_type === 'manual';
    }

    public function isAutomaticPaymentType(): bool
    {
        return $this->payment_method_type === 'automatic';
    }

    public function requiresManualPayment(): bool
    {
        return $this->manual_payment_required;
    }

    public function markManualPaymentRequired(): void
    {
        $this->update(['manual_payment_required' => true]);
    }

    public function markManualPaymentCompleted(): void
    {
        $this->update(['manual_payment_required' => false]);
    }

    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method_type) {
            'automatic' => 'Automatic (Card)',
            'manual' => 'Manual Payment',
            default => ucfirst($this->payment_method_type ?? 'automatic'),
        };
    }

    public function canSwitchPaymentMethod(): bool
    {
        // Allow switching in most cases - individual methods have specific validation
        // Only restriction: don't switch if there are business constraints
        return true;
    }

    public function hasManualOrders(): bool
    {
        return $this->orders()->where('billing_reason', Order::REASON_MANUAL)->exists();
    }

    public function getLatestManualOrder(): ?Order
    {
        return $this->orders()
            ->where('billing_reason', Order::REASON_MANUAL)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function studentHasPaymentMethods(): bool
    {
        // Check if student's user has active payment methods
        if (! $this->student || ! $this->student->user) {
            return false;
        }

        return $this->student->user->paymentMethods()
            ->where('is_active', true)
            ->exists();
    }

    public function canSwitchToAutomatic(): bool
    {
        // Can switch to automatic if:
        // 1. Currently manual payment method, AND
        // 2. Can switch payment method (business rules), AND
        // 3. Student has payment methods in Stripe
        return $this->isManualPaymentType() &&
               $this->canSwitchPaymentMethod() &&
               $this->studentHasPaymentMethods();
    }

    public function canSwitchToManual(): bool
    {
        // Can switch to manual if:
        // 1. Currently automatic payment method, AND
        // 2. Can switch payment method (business rules)
        return $this->isAutomaticPaymentType() &&
               $this->canSwitchPaymentMethod();
    }
}
