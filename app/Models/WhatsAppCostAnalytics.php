<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WhatsAppCostAnalytics extends Model
{
    protected $table = 'whatsapp_cost_analytics';

    protected $fillable = [
        'date',
        'country_code',
        'pricing_category',
        'message_volume',
        'cost_usd',
        'cost_myr',
        'granularity',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'message_volume' => 'integer',
            'cost_usd' => 'decimal:6',
            'cost_myr' => 'decimal:4',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<WhatsAppCostAnalytics>  $query
     * @return Builder<WhatsAppCostAnalytics>
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * @param  Builder<WhatsAppCostAnalytics>  $query
     * @return Builder<WhatsAppCostAnalytics>
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('pricing_category', strtoupper($category));
    }

    /**
     * @param  Builder<WhatsAppCostAnalytics>  $query
     * @return Builder<WhatsAppCostAnalytics>
     */
    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }
}
