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

it('has alr_source_unique unique index on source and source_record_id', function () {
    $indexes = collect(Schema::getIndexes('actual_live_records'));
    $unique = $indexes->firstWhere('name', 'alr_source_unique');

    expect($unique)->not->toBeNull()
        ->and($unique['unique'])->toBeTrue()
        ->and($unique['columns'])->toBe(['source', 'source_record_id']);
});

it('has alr_candidate_idx composite index for the candidate-search query', function () {
    $indexes = collect(Schema::getIndexes('actual_live_records'));
    $candidate = $indexes->firstWhere('name', 'alr_candidate_idx');

    expect($candidate)->not->toBeNull()
        ->and($candidate['columns'])->toBe([
            'platform_account_id',
            'creator_platform_user_id',
            'launched_time',
        ]);
});

it('adds matched_actual_live_record_id column to live_sessions', function () {
    expect(Schema::hasColumn('live_sessions', 'matched_actual_live_record_id'))->toBeTrue();
});

it('enforces a unique constraint on live_sessions.matched_actual_live_record_id', function () {
    $indexes = collect(Schema::getIndexes('live_sessions'));

    $unique = $indexes->first(function (array $index) {
        return $index['unique']
            && $index['columns'] === ['matched_actual_live_record_id'];
    });

    expect($unique)->not->toBeNull();
});
