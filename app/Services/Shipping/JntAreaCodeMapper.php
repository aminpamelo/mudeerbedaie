<?php

namespace App\Services\Shipping;

class JntAreaCodeMapper
{
    /**
     * Malaysian states to J&T state codes mapping.
     *
     * @var array<string, string>
     */
    private const STATE_CODES = [
        'Johor' => 'JHR',
        'Kedah' => 'KDH',
        'Kelantan' => 'KTN',
        'Melaka' => 'MLK',
        'Negeri Sembilan' => 'NSN',
        'Pahang' => 'PHG',
        'Perak' => 'PRK',
        'Perlis' => 'PLS',
        'Pulau Pinang' => 'PNG',
        'Sabah' => 'SBH',
        'Sarawak' => 'SWK',
        'Selangor' => 'SGR',
        'Terengganu' => 'TRG',
        'W.P. Kuala Lumpur' => 'KUL',
        'W.P. Labuan' => 'LBN',
        'W.P. Putrajaya' => 'PJY',
    ];

    /**
     * Get the J&T state code for a given Malaysian state name.
     */
    public static function getStateCode(string $state): string
    {
        return self::STATE_CODES[$state] ?? $state;
    }

    /**
     * Get all Malaysian states.
     *
     * @return array<string, string>
     */
    public static function getStates(): array
    {
        return self::STATE_CODES;
    }

    /**
     * Get state name from code.
     */
    public static function getStateName(string $code): ?string
    {
        $flipped = array_flip(self::STATE_CODES);

        return $flipped[$code] ?? null;
    }
}
