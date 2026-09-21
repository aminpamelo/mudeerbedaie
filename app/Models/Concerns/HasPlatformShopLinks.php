<?php

namespace App\Models\Concerns;

use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Shared "is this catalog item linked to an external platform shop?" behaviour.
 *
 * Products link through platform_sku_mappings.product_id and packages through
 * platform_sku_mappings.package_id; the foreign key is inferred from the model
 * that uses the trait, so a single implementation serves both.
 */
trait HasPlatformShopLinks
{
    public function activePlatformSkuMappings(): HasMany
    {
        return $this->hasMany(PlatformSkuMapping::class)->where('is_active', true);
    }

    /**
     * Distinct external shop accounts this item is actively linked to. Reads the
     * eager-loaded relation to avoid N+1 queries in listings; eager load
     * `activePlatformSkuMappings.platformAccount` when rendering many rows.
     *
     * @return Collection<int, PlatformAccount>
     */
    public function linkedShopAccounts(): Collection
    {
        return $this->activePlatformSkuMappings
            ->pluck('platformAccount')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function isLinkedToShop(): bool
    {
        return $this->activePlatformSkuMappings->isNotEmpty();
    }

    public function scopeLinkedToShop(Builder $query): Builder
    {
        return $query->whereHas('activePlatformSkuMappings');
    }

    public function scopeNotLinkedToShop(Builder $query): Builder
    {
        return $query->whereDoesntHave('activePlatformSkuMappings');
    }
}
