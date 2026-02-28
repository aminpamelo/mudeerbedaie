<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsAppTemplateFactory> */
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'language',
        'category',
        'status',
        'components',
        'meta_template_id',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<WhatsAppTemplate>  $query
     * @return Builder<WhatsAppTemplate>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'APPROVED');
    }

    /**
     * @param  Builder<WhatsAppTemplate>  $query
     * @return Builder<WhatsAppTemplate>
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
