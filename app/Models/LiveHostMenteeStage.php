<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeStage extends Model
{
    use HasFactory;

    protected $table = 'live_host_mentee_stages';

    protected $fillable = [
        'mentee_id', 'stage_id', 'assignee_id',
        'due_at', 'stage_notes', 'entered_at', 'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentoringStage::class, 'stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('exited_at');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null
            && $this->exited_at === null
            && $this->due_at->isPast();
    }
}
