<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewKpi extends Model
{
    protected $fillable = [
        'performance_review_id', 'kpi_template_id', 'title', 'target',
        'weight', 'self_score', 'self_comments', 'manager_score', 'manager_comments',
    ];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2'];
    }

    public function performanceReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class);
    }

    public function kpiTemplate(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class);
    }
}
