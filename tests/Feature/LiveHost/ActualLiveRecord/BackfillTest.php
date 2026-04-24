<?php

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Models\TiktokReportImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('is idempotent and copies tiktok_live_reports into actual_live_records', function () {
    expect(ActualLiveRecord::count())->toBe(0);

    $pic = User::factory()->create();
    $account = PlatformAccount::factory()->create();
    $import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'platform_account_id' => $account->id,
        'file_path' => 'tiktok-imports/test.xlsx',
        'uploaded_by' => $pic->id,
        'uploaded_at' => now(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'status' => 'completed',
    ]);

    TiktokLiveReport::create([
        'import_id' => $import->id,
        'tiktok_creator_id' => '123456789',
        'creator_nickname' => 'host_a',
        'launched_time' => now()->subHour(),
        'duration_seconds' => 3600,
        'gmv_myr' => 1500.00,
        'live_attributed_gmv_myr' => 1200.00,
        'viewers' => 250,
        'raw_row_json' => ['source' => 'test'],
    ]);

    $runBackfill = function (): void {
        if (ActualLiveRecord::where('source', 'csv_import')->exists()) {
            return;
        }

        TiktokLiveReport::query()
            ->with('import:id,platform_account_id')
            ->chunkById(500, function ($reports) {
                $rows = $reports->map(function (TiktokLiveReport $r) {
                    return [
                        'platform_account_id' => $r->import?->platform_account_id,
                        'source' => 'csv_import',
                        'source_record_id' => null,
                        'import_id' => $r->import_id,
                        'creator_platform_user_id' => $r->tiktok_creator_id,
                        'creator_handle' => $r->creator_nickname ?? $r->creator_display_name,
                        'launched_time' => $r->launched_time,
                        'duration_seconds' => $r->duration_seconds,
                        'gmv_myr' => $r->gmv_myr ?? 0,
                        'live_attributed_gmv_myr' => $r->live_attributed_gmv_myr ?? 0,
                        'viewers' => $r->viewers,
                        'raw_json' => json_encode($r->raw_row_json ?? []),
                        'created_at' => $r->created_at,
                        'updated_at' => $r->updated_at,
                    ];
                })
                    ->filter(fn ($row) => $row['platform_account_id'] !== null && $row['launched_time'] !== null)
                    ->values()
                    ->all();

                if (! empty($rows)) {
                    DB::table('actual_live_records')->insert($rows);
                }
            });
    };

    $runBackfill();

    expect(ActualLiveRecord::count())->toBe(TiktokLiveReport::count());

    $runBackfill();
    expect(ActualLiveRecord::count())->toBe(TiktokLiveReport::count());
});
