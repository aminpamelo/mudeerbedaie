<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRecurringConfig extends Model
{
    protected $fillable = ['task_id', 'frequency', 'day_of_week', 'day_of_month', 'time_of_day', 'last_generated_at', 'next_due_at', 'is_active'];

    protected function casts(): array
    {
        return ['last_generated_at' => 'datetime', 'next_due_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
