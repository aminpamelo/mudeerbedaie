<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmsUserStat extends Model
{
    protected $fillable = ['user_id', 'date', 'tasks_completed', 'tasks_created', 'tasks_overdue', 'time_tracked_seconds', 'streak_days', 'total_points'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
