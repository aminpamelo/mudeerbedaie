<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A link to one Facebook Business Manager, authenticated by a (system user)
 * access token with ads_read. user_id is null for shared company connections;
 * it will be set when fighters link their own BMs later.
 */
class FacebookAdConnection extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'business_manager_id',
        'access_token',
        'status',
        'status_message',
        'last_synced_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(FacebookAdAccount::class);
    }
}
