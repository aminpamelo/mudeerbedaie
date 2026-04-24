<?php

use Illuminate\Support\Facades\Schema;

it('has the expected columns', function () {
    expect(Schema::hasTable('live_host_platform_commission_tiers'))->toBeTrue();

    $columns = [
        'id', 'user_id', 'platform_id', 'tier_number',
        'min_gmv_myr', 'max_gmv_myr',
        'internal_percent', 'l1_percent', 'l2_percent',
        'effective_from', 'effective_to', 'is_active',
        'created_at', 'updated_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('live_host_platform_commission_tiers', $column))
            ->toBeTrue("missing column: {$column}");
    }
});

it('enforces the tier uniqueness index', function () {
    $indexes = collect(Schema::getIndexes('live_host_platform_commission_tiers'))
        ->pluck('name');

    expect($indexes)->toContain('lh_tier_unique');
});
