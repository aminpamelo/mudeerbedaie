<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipGoal extends Model
{
    protected $fillable = ['pip_id', 'title', 'description', 'target_date', 'status', 'check_in_notes', 'checked_at'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'checked_at' => 'datetime'];
    }

    public function pip(): BelongsTo
    {
        return $this->belongsTo(PerformanceImprovementPlan::class, 'pip_id');
    }
}
