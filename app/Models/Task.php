<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_number',
        'department_id',
        'title',
        'description',
        'task_type',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'due_date',
        'due_time',
        'started_at',
        'completed_at',
        'cancelled_at',
        'estimated_hours',
        'actual_hours',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Task $task) {
            if (empty($task->task_number)) {
                $task->task_number = self::generateTaskNumber();
            }
        });
    }

    /**
     * Generate unique task number
     */
    public static function generateTaskNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "TASK-{$date}-{$random}";
    }

    /**
     * Get the department that owns this task
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user assigned to this task
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created this task
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all comments for this task
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /**
     * Get root-level comments (not replies)
     */
    public function rootComments(): HasMany
    {
        return $this->hasMany(TaskComment::class)
            ->whereNull('parent_id')
            ->orderBy('created_at');
    }

    /**
     * Get all activity logs for this task
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class)->orderByDesc('created_at');
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        if (in_array($this->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED])) {
            return false;
        }

        return $this->due_date->isPast();
    }

    /**
     * Check if user can edit this task
     */
    public function canBeEditedBy(User $user): bool
    {
        // Admin can't edit (read-only)
        if ($user->isAdmin()) {
            return false;
        }

        // Both PIC and members can edit tasks in their department
        return $user->canEditTasks($this->department);
    }

    /**
     * Check if user can view this task
     */
    public function canBeViewedBy(User $user): bool
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Department members can view
        return $user->departments->contains($this->department_id);
    }

    /**
     * Assign task to a user
     */
    public function assignTo(?User $user): void
    {
        $this->update(['assigned_to' => $user?->id]);
    }

    /**
     * Change task status
     */
    public function changeStatus(TaskStatus $newStatus): void
    {
        $updates = ['status' => $newStatus];

        if ($newStatus === TaskStatus::IN_PROGRESS && ! $this->started_at) {
            $updates['started_at'] = now();
        }

        if ($newStatus === TaskStatus::COMPLETED) {
            $updates['completed_at'] = now();
        }

        if ($newStatus === TaskStatus::CANCELLED) {
            $updates['cancelled_at'] = now();
        }

        $this->update($updates);
    }

    /**
     * Add a comment to this task
     */
    public function addComment(string $content, User $user, bool $isInternal = false, ?int $parentId = null): TaskComment
    {
        return $this->comments()->create([
            'user_id' => $user->id,
            'content' => $content,
            'is_internal' => $isInternal,
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Log activity for this task
     */
    public function logActivity(string $action, ?array $oldValue, ?array $newValue, string $description): TaskActivityLog
    {
        return $this->activityLogs()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
        ]);
    }

    /**
     * Scope to filter by department
     */
    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to filter by status
     */
    public function scopeForStatus(Builder $query, TaskStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get overdue tasks
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED]);
    }

    /**
     * Scope to get upcoming tasks (due within X days)
     */
    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays($days))
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED]);
    }

    /**
     * Scope to filter by assignee
     */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->id);
    }

    /**
     * Scope to filter by task type
     */
    public function scopeByType(Builder $query, TaskType $type): Builder
    {
        return $query->where('task_type', $type);
    }

    /**
     * Scope to order by priority (urgent first)
     */
    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END");
    }
}
