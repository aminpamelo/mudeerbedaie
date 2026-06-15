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
        'type_id',
        'priority',
        'category_id',
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

    protected static function booted(): void
    {
        static::created(function (ItTicket $ticket): void {
            $ticket->notifyAssignee();
        });

        static::updated(function (ItTicket $ticket): void {
            if ($ticket->wasChanged('assignee_id')) {
                $ticket->notifyAssignee();
            }
        });
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItTicketCategory::class, 'category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ItTicketType::class, 'type_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ItTicketComment::class)->orderBy('created_at', 'asc');
    }

    /**
     * Notify the assignee that this ticket is now in their charge.
     */
    public function notifyAssignee(): void
    {
        if (! $this->assignee_id) {
            return;
        }

        if (auth()->check() && auth()->id() === $this->assignee_id) {
            return;
        }

        $this->assignee()->first()?->notify(new \App\Notifications\ItTicketAssignedNotification($this));
    }

    // --- Helper Methods ---

    public static function generateTicketNumber(): string
    {
        do {
            $number = 'IT-'.date('Ymd').'-'.strtoupper(Str::random(5));
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    public static function statuses(): array
    {
        return ['backlog', 'todo', 'in_progress', 'review', 'testing', 'done'];
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
        return $this->type?->name ?? 'No type';
    }

    /**
     * The type's hex color (custom types carry arbitrary colors), with a neutral
     * zinc fallback when a ticket has no type.
     */
    public function getTypeColor(): string
    {
        return $this->type?->color ?? '#71717a';
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

    /**
     * Classify the deadline so the UI can render a consistent indicator.
     *
     * @return 'none'|'completed'|'overdue'|'due_today'|'due_soon'|'on_track'
     */
    public function deadlineStatus(): string
    {
        if (! $this->due_date) {
            return 'none';
        }

        if ($this->status === 'done') {
            return 'completed';
        }

        $today = now()->startOfDay();
        $due = $this->due_date->copy()->startOfDay();

        if ($due->lt($today)) {
            return 'overdue';
        }

        if ($due->eq($today)) {
            return 'due_today';
        }

        if ($due->lte($today->copy()->addDays(3))) {
            return 'due_soon';
        }

        return 'on_track';
    }

    /**
     * Presentation metadata for the deadline indicator.
     *
     * @return array{key: string, label: string, short: string, icon: string, classes: string, dot: string}
     */
    public function deadlineMeta(): array
    {
        $status = $this->deadlineStatus();
        $date = $this->due_date;

        return match ($status) {
            'overdue' => [
                'key' => 'overdue',
                'label' => 'Overdue'.($date ? ' · '.$date->format('j M Y') : ''),
                'short' => $date?->format('j M') ?? 'Overdue',
                'icon' => 'exclamation-triangle',
                'classes' => 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400',
                'dot' => 'bg-red-500',
            ],
            'due_today' => [
                'key' => 'due_today',
                'label' => 'Due today'.($date ? ' · '.$date->format('j M Y') : ''),
                'short' => $date?->format('j M') ?? 'Today',
                'icon' => 'clock',
                'classes' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400',
                'dot' => 'bg-amber-500',
            ],
            'due_soon' => [
                'key' => 'due_soon',
                'label' => 'Due '.($date?->format('j M Y') ?? 'soon'),
                'short' => $date?->format('j M') ?? 'Soon',
                'icon' => 'calendar',
                'classes' => 'text-yellow-600 bg-yellow-50 dark:bg-yellow-500/10 dark:text-yellow-400',
                'dot' => 'bg-yellow-500',
            ],
            'on_track' => [
                'key' => 'on_track',
                'label' => 'Due '.($date?->format('j M Y') ?? ''),
                'short' => $date?->format('j M') ?? '',
                'icon' => 'calendar',
                'classes' => 'text-zinc-500 bg-zinc-100 dark:bg-zinc-700/40 dark:text-zinc-400',
                'dot' => 'bg-zinc-400',
            ],
            'completed' => [
                'key' => 'completed',
                'label' => 'Completed'.($date ? ' · due '.$date->format('j M Y') : ''),
                'short' => $date?->format('j M') ?? 'Done',
                'icon' => 'check-circle',
                'classes' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400',
                'dot' => 'bg-emerald-500',
            ],
            default => [
                'key' => 'none',
                'label' => 'No deadline',
                'short' => '—',
                'icon' => 'calendar',
                'classes' => 'text-zinc-400 bg-zinc-100/60 dark:bg-zinc-700/30 dark:text-zinc-500',
                'dot' => 'bg-zinc-300 dark:bg-zinc-600',
            ],
        };
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
