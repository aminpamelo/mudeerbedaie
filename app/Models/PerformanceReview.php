<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReview extends Model
{
    /** @use HasFactory<\Database\Factories\PerformanceReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'review_cycle_id', 'employee_id', 'reviewer_id', 'status',
        'self_assessment_notes', 'manager_notes', 'overall_rating',
        'rating_label', 'employee_acknowledged', 'acknowledged_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'decimal:1',
            'employee_acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function reviewCycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(ReviewKpi::class);
    }

    public function calculateOverallRating(): ?float
    {
        $kpis = $this->kpis()->whereNotNull('manager_score')->get();

        if ($kpis->isEmpty()) {
            return null;
        }

        $totalWeightedScore = $kpis->sum(fn ($kpi) => $kpi->manager_score * ($kpi->weight / 100));
        $totalWeight = $kpis->sum(fn ($kpi) => $kpi->weight / 100);

        if ($totalWeight === 0.0) {
            return null;
        }

        return round($totalWeightedScore / $totalWeight, 1);
    }
}
