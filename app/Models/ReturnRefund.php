<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReturnRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_number',
        'order_id',
        'package_id',
        'customer_id',
        'processed_by',
        'return_date',
        'reason',
        'refund_amount',
        'decision',
        'decision_reason',
        'decision_date',
        'tracking_number',
        'account_number',
        'account_holder_name',
        'bank_name',
        'status',
        'notes',
        'attachments',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'decision_date' => 'datetime',
            'refund_amount' => 'decimal:2',
            'attachments' => 'array',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Decision helper methods
    public function isPending(): bool
    {
        return $this->decision === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->decision === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->decision === 'rejected';
    }

    public function isRefundCompleted(): bool
    {
        return $this->status === 'refund_completed';
    }

    // Decision methods
    public function approve(User $user, ?string $reason = null): void
    {
        $this->update([
            'decision' => 'approved',
            'decision_reason' => $reason,
            'decision_date' => now(),
            'processed_by' => $user->id,
            'status' => 'approved_pending_return',
        ]);
    }

    public function reject(User $user, string $reason): void
    {
        $this->update([
            'decision' => 'rejected',
            'decision_reason' => $reason,
            'decision_date' => now(),
            'processed_by' => $user->id,
            'status' => 'rejected',
        ]);
    }

    public function markItemReceived(): void
    {
        $this->update(['status' => 'item_received']);
    }

    public function markRefundProcessing(): void
    {
        $this->update(['status' => 'refund_processing']);
    }

    public function markRefundCompleted(): void
    {
        $this->update(['status' => 'refund_completed']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // Helper methods
    public function getCustomerName(): string
    {
        if ($this->customer) {
            return $this->customer->name;
        }

        if ($this->order) {
            return $this->order->getCustomerName();
        }

        return 'Unknown Customer';
    }

    public function getOrderNumber(): string
    {
        return $this->order?->order_number ?? 'N/A';
    }

    public function getPackageName(): string
    {
        return $this->package?->name ?? 'N/A';
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending_review' => 'Pending Review',
            'approved_pending_return' => 'Approved - Pending Return',
            'item_received' => 'Item Received',
            'refund_processing' => 'Refund Processing',
            'refund_completed' => 'Refund Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending_review' => 'yellow',
            'approved_pending_return' => 'blue',
            'item_received' => 'purple',
            'refund_processing' => 'cyan',
            'refund_completed' => 'green',
            'rejected' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getDecisionLabel(): string
    {
        return match ($this->decision) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->decision ?? 'pending'),
        };
    }

    public function getDecisionColor(): string
    {
        return match ($this->decision) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }

    // Static methods
    public static function generateRefundNumber(): string
    {
        do {
            $number = 'RR-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (self::where('refund_number', $number)->exists());

        return $number;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('decision', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('decision', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('decision', 'rejected');
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('refund_number', 'like', "%{$search}%")
                ->orWhere('tracking_number', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhereHas('order', function ($orderQuery) use ($search) {
                    $orderQuery->where('order_number', 'like', "%{$search}%");
                })
                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('package', function ($packageQuery) use ($search) {
                    $packageQuery->where('name', 'like', "%{$search}%");
                });
        });
    }
}
