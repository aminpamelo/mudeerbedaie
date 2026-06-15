<?php

namespace App\Services\Shipping;

use Illuminate\Support\Str;

/**
 * Maps Malaysian state names to the ISO 3166-2 subdivision codes the EasyParcel
 * Open API expects for `sender.subdivision_code` / `receiver.subdivision_code`
 * (e.g. "MY-07" for Penang, "MY-10" for Selangor). Accepts common English and
 * Malay spellings; an already-valid code (or unknown value) passes through.
 */
class EasyParcelStateMapper
{
    /** @var array<string, string> ISO 3166-2:MY */
    private const CODES = [
        'johor' => 'MY-01',
        'kedah' => 'MY-02',
        'kelantan' => 'MY-03',
        'melaka' => 'MY-04',
        'malacca' => 'MY-04',
        'negeri sembilan' => 'MY-05',
        'pahang' => 'MY-06',
        'penang' => 'MY-07',
        'pulau pinang' => 'MY-07',
        'perak' => 'MY-08',
        'perlis' => 'MY-09',
        'selangor' => 'MY-10',
        'terengganu' => 'MY-11',
        'sabah' => 'MY-12',
        'sarawak' => 'MY-13',
        'kuala lumpur' => 'MY-14',
        'wp kuala lumpur' => 'MY-14',
        'labuan' => 'MY-15',
        'wp labuan' => 'MY-15',
        'putrajaya' => 'MY-16',
        'wp putrajaya' => 'MY-16',
    ];

    public static function getSubdivisionCode(?string $state): string
    {
        $value = (string) $state;

        if (preg_match('/^MY-\d{2}$/i', trim($value))) {
            return strtoupper(trim($value));
        }

        $normalized = Str::of($value)
            ->lower()
            ->replace(['wilayah persekutuan', 'w.p.', 'w.p'], '')
            ->squish()
            ->value();

        return self::CODES[$normalized] ?? '';
    }
}
