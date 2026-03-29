<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceImprovementPlan extends Model
{
    /** @use HasFactory<\Database\Factories\PerformanceImprovementPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id', 'initiated_by', 'performance_review_id', 'reason',
        'start_date', 'end_date', 'status', 'outcome_notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'initiated_by');
    }

    public function performanceReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(PipGoal::class, 'pip_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
