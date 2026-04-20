<?php

namespace App\Jobs;

use App\Models\TiktokLiveReport;
use App\Models\TiktokOrder;
use App\Models\TiktokReportImport;
use App\Services\LiveHost\Tiktok\AllOrderXlsxParser;
use App\Services\LiveHost\Tiktok\LiveAnalysisXlsxParser;
use App\Services\LiveHost\Tiktok\LiveSessionMatcher;
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

            $session = $matcher->match($report);
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
