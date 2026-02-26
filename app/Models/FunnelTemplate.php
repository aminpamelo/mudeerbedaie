<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'thumbnail_url',
        'template_data',
        'is_premium',
        'is_active',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'template_data' => 'array',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class, 'template_id');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
