<?php

declare(strict_types=1);

namespace App\Services\LiveHost;

use App\Models\LiveAccount;
use App\Models\LiveHostPlatformAccount;

/**
 * Resolves the various legacy/imported creator identities onto the canonical
 * LiveAccount entity. Prefers the stable numeric Creator ID, falls back to a
 * normalized handle, and as a last resort infers the account from a unique
 * (host, shop) pairing. Returns null whenever the resolution is ambiguous so
 * callers can flag the row for manual review rather than guess.
 */
class LiveAccountResolver
{
    public function fromPivot(?LiveHostPlatformAccount $pivot): ?LiveAccount
    {
        if ($pivot === null) {
            return null;
        }

        return $this->fromCreatorId($pivot->creator_platform_user_id)
            ?? $this->fromHandle($pivot->creator_handle);
    }

    public function fromCreatorId(?string $creatorUserId): ?LiveAccount
    {
        $creatorUserId = $creatorUserId !== null ? trim($creatorUserId) : null;
        if ($creatorUserId === null || $creatorUserId === '') {
            return null;
        }

        return LiveAccount::query()->where('creator_user_id', $creatorUserId)->first();
    }

    public function fromHandle(?string $handle): ?LiveAccount
    {
        $normalized = LiveAccount::normalizeHandle($handle);
        if ($normalized === null) {
            return null;
        }

        return LiveAccount::query()->where('normalized_handle', $normalized)->first();
    }

    /**
     * The single live account linked to BOTH this host and this shop, or null
     * when zero or more than one candidate exists (ambiguous).
     */
    public function fromHostAndShop(?int $hostId, ?int $shopId): ?LiveAccount
    {
        if ($hostId === null || $shopId === null) {
            return null;
        }

        $candidates = LiveAccount::query()
            ->whereHas('hosts', fn ($q) => $q->where('users.id', $hostId))
            ->whereHas('shops', fn ($q) => $q->where('platform_accounts.id', $shopId))
            ->limit(2)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
