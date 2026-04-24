<?php

namespace App\Jobs;

use App\Models\ActualLiveRecord;
use App\Models\TiktokLiveReport;
use App\Models\TiktokOrder;
use App\Models\TiktokReportImport;
use App\Services\LiveHost\Tiktok\AllOrderXlsxParser;
use App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser;
use App\Services\LiveHost\Tiktok\LiveSessionMatcher;
use App\Services\LiveHost\Tiktok\OrderRefundReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Parses an uploaded TikTok export xlsx (Live Analysis or All Order) and
 * persists one child row per xlsx row, attempting a LiveSession match on the
 * Live Analysis branch so the PIC Apply step has pre-populated candidates.
 *
 * Lifecycle:
 *   pending   → (job start)     processing
 *   processing → (job success)  completed (with total/matched/unmatched counts)
 *   processing → (job failure)  failed   (with error_log_json, exception rethrown)
 *
 * Already-completed / already-failed imports short-circuit so reruns don't
 * double-insert rows. Matching for order_list is intentionally deferred to a
 * separate service — we only store raw order rows here.
 */
class ProcessTiktokImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $importId) {}

    public function handle(
        LiveAnalysisXlsxParser $liveAnalysisParser,
        AllOrderXlsxParser $allOrderParser,
        LiveSessionMatcher $matcher,
        OrderRefundReconciler $reconciler,
    ): void {
        /** @var TiktokReportImport|null $import */
        $import = TiktokReportImport::query()->find($this->importId);

        if ($import === null) {
            return;
        }

        if (in_array($import->status, ['completed', 'failed'], true)) {
            return;
        }

        $import->status = 'processing';
        $import->save();

        try {
            $absolutePath = Storage::disk('local')->path($import->file_path);

            if ($import->report_type === 'live_analysis') {
                $this->processLiveAnalysis($import, $liveAnalysisParser, $matcher, $absolutePath);
            } else {
                $this->processOrderList($import, $allOrderParser, $absolutePath);
                $reconciler->reconcile($import);
            }
        } catch (Throwable $e) {
            $import->status = 'failed';
            $import->error_log_json = [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ];
            $import->save();

            throw $e;
        }
    }

    private function processLiveAnalysis(
        TiktokReportImport $import,
        LiveAnalysisXlsxParser $parser,
        LiveSessionMatcher $matcher,
        string $absolutePath,
    ): void {
        $rows = $parser->parse($absolutePath);

        $total = 0;
        $matched = 0;

        foreach ($rows as $row) {
            $total++;

            /** @var TiktokLiveReport $report */
            $report = TiktokLiveReport::create(array_merge(
                $row,
                ['import_id' => $import->id]
            ));

            ActualLiveRecord::create([
                'platform_account_id' => $import->platform_account_id,
                'source' => 'csv_import',
                'source_record_id' => null,
                'import_id' => $import->id,
                'creator_platform_user_id' => $row['tiktok_creator_id'] ?? null,
                'creator_handle' => $row['creator_nickname'] ?? $row['creator_display_name'] ?? null,
                'launched_time' => $row['launched_time'],
                'duration_seconds' => $row['duration_seconds'] ?? null,
                'gmv_myr' => $row['gmv_myr'] ?? 0,
                'live_attributed_gmv_myr' => $row['live_attributed_gmv_myr'] ?? 0,
                'viewers' => $row['viewers'] ?? null,
                'views' => $row['views'] ?? null,
                'comments' => $row['comments'] ?? null,
                'shares' => $row['shares'] ?? null,
                'likes' => $row['likes'] ?? null,
                'new_followers' => $row['new_followers'] ?? null,
                'products_added' => $row['products_added'] ?? null,
                'products_sold' => $row['products_sold'] ?? null,
                'items_sold' => $row['items_sold'] ?? null,
                'sku_orders' => $row['sku_orders'] ?? null,
                'unique_customers' => $row['unique_customers'] ?? null,
                'avg_price_myr' => $row['avg_price_myr'] ?? null,
                'click_to_order_rate' => $row['click_to_order_rate'] ?? null,
                'ctr' => $row['ctr'] ?? null,
                'raw_json' => $row['raw_row_json'] ?? [],
            ]);

            $session = $matcher->match($report, $import->platform_account_id);
            if ($session !== null) {
                $report->matched_live_session_id = $session->id;
                $report->save();
                $matched++;
            }
        }

        $import->status = 'completed';
        $import->total_rows = $total;
        $import->matched_rows = $matched;
        $import->unmatched_rows = $total - $matched;
        $import->save();
    }

    private function processOrderList(
        TiktokReportImport $import,
        AllOrderXlsxParser $parser,
        string $absolutePath,
    ): void {
        $rows = $parser->parse($absolutePath);

        $total = 0;

        foreach ($rows as $row) {
            $total++;

            TiktokOrder::create(array_merge(
                $row,
                ['import_id' => $import->id]
            ));
        }

        $import->status = 'completed';
        $import->total_rows = $total;
        $import->matched_rows = 0;
        $import->unmatched_rows = $total;
        $import->save();
    }
}
