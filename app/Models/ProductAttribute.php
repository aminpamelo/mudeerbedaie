<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'attribute_name',
        'attribute_value',
        'attribute_type',
        'is_filterable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isFilterable(): bool
    {
        return $this->is_filterable;
    }

    public function isText(): bool
    {
        return $this->attribute_type === 'text';
    }

    public function isNumber(): bool
    {
        return $this->attribute_type === 'number';
    }

    public function isBoolean(): bool
    {
        return $this->attribute_type === 'boolean';
    }

    public function isDate(): bool
    {
        return $this->attribute_type === 'date';
    }

    public function getFormattedValueAttribute(): string
    {
        return match ($this->attribute_type) {
            'boolean' => $this->attribute_value ? 'Yes' : 'No',
            'date' => date('Y-m-d', strtotime($this->attribute_value)),
            'number' => number_format((float) $this->attribute_value, 2),
            default => $this->attribute_value,
        };
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    public function scopeByName($query, $name)
    {
        return $query->where('attribute_name', $name);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('attribute_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('attribute_name');
    }
}
