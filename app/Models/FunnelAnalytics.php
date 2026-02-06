<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelAnalytics extends Model
{
    use HasFactory;

    protected $table = 'funnel_analytics';

    protected $fillable = [
        'funnel_id',
        'funnel_step_id',
        'date',
        'unique_visitors',
        'pageviews',
        'conversions',
        'revenue',
        'avg_time_seconds',
        'bounce_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'revenue' => 'decimal:2',
        ];
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'funnel_step_id');
    }

    // Calculated metrics
    public function getConversionRate(): float
    {
        if ($this->unique_visitors === 0) {
            return 0;
        }

        return round(($this->conversions / $this->unique_visitors) * 100, 2);
    }

    public function getBounceRate(): float
    {
        if ($this->pageviews === 0) {
            return 0;
        }

        return round(($this->bounce_count / $this->pageviews) * 100, 2);
    }

    public function getAverageTimeFormatted(): string
    {
        $minutes = floor($this->avg_time_seconds / 60);
        $seconds = $this->avg_time_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getRevenuePerVisitor(): float
    {
        if ($this->unique_visitors === 0) {
            return 0;
        }

        return round($this->revenue / $this->unique_visitors, 2);
    }

    public function getFormattedRevenue(): string
    {
        return 'RM '.number_format($this->revenue, 2);
    }

    // Increment methods
    public function incrementVisitors(): void
    {
        $this->increment('unique_visitors');
    }

    public function incrementPageviews(): void
    {
        $this->increment('pageviews');
    }

    public function incrementConversions(float $revenue = 0): void
    {
        $this->increment('conversions');

        if ($revenue > 0) {
            $this->addRevenue($revenue);
        }
    }

    public function incrementBounce(): void
    {
        $this->increment('bounce_count');
    }

    public function addRevenue(float $amount): void
    {
        $this->increment('revenue', $amount);
    }

    // Static helper to get or create today's analytics
    public static function getOrCreateForToday(int $funnelId, ?int $stepId = null): self
    {
        $date = now()->toDateString();

        $record = self::where('funnel_id', $funnelId)
            ->where('funnel_step_id', $stepId)
            ->whereDate('date', $date)
            ->first();

        if ($record) {
            return $record;
        }

        try {
            return self::create([
                'funnel_id' => $funnelId,
                'funnel_step_id' => $stepId,
                'date' => $date,
                'unique_visitors' => 0,
                'pageviews' => 0,
                'conversions' => 0,
                'revenue' => 0,
                'avg_time_seconds' => 0,
                'bounce_count' => 0,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return self::where('funnel_id', $funnelId)
                ->where('funnel_step_id', $stepId)
                ->whereDate('date', $date)
                ->first();
        }
    }

    // Scopes
    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }

    public function scopeForStep($query, int $stepId)
    {
        return $query->where('funnel_step_id', $stepId);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->where('date', now()->toDateString());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ]);
    }
}
