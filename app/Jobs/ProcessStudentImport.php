<?php

namespace App\Jobs;

use App\Models\StudentImportProgress;
use App\Services\StudentImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessStudentImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $importProgressId) {}

    public function handle(): void
    {
        $progress = StudentImportProgress::find($this->importProgressId);

        if (! $progress) {
            Log::error("Student import progress not found: {$this->importProgressId}");

            return;
        }

        try {
            $progress->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            if (! Storage::disk('local')->exists($progress->file_path)) {
                throw new \Exception("CSV file not found at path: {$progress->file_path}");
            }

            $fullPath = Storage::disk('local')->path($progress->file_path);

            $service = new StudentImportService;
            $service->parseCsv($fullPath);
            $validated = $service->validateData();

            $progress->update(['total_rows' => count($validated)]);

            $result = $service->importValidData(function (int $processed, int $imported, int $updated, int $skipped, array $errors) use ($progress): bool {
                if ($processed % 25 !== 0) {
                    return true;
                }

                $progress->refresh();
                if ($progress->status === 'cancelled') {
                    Log::info("Student import {$progress->id} cancelled by user at row {$processed}");

                    return false;
                }

                $progress->update([
                    'processed_rows' => $processed,
                    'created_count' => $imported,
                    'matched_count' => $updated,
                    'skipped_count' => $skipped,
                    'error_count' => count($errors),
                ]);

                return true;
            });

            $progress->refresh();

            if ($progress->status === 'cancelled') {
                Storage::disk('local')->delete($progress->file_path);

                return;
            }

            $progress->update([
                'processed_rows' => $result['total'],
                'created_count' => $result['imported'],
                'matched_count' => $result['updated'],
                'skipped_count' => $result['skipped'],
                'error_count' => count($result['errors']),
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $result,
            ]);

            Storage::disk('local')->delete($progress->file_path);

            Log::info("Student import {$progress->id} completed: {$result['imported']} imported, {$result['updated']} updated, {$result['skipped']} skipped");
        } catch (\Throwable $e) {
            Log::error("Student import {$this->importProgressId} failed: {$e->getMessage()}");

            $progress->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
