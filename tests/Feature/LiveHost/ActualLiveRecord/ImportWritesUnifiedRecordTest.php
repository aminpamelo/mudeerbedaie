<?php

use App\Jobs\ProcessTiktokImportJob;
use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use App\Models\TiktokReportImport;
use App\Models\User;
use App\Services\LiveHost\Tiktok\AllOrderXlsxParser;
use App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser;
use App\Services\LiveHost\Tiktok\LiveSessionMatcher;
use App\Services\LiveHost\Tiktok\OrderRefundReconciler;

it('creates actual_live_records row for each imported live-analysis row', function () {
    $account = PlatformAccount::factory()->create();
    $user = User::factory()->create();
    $import = TiktokReportImport::create([
        'platform_account_id' => $account->id,
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'report_type' => 'live_analysis',
        'file_path' => 'stub.xlsx',
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => 'pending',
    ]);

    // Stub the live-analysis parser so we don't need a real xlsx file
    $this->mock(LiveAnalysisXlsxParser::class, function ($mock) {
        $mock->shouldReceive('parse')->andReturn([
            [
                'tiktok_creator_id' => 'c1',
                'creator_nickname' => 'host_a',
                'launched_time' => now(),
                'duration_seconds' => 3600,
                'gmv_myr' => 500,
                'live_attributed_gmv_myr' => 400,
                'viewers' => 100,
                'raw_row_json' => [],
            ],
        ]);
    });

    (new ProcessTiktokImportJob($import->id))->handle(
        app(LiveAnalysisXlsxParser::class),
        app(AllOrderXlsxParser::class),
        app(LiveSessionMatcher::class),
        app(OrderRefundReconciler::class),
    );

    expect(ActualLiveRecord::where('import_id', $import->id)->count())->toBe(1);

    $record = ActualLiveRecord::where('import_id', $import->id)->first();
    expect($record->source)->toBe('csv_import')
        ->and($record->platform_account_id)->toBe($account->id)
        ->and($record->creator_platform_user_id)->toBe('c1')
        ->and((float) $record->gmv_myr)->toBe(500.0)
        ->and((float) $record->live_attributed_gmv_myr)->toBe(400.0);
});
