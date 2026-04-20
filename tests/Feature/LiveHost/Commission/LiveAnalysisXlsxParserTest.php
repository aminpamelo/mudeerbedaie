<?php

use App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser;
use Carbon\Carbon;

it('parses a fixture xlsx into typed rows', function () {
    $parser = new LiveAnalysisXlsxParser;
    $rows = $parser->parse(base_path('tests/Fixtures/tiktok/live_analysis_sample.xlsx'));

    expect($rows)->toHaveCount(2);

    $first = $rows[0];

    expect($first['tiktok_creator_id'])->toBe('6526684195492729856');
    expect($first['creator_display_name'])->toBe('BeDaie Ustaz Amar');
    expect($first['creator_nickname'])->toBe('amarmirzabedaie');
    expect((float) $first['gmv_myr'])->toEqual(444.23);
    expect((float) $first['live_attributed_gmv_myr'])->toEqual(444.23);
    expect($first['launched_time'])->toBeInstanceOf(Carbon::class);
    expect($first['launched_time']->format('Y-m-d H:i'))->toBe('2026-04-18 22:14');
    expect($first['duration_seconds'])->toEqual(6000); // 1h 40min
    expect($first['click_to_order_rate'])->toEqual(8.33);
    expect($first['ctr'])->toEqual(2.02);
    expect($first['viewers'])->toBe(14254);
    expect($first['views'])->toBe(16652);
    expect($first['avg_view_duration_sec'])->toBe(107);
    expect($first['comments'])->toBe(193);
    expect($first['shares'])->toBe(81);
    expect($first['likes'])->toBe(5100);
    expect($first['new_followers'])->toBe(12);
    expect($first['product_impressions'])->toBe(1778);
    expect($first['product_clicks'])->toBe(36);
    expect($first['products_added'])->toBe(3);
    expect($first['products_sold'])->toBe(1);
    expect($first['sku_orders'])->toBe(3);
    expect($first['items_sold'])->toBe(3);
    expect($first['unique_customers'])->toBe(3);
    expect((float) $first['avg_price_myr'])->toEqual(148.08);

    expect($first['raw_row_json'])->toBeArray();
    expect($first['raw_row_json'])->toHaveKey('Creator ID');
    expect($first['raw_row_json']['CTR'])->toBe('2.02%');
});

it('parses a second row with zero gmv correctly', function () {
    $parser = new LiveAnalysisXlsxParser;
    $rows = $parser->parse(base_path('tests/Fixtures/tiktok/live_analysis_sample.xlsx'));

    $second = $rows[1];
    expect($second['tiktok_creator_id'])->toBe('6512620107667226626');
    expect($second['gmv_myr'])->toEqual(0.0);
    expect($second['click_to_order_rate'])->toEqual(0.0);
    expect($second['duration_seconds'])->toEqual(5040); // 1h 24min
});

it('returns empty array when header row cannot be found', function () {
    $parser = new LiveAnalysisXlsxParser;

    // Create a temp xlsx with no "Creator ID" header
    $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $ss->getActiveSheet()->setCellValue('A1', 'Nothing to see here');
    $tmp = tempnam(sys_get_temp_dir(), 'notiktok_').'.xlsx';
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($ss, 'Xlsx');
    $writer->save($tmp);

    try {
        $rows = $parser->parse($tmp);
        expect($rows)->toBe([]);
    } finally {
        @unlink($tmp);
    }
});
