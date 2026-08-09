<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VaultCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (VaultCategory $category): void {
            if (blank($category->slug) && filled($category->name)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // ---------------------------------------------------------------- relations

    public function credentials(): HasMany
    {
        return $this->hasMany(VaultCredential::class, 'category_id');
    }

    // ------------------------------------------------------------------ scopes

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
