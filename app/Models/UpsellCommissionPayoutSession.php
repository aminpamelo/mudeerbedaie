<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsellCommissionPayoutSession extends Model
{
    /** @use HasFactory<\Database\Factories\UpsellCommissionPayoutSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'upsell_commission_payout_id',
        'class_session_id',
        'paid_revenue',
        'commission_rate',
        'commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'paid_revenue' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(UpsellCommissionPayout::class, 'upsell_commission_payout_id');
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }
}
