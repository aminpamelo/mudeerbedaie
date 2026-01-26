<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowConnection extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'source_step_id',
        'target_step_id',
        'source_handle',
        'target_handle',
        'label',
        'condition_config',
    ];

    protected function casts(): array
    {
        return [
            'condition_config' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function sourceStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'source_step_id');
    }

    public function targetStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'target_step_id');
    }
}
