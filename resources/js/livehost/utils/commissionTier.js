/**
 * Client-side mirror of App\Services\LiveHost\CommissionTierResolver.
 *
 * The server resolver queries by (host, platform, GMV, asOf). The UI projection
 * slider is already scoped to a specific platform tier group, so this helper
 * takes a `tiers` array (one platform's ladder) plus a GMV value and returns
 * the matching tier, or `null` when GMV is below the lowest floor.
 *
 * Boundaries match the server: min_gmv_myr is inclusive, max_gmv_myr is
 * exclusive, and a null/undefined max_gmv_myr denotes an open-ended top tier.
 */
export function resolveTier(tiers, gmv) {
    if (!Array.isArray(tiers) || tiers.length === 0) {
        return null;
    }

    const amount = Number(gmv) || 0;

    // Sort tiers so the highest tier_number wins when ranges overlap, mirroring
    // the server's `orderByDesc('tier_number')->first()` tiebreaker.
    const sorted = [...tiers].sort(
        (a, b) => Number(b.tier_number ?? 0) - Number(a.tier_number ?? 0),
    );

    for (const tier of sorted) {
        const min = Number(tier.min_gmv_myr ?? 0);
        const rawMax = tier.max_gmv_myr;
        const hasMax = rawMax !== null && rawMax !== undefined && rawMax !== '';
        const max = hasMax ? Number(rawMax) : Infinity;

        if (amount >= min && amount < max) {
            return tier;
        }
    }

    return null;
}

/**
 * Format a tier's GMV range for display, e.g. "60K–100K" or "150K+" for an
 * open-ended top tier. Uses K/M suffixes for readability in the projection
 * badge.
 */
export function formatTierRange(tier) {
    if (!tier) {
        return '—';
    }

    const min = Number(tier.min_gmv_myr ?? 0);
    const rawMax = tier.max_gmv_myr;
    const hasMax = rawMax !== null && rawMax !== undefined && rawMax !== '';

    if (!hasMax) {
        return `${compactNumber(min)}+`;
    }

    return `${compactNumber(min)}–${compactNumber(Number(rawMax))}`;
}

function compactNumber(value) {
    if (value >= 1_000_000) {
        const millions = value / 1_000_000;
        return `${trimZeros(millions)}M`;
    }
    if (value >= 1_000) {
        const thousands = value / 1_000;
        return `${trimZeros(thousands)}K`;
    }
    return String(Math.round(value));
}

function trimZeros(num) {
    // 60 → "60", 60.5 → "60.5", 60.00 → "60"
    const rounded = Math.round(num * 10) / 10;
    return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
}
