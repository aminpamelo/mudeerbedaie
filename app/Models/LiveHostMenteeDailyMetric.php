<?php

namespace App\Models;

use Database\Factories\LiveHostMenteeDailyMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeDailyMetric extends Model
{
    /** @use HasFactory<LiveHostMenteeDailyMetricFactory> */
    use HasFactory;

    protected $table = 'live_host_mentee_daily_metrics';

    protected $fillable = [
        'mentee_id', 'metric_date', 'sales_override', 'comment', 'commented_by', 'commented_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'sales_override' => 'decimal:2',
            'commented_at' => 'datetime',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function commentedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commented_by');
    }
}
