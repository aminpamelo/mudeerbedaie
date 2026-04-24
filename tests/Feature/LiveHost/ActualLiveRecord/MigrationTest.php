<?php

use Illuminate\Support\Facades\Schema;

it('creates actual_live_records table with expected columns', function () {
    expect(Schema::hasTable('actual_live_records'))->toBeTrue();

    $expected = [
        'id', 'platform_account_id', 'source', 'source_record_id',
        'import_id', 'creator_platform_user_id', 'creator_handle',
        'launched_time', 'ended_time', 'duration_seconds',
        'gmv_myr', 'live_attributed_gmv_myr',
        'viewers', 'views', 'comments', 'shares', 'likes', 'new_followers',
        'products_added', 'products_sold', 'items_sold', 'sku_orders',
        'unique_customers', 'avg_price_myr', 'click_to_order_rate', 'ctr',
        'raw_json', 'created_at', 'updated_at',
    ];

    foreach ($expected as $col) {
        expect(Schema::hasColumn('actual_live_records', $col))
            ->toBeTrue("missing column: {$col}");
    }
});
