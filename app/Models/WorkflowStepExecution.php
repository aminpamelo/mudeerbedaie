<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepExecution extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'enrollment_id',
        'step_id',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'result',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(WorkflowEnrollment::class, 'enrollment_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsCompleted(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result' => $result,
        ]);
    }

    public function markAsFailed(array $result = []): void
    {
        $this->update([
            'status' => 'failed',
            'result' => $result,
        ]);
    }
}
