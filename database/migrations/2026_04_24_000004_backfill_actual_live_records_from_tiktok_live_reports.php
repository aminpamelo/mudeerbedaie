<?php

use App\Models\ActualLiveRecord;
use App\Models\TiktokLiveReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
                        'views' => $r->views,
                        'comments' => $r->comments,
                        'shares' => $r->shares,
                        'likes' => $r->likes,
                        'new_followers' => $r->new_followers,
                        'products_added' => $r->products_added,
                        'products_sold' => $r->products_sold,
                        'items_sold' => $r->items_sold,
                        'sku_orders' => $r->sku_orders,
                        'unique_customers' => $r->unique_customers,
                        'avg_price_myr' => $r->avg_price_myr,
                        'click_to_order_rate' => $r->click_to_order_rate,
                        'ctr' => $r->ctr,
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
    }

    public function down(): void
    {
        DB::table('actual_live_records')->where('source', 'csv_import')->delete();
    }
};
