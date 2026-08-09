<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class VaultTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saving(function (VaultTag $tag): void {
            if (blank($tag->slug) && filled($tag->name)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    // ---------------------------------------------------------------- relations

    public function credentials(): BelongsToMany
    {
        return $this->belongsToMany(VaultCredential::class, 'vault_credential_tag');
    }
}
