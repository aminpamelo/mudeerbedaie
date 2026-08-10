<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimeEntry extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'user_id', 'started_at', 'ended_at', 'duration_seconds', 'description'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function stop(): void
    {
        $this->update([
            'ended_at' => now(),
            'duration_seconds' => now()->diffInSeconds($this->started_at),
        ]);
    }
}
