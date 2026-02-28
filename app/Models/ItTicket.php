<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ItTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'position',
        'reporter_id',
        'assignee_id',
        'due_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    // --- Relationships ---

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ItTicketComment::class)->orderBy('created_at', 'asc');
    }

    // --- Helper Methods ---

    public static function generateTicketNumber(): string
    {
        do {
            $number = 'IT-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    public static function statuses(): array
    {
        return ['backlog', 'todo', 'in_progress', 'review', 'testing', 'done'];
    }

    public static function types(): array
    {
        return ['bug', 'feature', 'task', 'improvement'];
    }

    public static function priorities(): array
    {
        return ['low', 'medium', 'high', 'urgent'];
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'backlog' => 'Backlog',
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'review' => 'Review',
            'testing' => 'Testing',
            'done' => 'Done',
            default => ucfirst($this->status),
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'bug' => 'Bug',
            'feature' => 'Feature',
            'task' => 'Task',
            'improvement' => 'Improvement',
            default => ucfirst($this->type),
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            'bug' => 'red',
            'feature' => 'green',
            'task' => 'blue',
            'improvement' => 'yellow',
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

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'backlog' => 'gray',
            'todo' => 'blue',
            'in_progress' => 'yellow',
            'review' => 'purple',
            'testing' => 'orange',
            'done' => 'green',
            default => 'gray',
        };
    }

    public function markDone(): void
    {
        $this->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'done';
    }

    public function scopeSearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('reporter', fn ($r) => $r->where('name', 'like', "%{$search}%"))
                ->orWhereHas('assignee', fn ($a) => $a->where('name', 'like', "%{$search}%"));
        });
    }
}
