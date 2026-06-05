<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveAccount extends Model
{
    /** @use HasFactory<\Database\Factories\LiveAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'creator_user_id',
        'nickname',
        'display_name',
        'normalized_handle',
        'avatar_url',
        'follower_count',
        'is_active',
        'needs_review',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'follower_count' => 'integer',
            'is_active' => 'boolean',
            'needs_review' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * The TikTok Shops this account is affiliated with (sells for).
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(PlatformAccount::class, 'live_account_shop', 'live_account_id', 'platform_account_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * The staff hosts eligible to operate this account.
     */
    public function hosts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'live_account_host', 'live_account_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(LiveScheduleAssignment::class, 'live_account_id');
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class, 'live_account_id');
    }

    /**
     * Best human label for the account: prefer the @handle, fall back to the
     * display name, then the creator id, then a generic placeholder.
     */
    public function getLabelAttribute(): string
    {
        return $this->nickname
            ?: $this->display_name
            ?: ($this->creator_user_id ? "Creator {$this->creator_user_id}" : 'Unknown account');
    }

    /**
     * Normalize a raw TikTok handle/nickname into a comparable key: trimmed,
     * lowercased, with a leading "@" stripped. Used for dedup and fallback
     * matching when the numeric creator id is missing from imported rows.
     */
    public static function normalizeHandle(?string $handle): ?string
    {
        if ($handle === null) {
            return null;
        }

        $normalized = strtolower(trim($handle));
        $normalized = ltrim($normalized, '@');
        $normalized = trim($normalized);

        return $normalized === '' ? null : $normalized;
    }
}
