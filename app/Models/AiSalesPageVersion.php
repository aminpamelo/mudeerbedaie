<?php

namespace App\Models;

use Database\Factories\AiSalesPageVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSalesPageVersion extends Model
{
    /** @use HasFactory<AiSalesPageVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'ai_sales_page_id',
        'version',
        'label',
        'html',
        'custom_css',
        'custom_js',
        'generated_by',
        'prompt_snapshot',
        'model',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(AiSalesPage::class, 'ai_sales_page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
