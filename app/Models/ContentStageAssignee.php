<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentStageAssignee extends Model
{
    protected $fillable = [
        'content_stage_id',
        'employee_id',
        'role',
    ];

    /**
     * Get the stage this assignee belongs to
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ContentStage::class, 'content_stage_id');
    }

    /**
     * Get the employee assigned to this stage
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
