<?php

namespace App\Services\Shipping;

use Illuminate\Support\Str;

/**
 * Maps Malaysian state names to the short state codes EasyParcel expects for the
 * `pick_state` / `send_state` fields. Accepts common English and Malay spellings;
 * if a value is already a valid code (or unrecognised) it is passed through.
 */
class EasyParcelStateMapper
{
    /** @var array<string, string> */
    private const CODES = [
        'johor' => 'jhr',
        'kedah' => 'kdh',
        'kelantan' => 'ktn',
        'melaka' => 'mlk',
        'malacca' => 'mlk',
        'negeri sembilan' => 'nsn',
        'pahang' => 'phg',
        'penang' => 'png',
        'pulau pinang' => 'png',
        'perak' => 'prk',
        'perlis' => 'pls',
        'selangor' => 'sgr',
        'terengganu' => 'trg',
        'sabah' => 'sbh',
        'sarawak' => 'swk',
        'kuala lumpur' => 'kul',
        'wp kuala lumpur' => 'kul',
        'labuan' => 'lbn',
        'wp labuan' => 'lbn',
        'putrajaya' => 'pjy',
        'wp putrajaya' => 'pjy',
    ];

    /** Valid EasyParcel state codes, so an already-coded value passes through. */
    private const VALID_CODES = [
        'jhr', 'kdh', 'ktn', 'mlk', 'nsn', 'phg', 'png', 'prk',
        'pls', 'sgr', 'trg', 'sbh', 'swk', 'kul', 'lbn', 'pjy',
    ];

    public static function getStateCode(?string $state): string
    {
        $normalized = Str::of((string) $state)
            ->lower()
            ->replace(['wilayah persekutuan', 'w.p.', 'w.p'], '')
            ->squish()
            ->value();

        if ($normalized === '') {
            return '';
        }

        if (in_array($normalized, self::VALID_CODES, true)) {
            return $normalized;
        }

        return self::CODES[$normalized] ?? $normalized;
    }
}
