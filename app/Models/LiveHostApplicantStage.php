<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostApplicantStage extends Model
{
    use HasFactory;

    protected $table = 'live_host_applicant_stages';

    protected $fillable = [
        'applicant_id', 'stage_id', 'assignee_id',
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

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(LiveHostApplicant::class, 'applicant_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'stage_id');
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
