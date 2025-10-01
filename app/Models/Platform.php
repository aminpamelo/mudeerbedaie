<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'description',
        'website_url',
        'api_base_url',
        'logo_url',
        'color_primary',
        'color_secondary',
        'type',
        'features',
        'required_credentials',
        'settings',
        'is_active',
        'supports_orders',
        'supports_products',
        'supports_webhooks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'required_credentials' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'supports_orders' => 'boolean',
            'supports_products' => 'boolean',
            'supports_webhooks' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(PlatformAccount::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ProductOrder::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(PlatformWebhook::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(PlatformProduct::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isConnected(): bool
    {
        return $this->accounts()->where('is_active', true)->exists();
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}
