<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFactory> */
    use HasFactory;

    protected $fillable = [
        'asset_tag',
        'asset_category_id',
        'name',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'warranty_expiry',
        'condition',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'warranty_expiry' => 'date',
        ];
    }

    /**
     * Get the category this asset belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * Get all assignments for this asset.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Get the current active assignment for this asset.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->where('status', 'active');
    }

    /**
     * Scope to filter available assets.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to filter assigned assets.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Generate the next asset tag in AST-0001 format.
     */
    public static function generateAssetTag(): string
    {
        $lastAsset = static::query()
            ->where('asset_tag', 'like', 'AST-%')
            ->orderByDesc('asset_tag')
            ->first();

        if ($lastAsset) {
            $lastNumber = (int) substr($lastAsset->asset_tag, 4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return 'AST-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
