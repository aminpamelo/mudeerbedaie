<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSendLog extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_send_logs';

    protected $fillable = [
        'send_date',
        'message_count',
        'success_count',
        'failure_count',
        'device_token',
    ];

    protected function casts(): array
    {
        return [
            'send_date' => 'date',
            'message_count' => 'integer',
            'success_count' => 'integer',
            'failure_count' => 'integer',
        ];
    }

    /**
     * Get the success rate as a percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->message_count === 0) {
            return 0;
        }

        return round(($this->success_count / $this->message_count) * 100, 1);
    }

    /**
     * Get the failure rate as a percentage.
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->message_count === 0) {
            return 0;
        }

        return round(($this->failure_count / $this->message_count) * 100, 1);
    }

    /**
     * Scope to get logs for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('send_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get today's log.
     */
    public function scopeToday($query)
    {
        return $query->where('send_date', today());
    }

    /**
     * Scope to get this week's logs.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('send_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope to get this month's logs.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('send_date', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }

    /**
     * Get total messages for a date range.
     */
    public static function getTotalMessages($startDate = null, $endDate = null): int
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query->sum('message_count');
    }

    /**
     * Get success count for a date range.
     */
    public static function getTotalSuccess($startDate = null, $endDate = null): int
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query->sum('success_count');
    }

    /**
     * Get failure count for a date range.
     */
    public static function getTotalFailures($startDate = null, $endDate = null): int
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query->sum('failure_count');
    }
}
