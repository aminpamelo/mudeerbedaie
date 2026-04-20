<?php

use App\Services\LiveHost\Tiktok\AllOrderXlsxParser;
use Carbon\Carbon;

it('parses a fixture xlsx into typed rows', function () {
    $parser = new AllOrderXlsxParser;
    $rows = $parser->parse(base_path('tests/Fixtures/tiktok/all_order_sample.xlsx'));

    expect($rows)->toHaveCount(3);
});

it('parses a shipped order with no refund', function () {
    $parser = new AllOrderXlsxParser;
    $rows = $parser->parse(base_path('tests/Fixtures/tiktok/all_order_sample.xlsx'));

    $shipped = $rows[0];

    expect($shipped['tiktok_order_id'])->toBe('583591642362381385');
    expect($shipped['order_status'])->toBe('Shipped');
    expect($shipped['order_substatus'])->toBe('In transit');
    expect($shipped['cancelation_return_type'])->toBeNull();
    expect($shipped['created_time'])->toBeInstanceOf(Carbon::class);
    expect($shipped['created_time']->format('Y-m-d H:i:s'))->toBe('2026-04-18 23:45:00');
    expect($shipped['rts_time']->format('Y-m-d H:i:s'))->toBe('2026-04-19 09:00:58');
    expect($shipped['shipped_time']->format('Y-m-d H:i:s'))->toBe('2026-04-19 12:00:11');
    expect($shipped['paid_time'])->toBeNull();
    expect($shipped['delivered_time'])->toBeNull();
    expect($shipped['cancelled_time'])->toBeNull();
    expect($shipped['order_amount_myr'])->toEqual(148.99);
    expect($shipped['order_refund_amount_myr'])->toEqual(0.0);
    expect($shipped['payment_method'])->toBe('Cash on delivery');
    expect($shipped['fulfillment_type'])->toBe('Fulfillment by seller');
    expect($shipped['product_category'])->toBe('Religion & Philosophy');

    expect($shipped['raw_row_json'])->toBeArray();
    expect($shipped['raw_row_json'])->toHaveKey('Order ID');
    expect($shipped['raw_row_json']['Tracking ID'])->toBe('680009101761417');
});

it('parses a cancelled order with full refund', function () {
    $parser = new AllOrderXlsxParser;
    $rows = $parser->parse(base_path('tests/Fixtures/tiktok/all_order_sample.xlsx'));

    $cancelled = $rows[1];

    expect($cancelled['tiktok_order_id'])->toBe('583591700000000001');
    expect($cancelled['order_status'])->toBe('Cancelled');
    expect($cancelled['cancelation_return_type'])->toBe('Cancellation');
    expect($cancelled['cancelled_time'])->toBeInstanceOf(Carbon::class);
    expect($cancelled['cancelled_time']->format('Y-m-d H:i:s'))->toBe('2026-04-17 14:30:00');
    expect($cancelled['order_amount_myr'])->toEqual(80.0);
    expect($cancelled['order_refund_amount_myr'])->toEqual(80.0);
    expect($cancelled['shipped_time'])->toBeNull();
    expect($cancelled['delivered_time'])->toBeNull();
    expect($cancelled['payment_method'])->toBe('Credit card');
});

it('parses a delivered order with partial refund', function () {
    $parser = new AllOrderXlsxParser;
    $rows = $parser->parse(base_path('tests/Fixtures/tiktok/all_order_sample.xlsx'));

    $delivered = $rows[2];

    expect($delivered['tiktok_order_id'])->toBe('583591700000000002');
    expect($delivered['order_status'])->toBe('Delivered');
    expect($delivered['cancelation_return_type'])->toBe('Return');
    expect($delivered['delivered_time'])->toBeInstanceOf(Carbon::class);
    expect($delivered['delivered_time']->format('Y-m-d H:i:s'))->toBe('2026-04-17 11:00:00');
    expect($delivered['order_amount_myr'])->toEqual(143.0);
    expect($delivered['order_refund_amount_myr'])->toEqual(70.0);
    expect($delivered['order_refund_amount_myr'])->toBeLessThan($delivered['order_amount_myr']);
    expect($delivered['order_refund_amount_myr'])->toBeGreaterThan(0);
    expect($delivered['payment_method'])->toBe('Online banking');
});

it('returns empty array when header row cannot be found', function () {
    $parser = new AllOrderXlsxParser;

    $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $ss->getActiveSheet()->setCellValue('A1', 'Nothing to see here');
    $tmp = tempnam(sys_get_temp_dir(), 'noorder_').'.xlsx';
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($ss, 'Xlsx');
    $writer->save($tmp);

    try {
        $rows = $parser->parse($tmp);
        expect($rows)->toBe([]);
    } finally {
        @unlink($tmp);
    }
});
