<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPlatform extends Model
{
    /** @use HasFactory<\Database\Factories\CmsPlatformFactory> */
    use HasFactory;

    protected $table = 'cms_platforms';

    protected $fillable = [
        'key',
        'name',
        'icon',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CmsContentPlatformPost::class, 'platform_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)->orderBy('sort_order');
    }
}
