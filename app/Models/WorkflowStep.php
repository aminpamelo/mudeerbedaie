<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'uuid',
        'node_id',
        'type',
        'action_type',
        'name',
        'config',
        'position_x',
        'position_y',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'order_index' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($step) {
            if (empty($step->uuid)) {
                $step->uuid = (string) Str::uuid();
            }
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(WorkflowConnection::class, 'source_step_id');
    }

    public function incomingConnections(): HasMany
    {
        return $this->hasMany(WorkflowConnection::class, 'target_step_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowStepExecution::class, 'step_id');
    }

    public function isTrigger(): bool
    {
        return $this->type === 'trigger';
    }

    public function isAction(): bool
    {
        return $this->type === 'action';
    }

    public function isCondition(): bool
    {
        return $this->type === 'condition';
    }

    public function isDelay(): bool
    {
        return $this->type === 'delay';
    }
}
