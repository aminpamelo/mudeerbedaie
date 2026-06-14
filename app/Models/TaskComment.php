<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'employee_id',
        'user_id',
        'content',
    ];

    /**
     * Get the task this comment belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the employee who wrote this comment (when the author is an employee).
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user account that authored this comment (always set).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
