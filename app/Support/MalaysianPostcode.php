<?php

namespace App\Support;

/**
 * Best-effort resolver for the state (and a likely city) of a Malaysian
 * address when only a postcode and/or a free-text address string is on file.
 *
 * Postcode → state is deterministic in Malaysia (well-defined numeric ranges,
 * plus a few scattered highland/resort exceptions) so `state()` is reliable.
 * `city()` is a heuristic extraction from the address text — the postcode still
 * drives courier routing, so a slightly-off city label is low risk; callers
 * should treat it as a fill-in the admin can correct, not authoritative.
 */
class MalaysianPostcode
{
    public static function state(?string $postcode): ?string
    {
        $p = (int) preg_replace('/\D/', '', (string) $postcode);

        if ($p < 1000 || $p > 98999) {
            return null;
        }

        // Scattered exceptions that fall outside the main contiguous ranges.
        $exceptions = [
            49000 => 'Pahang', // Bukit Fraser
            69000 => 'Pahang', // Genting Highlands
        ];

        if (isset($exceptions[$p])) {
            return $exceptions[$p];
        }

        return match (true) {
            $p >= 1000 && $p <= 2800 => 'Perlis',
            $p >= 5000 && $p <= 9810 => 'Kedah',
            $p >= 10000 && $p <= 14400 => 'Penang',
            $p >= 15000 && $p <= 18500 => 'Kelantan',
            $p >= 20000 && $p <= 24300 => 'Terengganu',
            $p >= 25000 && $p <= 28800 => 'Pahang',
            $p >= 30000 && $p <= 36810 => 'Perak',
            $p >= 39000 && $p <= 39200 => 'Pahang', // Cameron Highlands
            $p >= 40000 && $p <= 48300 => 'Selangor',
            $p >= 50000 && $p <= 60000 => 'Kuala Lumpur',
            $p >= 62000 && $p <= 62988 => 'Putrajaya',
            $p >= 63000 && $p <= 68100 => 'Selangor',
            $p >= 70000 && $p <= 73509 => 'Negeri Sembilan',
            $p >= 75000 && $p <= 78309 => 'Melaka',
            $p >= 79000 && $p <= 86900 => 'Johor',
            $p >= 87000 && $p <= 87033 => 'Labuan',
            $p >= 88000 && $p <= 91309 => 'Sabah',
            $p >= 93000 && $p <= 98859 => 'Sarawak',
            default => null,
        };
    }

    /**
     * Pull the most likely city/town out of a free-text Malaysian address after
     * removing the postcode and state. Returns null when nothing plausible
     * remains.
     */
    public static function city(?string $addressText, ?string $state = null, ?string $postcode = null): ?string
    {
        $text = trim((string) $addressText);

        if ($text === '') {
            return null;
        }

        if (filled($postcode)) {
            $text = preg_replace('/(?<!\d)'.preg_quote((string) $postcode, '/').'(?!\d)/', '', $text);
        }

        if (filled($state)) {
            $text = preg_replace('/\b'.preg_quote((string) $state, '/').'\b/i', '', (string) $text);
        }

        $segments = array_values(array_filter(
            array_map('trim', explode(',', (string) $text)),
            fn ($segment) => $segment !== ''
        ));

        if (empty($segments)) {
            return null;
        }

        $candidate = end($segments);

        // A street line (e.g. "Seksyen 26 Jalan Tok Guru Kota Bharu") — the town
        // is usually the trailing word or two after the street name.
        if (preg_match('/\b(jalan|jln|lorong|lrg|lot|no|taman|tmn|kampung|kg|kampong|seksyen|persiaran|blok|tingkat|apartment|apartmen)\b/i', $candidate)) {
            $words = preg_split('/\s+/', $candidate) ?: [];
            $candidate = trim(implode(' ', array_slice($words, -2)));
        }

        return $candidate !== '' ? $candidate : null;
    }
}
