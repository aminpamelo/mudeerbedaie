<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number',
        'order_id',
        'customer_id',
        'assigned_to',
        'subject',
        'description',
        'category',
        'status',
        'priority',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->orderBy('created_at', 'asc');
    }

    public function addReply(string $message, User $user, bool $isInternal = false): TicketReply
    {
        return $this->replies()->create([
            'user_id' => $user->id,
            'message' => $message,
            'is_internal' => $isInternal,
        ]);
    }

    public function assignTo(?User $user): void
    {
        $this->update([
            'assigned_to' => $user?->id,
            'status' => $user ? 'in_progress' : $this->status,
        ]);
    }

    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'open' => 'yellow',
            'in_progress' => 'blue',
            'pending' => 'orange',
            'resolved' => 'green',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    public function getCategoryLabel(): string
    {
        return match ($this->category) {
            'refund' => 'Refund Request',
            'return' => 'Return Request',
            'complaint' => 'Complaint',
            'inquiry' => 'Inquiry',
            'other' => 'Other',
            default => ucfirst($this->category),
        };
    }

    public function getCustomerName(): string
    {
        return $this->customer?->name ?? $this->order?->getCustomerName() ?? 'Unknown';
    }

    public static function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhereHas('order', fn($o) => $o->where('order_number', 'like', "%{$search}%"))
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$search}%"));
        });
    }
}
