<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerFeedback extends Model
{
    protected $table = 'customer_feedbacks';

    protected $fillable = [
        'feedback_number',
        'order_id',
        'customer_id',
        'reviewed_by',
        'type',
        'rating',
        'subject',
        'message',
        'status',
        'admin_response',
        'responded_at',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'is_public' => 'boolean',
            'rating' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function respond(string $response, User $reviewer): void
    {
        $this->update([
            'admin_response' => $response,
            'reviewed_by' => $reviewer->id,
            'responded_at' => now(),
            'status' => 'responded',
        ]);
    }

    public function markAsReviewed(User $reviewer): void
    {
        $this->update([
            'reviewed_by' => $reviewer->id,
            'status' => 'reviewed',
        ]);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    public function togglePublic(): void
    {
        $this->update(['is_public' => ! $this->is_public]);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'reviewed' => 'Reviewed',
            'responded' => 'Responded',
            'archived' => 'Archived',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'reviewed' => 'blue',
            'responded' => 'green',
            'archived' => 'gray',
            default => 'gray',
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'complaint' => 'Complaint',
            'suggestion' => 'Suggestion',
            'compliment' => 'Compliment',
            'question' => 'Question',
            'other' => 'Other',
            default => ucfirst($this->type),
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            'complaint' => 'red',
            'suggestion' => 'blue',
            'compliment' => 'green',
            'question' => 'purple',
            'other' => 'gray',
            default => 'gray',
        };
    }

    public function getRatingStars(): string
    {
        if (! $this->rating) {
            return 'No rating';
        }

        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }

    public function getCustomerName(): string
    {
        return $this->customer?->name ?? $this->order?->getCustomerName() ?? 'Unknown';
    }

    public static function generateFeedbackNumber(): string
    {
        do {
            $number = 'FB-'.date('Ymd').'-'.strtoupper(Str::random(5));
        } while (self::where('feedback_number', $number)->exists());

        return $number;
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('feedback_number', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
        });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    public function scopeResponded($query)
    {
        return $query->where('status', 'responded');
    }

    public static function getAverageRating(): float
    {
        return (float) self::whereNotNull('rating')->avg('rating') ?? 0;
    }
}
