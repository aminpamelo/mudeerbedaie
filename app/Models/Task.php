<?php

namespace App\Models;

use App\Services\Ceo\CeoDashboardService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Keep the task_assignee pivot consistent for tasks created/assigned through
     * any path (CEO, HR, meeting action items): the primary `assigned_to` is
     * always one of the co-owners. CEO multi-assign adds further co-owners on top.
     */
    protected static function booted(): void
    {
        static::saved(function (Task $task): void {
            if (! $task->assigned_to) {
                return;
            }

            if (! $task->assignees()->where('employees.id', $task->assigned_to)->exists()) {
                $task->assignees()->attach($task->assigned_to);
            }
        });

        // Any task mutation invalidates the CEO's cached task aggregates (Task
        // Monitoring + Staff KPI) so changes made from any surface — the HR
        // module, meeting action items or the CEO board — appear immediately
        // instead of waiting out the cache TTL.
        $bustCeoCache = static fn () => CeoDashboardService::bustTaskCache();
        static::saved($bustCeoCache);
        static::deleted($bustCeoCache);
        static::restored($bustCeoCache);
        static::forceDeleted($bustCeoCache);
    }

    protected $fillable = [
        'taskable_type',
        'taskable_id',
        'category_id',
        'parent_id',
        'project_id',
        'title',
        'description',
        'assigned_to',
        'assigned_by',
        'priority',
        'status',
        'deadline',
        'completed_at',
        'reminders_sent',
        'estimated_minutes',
        'actual_minutes',
        'start_date',
        'sort_order',
        'is_recurring',
        'recurring_config_id',
        'approval_status',
        'approved_by',
        'approved_at',
        'points',
        'tags',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'completed_at' => 'datetime',
            'reminders_sent' => 'array',
            'start_date' => 'date',
            'approved_at' => 'datetime',
            'tags' => 'array',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Get the parent model (polymorphic).
     */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the parent task.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get the category this task belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    /**
     * Get the subtasks.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * Get the employee this task is assigned to.
     *
     * This is the "primary" owner — always one of the {@see assignees()}. Kept as
     * a single column so existing per-assignee features keep working; the full set
     * of co-owners lives in the task_assignee pivot.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /**
     * All employees who co-own this task (a shared task can have several owners).
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignee', 'task_id', 'employee_id')
            ->withTimestamps();
    }

    /**
     * Get the employee who assigned this task.
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    /**
     * Get the comments on this task.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * Get the attachments for this task.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Get the TMS project this task belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TmsProject::class, 'project_id');
    }

    /**
     * Get the checklists for this task.
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class);
    }

    /**
     * Get the time entries for this task.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class);
    }

    /**
     * Get the activity logs for this task.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class);
    }

    /**
     * Get the watchers for this task.
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')->withTimestamps();
    }

    /**
     * Get the dependencies for this task.
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    /**
     * Get tasks that depend on this task.
     */
    public function dependedOnBy(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    /**
     * Get the recurring configuration for this task.
     */
    public function recurringConfig(): HasOne
    {
        return $this->hasOne(TaskRecurringConfig::class);
    }

    /**
     * Get the user who approved this task.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
