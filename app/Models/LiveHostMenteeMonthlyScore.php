<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeMonthlyScore extends Model
{
    use HasFactory;

    protected $table = 'live_host_mentee_monthly_scores';

    protected $fillable = [
        'mentee_id', 'year', 'month', 'attitude_score', 'video_target', 'live_target', 'sales_quantity', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'attitude_score' => 'integer',
            'video_target' => 'integer',
            'live_target' => 'integer',
            'sales_quantity' => 'decimal:2',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The 'YYYY-MM' key used to join scores to a month column in the UI.
     */
    public function periodKey(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
