<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttributeTemplate extends Model
{
    use HasFactory;

    protected $table = 'product_attribute_templates';

    protected $fillable = [
        'name',
        'label',
        'type',
        'values',
        'is_required',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'attributes->name', 'name');
    }

    public function scopeActive($query)
    {
        return $query;
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }
}
