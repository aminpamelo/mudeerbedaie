<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of every write the platform makes to Facebook Ads — manual
 * pause/budget edits, campaign launches, and automated rule actions
 * (including dry-run "would have done" entries).
 */
class FacebookAdAction extends Model
{
    protected $fillable = [
        'facebook_ad_account_id',
        'user_id',
        'facebook_ad_rule_id',
        'source',
        'object_type',
        'object_id',
        'object_name',
        'action',
        'before',
        'after',
        'dry_run',
        'success',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'dry_run' => 'boolean',
            'success' => 'boolean',
        ];
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'facebook_ad_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FacebookAdRule::class, 'facebook_ad_rule_id');
    }
}
