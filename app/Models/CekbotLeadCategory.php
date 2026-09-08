<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CekbotLeadCategory extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotLeadCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'sort_order',
    ];

    /**
     * Leads (conversations) filed under this category.
     *
     * @return HasMany<CekbotConversation, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(CekbotConversation::class, 'lead_category_id');
    }

    /**
     * @param  Builder<CekbotLeadCategory>  $query
     * @return Builder<CekbotLeadCategory>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
