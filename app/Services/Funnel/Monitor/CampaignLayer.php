<?php

namespace App\Services\Funnel\Monitor;

/**
 * Which funnel layer a Meta campaign belongs to, so the monitor judges Sales campaigns on
 * their own numbers instead of a blend distorted by cheap engagement/traffic delivery.
 * Objective decides; rows without one fall back to naming conventions (ACR tags [C]/[R]/[A],
 * _SALES_, TOFU/BOFU…). Ported from Dana Ummah's Monitor Klien.
 */
final class CampaignLayer
{
    public const SALES = 'sales';

    public const ENGAGEMENT = 'engagement';

    public const TRAFFIC = 'traffic';

    public const AWARENESS = 'awareness';

    public const LEADS = 'leads';

    public const OTHER = 'lain';

    /** Layers that support Sales rather than ask for the sale themselves. */
    public const SUPPORT = [self::ENGAGEMENT, self::TRAFFIC, self::AWARENESS];

    public const ROLE_COLD = 'C — Cold Sales';

    public const ROLE_RETARGET = 'R — Retarget';

    public const ROLE_AWARENESS = 'A — Awareness/Engagement';

    private const OBJECTIVES = [
        'OUTCOME_SALES' => self::SALES,
        'CONVERSIONS' => self::SALES,
        'OFFSITE_CONVERSIONS' => self::SALES,
        'PRODUCT_CATALOG_SALES' => self::SALES,
        'OUTCOME_ENGAGEMENT' => self::ENGAGEMENT,
        'POST_ENGAGEMENT' => self::ENGAGEMENT,
        'VIDEO_VIEWS' => self::ENGAGEMENT,
        'PAGE_LIKES' => self::ENGAGEMENT,
        'EVENT_RESPONSES' => self::ENGAGEMENT,
        'MESSAGES' => self::ENGAGEMENT,
        'OUTCOME_TRAFFIC' => self::TRAFFIC,
        'LINK_CLICKS' => self::TRAFFIC,
        'OUTCOME_AWARENESS' => self::AWARENESS,
        'REACH' => self::AWARENESS,
        'BRAND_AWARENESS' => self::AWARENESS,
        'OUTCOME_LEADS' => self::LEADS,
        'LEAD_GENERATION' => self::LEADS,
    ];

    private const LABELS = [
        self::SALES => 'Sales',
        self::ENGAGEMENT => 'Engagement',
        self::TRAFFIC => 'Traffic',
        self::AWARENESS => 'Awareness',
        self::LEADS => 'Leads',
        self::OTHER => 'Lain',
    ];

    public static function normaliseObjective(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z_]{1,40}$/', $value) === 1 ? $value : null;
    }

    /**
     * @return array{layer: string, label: string, role: string|null}
     */
    public static function classify(?string $objective, string $campaignName): array
    {
        $name = strtoupper($campaignName);
        $objective = self::normaliseObjective($objective);

        $layer = $objective !== null
            ? (self::OBJECTIVES[$objective] ?? self::OTHER)
            : self::fromName($name);

        return [
            'layer' => $layer,
            'label' => self::LABELS[$layer],
            'role' => self::role($layer, $name),
        ];
    }

    private static function fromName(string $name): string
    {
        return match (true) {
            str_contains($name, '[C]'), str_contains($name, '[R]'),
            self::hasWord($name, 'SALES'), self::hasWord($name, 'CONVERSION'), self::hasWord($name, 'CONVERSIONS'),
            self::hasWord($name, 'TOFU'), self::hasWord($name, 'BOFU') => self::SALES,
            self::hasWord($name, 'ENGAGEMENT') => self::ENGAGEMENT,
            self::hasWord($name, 'TRAFFIC'), self::hasWord($name, 'LPV') => self::TRAFFIC,
            self::hasWord($name, 'LEADS'), self::hasWord($name, 'LEAD') => self::LEADS,
            str_contains($name, '[A]'), self::hasWord($name, 'AWARENESS'), self::hasWord($name, 'REACH') => self::AWARENESS,
            default => self::OTHER,
        };
    }

    private static function role(string $layer, string $name): ?string
    {
        $retarget = str_contains($name, '[R]') || self::hasWord($name, 'BOFU') || str_contains($name, 'RETARGET');
        $acr = $retarget
            || str_contains($name, '[C]') || str_contains($name, '[A]')
            || self::hasWord($name, 'TOFU') || self::hasWord($name, 'MOFU');

        if (! $acr) {
            return null;
        }

        return match (true) {
            $layer === self::SALES => $retarget ? self::ROLE_RETARGET : self::ROLE_COLD,
            in_array($layer, self::SUPPORT, true) => self::ROLE_AWARENESS,
            default => null,
        };
    }

    private static function hasWord(string $name, string $word): bool
    {
        return preg_match('/(?<![A-Z0-9])'.$word.'(?![A-Z0-9])/', $name) === 1;
    }

    /** Angle id at the start of an ad name: "[C] A4_IMG_…" → "A4". */
    public static function creativeKey(string $name): string
    {
        $name = trim((string) preg_replace('/^\s*\[[^\]]*\]\s*/', '', $name));

        return mb_strtoupper(trim(explode('_', $name, 2)[0]));
    }
}
