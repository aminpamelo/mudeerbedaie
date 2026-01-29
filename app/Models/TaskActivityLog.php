<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TaskActivityLog $log) {
            $log->created_at = $log->created_at ?? now();
        });
    }

    /**
     * Get the task this log belongs to
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who performed this action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted action label
     */
    public function getActionLabel(): string
    {
        return match ($this->action) {
            'created' => 'created the task',
            'status_changed' => 'changed the status',
            'assigned' => 'assigned the task',
            'unassigned' => 'unassigned the task',
            'commented' => 'added a comment',
            'priority_changed' => 'changed the priority',
            'due_date_changed' => 'changed the due date',
            'title_changed' => 'changed the title',
            'description_changed' => 'changed the description',
            default => $this->action,
        };
    }
}
