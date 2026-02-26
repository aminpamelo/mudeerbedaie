<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'student_id',
        'current_step_id',
        'status',
        'entered_at',
        'completed_at',
        'exited_at',
        'exit_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'entered_at' => 'datetime',
            'completed_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function stepExecutions(): HasMany
    {
        return $this->hasMany(WorkflowStepExecution::class, 'enrollment_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function exit(?string $reason = null): void
    {
        $this->update([
            'status' => 'exited',
            'exited_at' => now(),
            'exit_reason' => $reason,
        ]);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function resume(): void
    {
        $this->update(['status' => 'active']);
    }
}
