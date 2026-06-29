<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSalesPageMessage extends Model
{
    protected $fillable = [
        'ai_sales_page_id',
        'role',
        'content',
        'status',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(AiSalesPage::class, 'ai_sales_page_id');
    }
}
