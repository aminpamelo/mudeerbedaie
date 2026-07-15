<?php

namespace App\Services\Fighter;

use App\Models\Funnel;
use App\Models\SalesSource;
use App\Models\User;

class FighterProvisioner
{
    /**
     * The badge colour used for auto-created fighter sales-source segments.
     */
    private const SEGMENT_COLOR = '#F97316';

    /**
     * Ensure the fighter has a dedicated sales-source segment and is linked to it.
     *
     * Idempotent: returns the existing segment when one is already attached,
     * otherwise creates a "Fighter: {name}" sales source and links it on the user.
     */
    public function ensureSalesSource(User $fighter): SalesSource
    {
        if ($fighter->sales_source_id && ($existing = $fighter->salesSource)) {
            return $existing;
        }

        $source = SalesSource::create([
            'name' => $this->segmentName($fighter),
            'description' => 'Auto-created segment for fighter '.$fighter->name.' (user #'.$fighter->id.').',
            'color' => self::SEGMENT_COLOR,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $fighter->forceFill(['sales_source_id' => $source->id])->save();
        $fighter->setRelation('salesSource', $source);

        return $source;
    }

    /**
     * Order attributes derived from funnel ownership.
     *
     * A funnel owned by a Fighter tags its orders with the fighter's segment
     * and forces them visible to the internal e-commerce team. Any other funnel
     * keeps the existing behaviour (no segment; visibility from the funnel's
     * own setting).
     *
     * @return array{sales_source_id: int|null, hidden_from_admin: bool}
     */
    public function orderTaggingFor(Funnel $funnel): array
    {
        $owner = $funnel->user;

        if ($owner && $owner->isFighter()) {
            return [
                'sales_source_id' => $this->ensureSalesSource($owner)->id,
                'hidden_from_admin' => false,
            ];
        }

        return [
            'sales_source_id' => null,
            'hidden_from_admin' => ! $funnel->shouldShowOrdersInAdmin(),
        ];
    }

    private function segmentName(User $fighter): string
    {
        return 'Fighter: '.$fighter->name;
    }
}
