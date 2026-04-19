<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokOrder extends Model
{
    protected $fillable = [
        'import_id',
        'tiktok_order_id',
        'order_status',
        'order_substatus',
        'cancelation_return_type',
        'created_time',
        'paid_time',
        'rts_time',
        'shipped_time',
        'delivered_time',
        'cancelled_time',
        'order_amount_myr',
        'order_refund_amount_myr',
        'payment_method',
        'fulfillment_type',
        'product_category',
        'matched_live_session_id',
        'raw_row_json',
    ];

    protected function casts(): array
    {
        return [
            'created_time' => 'datetime',
            'paid_time' => 'datetime',
            'rts_time' => 'datetime',
            'shipped_time' => 'datetime',
            'delivered_time' => 'datetime',
            'cancelled_time' => 'datetime',
            'order_amount_myr' => 'decimal:2',
            'order_refund_amount_myr' => 'decimal:2',
            'raw_row_json' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(TiktokReportImport::class, 'import_id');
    }

    public function matchedLiveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'matched_live_session_id');
    }
}
