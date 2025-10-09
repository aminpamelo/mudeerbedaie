<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\TikTokOrderProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessTikTokOrderImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout

    public $tries = 3; // Retry 3 times on failure

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importJobId,
        public int $platformId,
        public int $accountId,
        public array $fieldMapping,
        public array $productMappings,
        public int $batchSize = 50
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $importJob = ImportJob::findOrFail($this->importJobId);
        $platform = Platform::findOrFail($this->platformId);
        $account = PlatformAccount::findOrFail($this->accountId);

        try {
            $importJob->markAsStarted();

            // Load CSV data from file
            $csvData = $this->loadCsvData($importJob->file_path);
            $totalRows = count($csvData);

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            // Process in batches to avoid memory issues
            $chunks = array_chunk($csvData, $this->batchSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                foreach ($chunk as $rowIndex => $row) {
                    $globalIndex = ($chunkIndex * $this->batchSize) + $rowIndex;

                    try {
                        DB::transaction(function () use ($row, $platform, $account, &$imported, &$updated) {
                            $processor = new TikTokOrderProcessor(
                                $platform,
                                $account,
                                $this->fieldMapping,
                                $this->productMappings
                            );

                            $orderData = $this->extractOrderData($row);
                            $result = $processor->processOrderRow($orderData);

                            if ($result['product_order']->wasRecentlyCreated) {
                                $imported++;
                            } else {
                                $updated++;
                            }
                        });
                    } catch (\Exception $e) {
                        $skipped++;
                        $errorMessage = 'Row '.($globalIndex + 2).': '.$e->getMessage();
                        $errors[] = $errorMessage;
                        $importJob->addError($e->getMessage(), $globalIndex + 2);

                        Log::warning('TikTok import row failed', [
                            'import_job_id' => $this->importJobId,
                            'row' => $globalIndex + 2,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Update progress after each row
                    $progress = $globalIndex + 1;
                    $importJob->updateProgress($progress);
                }

                // Clear memory after each chunk
                unset($chunk);
                gc_collect_cycles();
            }

            // Update final results
            $importJob->update([
                'successful_rows' => $imported + $updated,
                'failed_rows' => $skipped,
                'metadata' => array_merge($importJob->metadata ?? [], [
                    'imported' => $imported,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => array_slice($errors, 0, 100), // Store max 100 errors
                ]),
            ]);

            $importJob->markAsCompleted();

            Log::info('TikTok import completed successfully', [
                'import_job_id' => $this->importJobId,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'total_rows' => $totalRows,
            ]);
        } catch (\Exception $e) {
            $importJob->markAsFailed($e->getMessage());

            Log::error('TikTok import job failed', [
                'import_job_id' => $this->importJobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Load CSV data from storage
     */
    protected function loadCsvData(string $filePath): array
    {
        $fullPath = Storage::path($filePath);

        if (! file_exists($fullPath)) {
            throw new \Exception("CSV file not found at: {$filePath}");
        }

        $fileContent = file_get_contents($fullPath);
        $lines = explode("\n", $fileContent);

        // Skip first row (header row) and parse data rows
        $csvData = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            // Skip empty rows
            if (! empty(array_filter($row))) {
                $csvData[] = $row;
            }
        }

        return $csvData;
    }

    /**
     * Extract order data from CSV row based on field mapping
     */
    protected function extractOrderData(array $row): array
    {
        $data = [];

        foreach ($this->fieldMapping as $field => $columnIndex) {
            if ($columnIndex !== '' && isset($row[$columnIndex])) {
                $data[$field] = trim($row[$columnIndex]);
            }
        }

        return $data;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if ($importJob) {
            $importJob->markAsFailed($exception->getMessage());
        }

        Log::error('TikTok import job failed permanently', [
            'import_job_id' => $this->importJobId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
