<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A link to one Facebook Business Manager, authenticated by a (system user)
 * access token with ads_read (plus ads_management to run ads — can_manage_ads). user_id is null for shared company connections
 * and set to the fighter for BMs a fighter linked from the Fighter portal.
 */
class FacebookAdConnection extends Model
{
    /** @use HasFactory<\Database\Factories\FacebookAdConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'business_manager_id',
        'access_token',
        'status',
        'status_message',
        'can_manage_ads',
        'permissions',
        'last_synced_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'can_manage_ads' => 'boolean',
            'permissions' => 'array',
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
