<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'options',
        'default_value',
        'is_required',
        'is_filterable',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($field) {
            if (empty($field->slug)) {
                $field->slug = Str::slug($field->name, '_');
            }
        });
    }

    public function studentValues(): HasMany
    {
        return $this->hasMany(StudentCustomField::class);
    }

    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function isNumber(): bool
    {
        return $this->type === 'number';
    }

    public function isDate(): bool
    {
        return $this->type === 'date';
    }

    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    public function isSelect(): bool
    {
        return $this->type === 'select';
    }

    public function isMultiselect(): bool
    {
        return $this->type === 'multiselect';
    }

    public function hasOptions(): bool
    {
        return $this->isSelect() || $this->isMultiselect();
    }
}
