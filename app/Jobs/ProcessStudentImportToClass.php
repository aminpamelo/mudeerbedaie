<?php

namespace App\Jobs;

use App\Models\ClassModel;
use App\Models\Student;
use App\Models\StudentImportProgress;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessStudentImportToClass implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importProgressId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $importProgress = StudentImportProgress::find($this->importProgressId);

        if (! $importProgress) {
            Log::error("Student import progress not found: {$this->importProgressId}");

            return;
        }

        try {
            $importProgress->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $class = ClassModel::find($importProgress->class_id);

            if (! $class) {
                throw new \Exception('Class not found');
            }

            // Debug logging for production troubleshooting
            $storagePath = Storage::disk('local')->path('');
            $fullPath = Storage::disk('local')->path($importProgress->file_path);
            Log::info("Student import job - Storage base path: {$storagePath}");
            Log::info("Student import job - Looking for file: {$importProgress->file_path}");
            Log::info("Student import job - Full path would be: {$fullPath}");

            // Use Storage facade for consistent file access across environments
            $fileExists = Storage::disk('local')->exists($importProgress->file_path);
            Log::info('Student import job - Storage exists check: '.($fileExists ? 'YES' : 'NO'));

            if (! $fileExists) {
                // Additional debug: check with raw file_exists
                $rawExists = file_exists($fullPath);
                Log::error('Student import job - File not found via Storage. Raw file_exists: '.($rawExists ? 'YES' : 'NO'));

                // List files in directory for debugging
                $directory = dirname($importProgress->file_path);
                if (Storage::disk('local')->exists($directory)) {
                    $files = Storage::disk('local')->files($directory);
                    Log::info("Student import job - Files in {$directory}: ".json_encode($files));
                } else {
                    Log::error("Student import job - Directory does not exist: {$directory}");
                }

                throw new \Exception("CSV file not found at path: {$importProgress->file_path}");
            }

            $fileContents = Storage::disk('local')->get($importProgress->file_path);

            if ($fileContents === false || $fileContents === null) {
                throw new \Exception('Failed to read uploaded file');
            }

            // Parse CSV
            $lines = preg_split('/\r\n|\r|\n/', $fileContents);
            $header = str_getcsv(array_shift($lines));

            // Normalize headers (lowercase and trim)
            $header = array_map(fn ($h) => strtolower(trim($h)), $header);

            // Find required columns
            $phoneIndex = array_search('phone', $header);
            $nameIndex = array_search('name', $header);
            $emailIndex = array_search('email', $header);
            $orderIdIndex = array_search('order_id', $header);

            if ($phoneIndex === false) {
                throw new \Exception('CSV must contain a "phone" column.');
            }

            // Filter empty lines
            $lines = array_filter($lines, fn ($line) => ! empty(trim($line)));
            $totalRows = count($lines);

            $importProgress->update(['total_rows' => $totalRows]);

            $result = [
                'matched' => [],
                'created' => [],
                'skipped' => [],
                'enrolled' => [],
                'already_enrolled' => [],
                'errors' => [],
            ];

            // Get existing students in this class
            $classStudentIds = $class->activeStudents()
                ->pluck('student_id')
                ->toArray();

            $processedRows = 0;
            $matchedCount = 0;
            $createdCount = 0;
            $enrolledCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($lines as $lineNumber => $line) {
                // Check for cancellation every 10 rows for efficiency
                if ($processedRows % 10 === 0) {
                    $importProgress->refresh();
                    if ($importProgress->isCancelled()) {
                        Log::info("Student import cancelled by user at row {$processedRows}");
                        // Clean up uploaded file
                        Storage::disk('local')->delete($importProgress->file_path);

                        return;
                    }
                }

                $row = str_getcsv($line);
                $phone = isset($row[$phoneIndex]) ? trim($row[$phoneIndex]) : null;
                $name = $nameIndex !== false && isset($row[$nameIndex]) ? trim($row[$nameIndex]) : null;
                $email = $emailIndex !== false && isset($row[$emailIndex]) ? trim($row[$emailIndex]) : null;
                $orderId = $orderIdIndex !== false && isset($row[$orderIdIndex]) ? trim($row[$orderIdIndex]) : null;

                if (empty($phone)) {
                    $result['errors'][] = 'Row '.($lineNumber + 2).': Phone number is required.';
                    $errorCount++;
                    $processedRows++;
                    $this->updateProgress($importProgress, $processedRows, $matchedCount, $createdCount, $enrolledCount, $skippedCount, $errorCount);

                    continue;
                }

                // Normalize phone number
                $normalizedPhone = preg_replace('/[^0-9+]/', '', $phone);
                $phoneVariants = [
                    $normalizedPhone,
                    ltrim($normalizedPhone, '+'),
                    '60'.ltrim(ltrim($normalizedPhone, '+'), '0'),
                    ltrim(ltrim($normalizedPhone, '+'), '60'),
                ];

                // Try to find student by phone
                $student = Student::where(function ($query) use ($phoneVariants) {
                    foreach ($phoneVariants as $variant) {
                        $query->orWhere('phone', 'like', '%'.$variant)
                            ->orWhere('phone', $variant);
                    }
                })->first();

                if ($student) {
                    $matchedCount++;
                    $result['matched'][] = [
                        'phone' => $phone,
                        'name' => $student->user->name,
                        'student_id' => $student->student_id,
                    ];

                    // Check if already enrolled
                    if (in_array($student->id, $classStudentIds)) {
                        $result['already_enrolled'][] = [
                            'phone' => $phone,
                            'name' => $student->user->name,
                        ];
                        $processedRows++;
                        $this->updateProgress($importProgress, $processedRows, $matchedCount, $createdCount, $enrolledCount, $skippedCount, $errorCount);

                        continue;
                    }

                    // Enroll if auto-enroll is enabled
                    if ($importProgress->auto_enroll) {
                        // Check capacity
                        if ($class->max_capacity) {
                            $currentCount = count($classStudentIds) + $enrolledCount;
                            if ($currentCount >= $class->max_capacity) {
                                $result['errors'][] = "Skipped {$student->user->name}: Class is at maximum capacity.";
                                $errorCount++;
                                $processedRows++;
                                $this->updateProgress($importProgress, $processedRows, $matchedCount, $createdCount, $enrolledCount, $skippedCount, $errorCount);

                                continue;
                            }
                        }

                        $class->addStudent($student, $orderId);
                        $enrolledCount++;
                        $result['enrolled'][] = [
                            'phone' => $phone,
                            'name' => $student->user->name,
                            'order_id' => $orderId,
                        ];
                    }
                } else {
                    // Student not found
                    if ($importProgress->create_missing && $name) {
                        try {
                            DB::beginTransaction();

                            // Create user first
                            $user = User::create([
                                'name' => $name,
                                'email' => $email ?: null,
                                'password' => Hash::make($importProgress->default_password ?? 'password123'),
                                'role' => 'student',
                            ]);

                            // Create student profile
                            $newStudent = Student::create([
                                'user_id' => $user->id,
                                'phone' => $normalizedPhone,
                                'status' => 'active',
                            ]);

                            DB::commit();

                            $createdCount++;
                            $result['created'][] = [
                                'phone' => $phone,
                                'name' => $name,
                                'student_id' => $newStudent->student_id,
                            ];

                            // Enroll if auto-enroll is enabled
                            if ($importProgress->auto_enroll) {
                                // Check capacity
                                if ($class->max_capacity) {
                                    $currentCount = count($classStudentIds) + $enrolledCount;
                                    if ($currentCount >= $class->max_capacity) {
                                        $result['errors'][] = "Skipped enrolling {$name}: Class is at maximum capacity.";
                                        $errorCount++;
                                        $processedRows++;
                                        $this->updateProgress($importProgress, $processedRows, $matchedCount, $createdCount, $enrolledCount, $skippedCount, $errorCount);

                                        continue;
                                    }
                                }

                                $class->addStudent($newStudent, $orderId);
                                $enrolledCount++;
                                $result['enrolled'][] = [
                                    'phone' => $phone,
                                    'name' => $name,
                                    'order_id' => $orderId,
                                ];
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();
                            $result['errors'][] = 'Row '.($lineNumber + 2).': Failed to create student - '.$e->getMessage();
                            $errorCount++;
                        }
                    } else {
                        $skippedCount++;
                        $result['skipped'][] = [
                            'phone' => $phone,
                            'name' => $name ?? 'Unknown',
                            'reason' => $importProgress->create_missing ? 'Name is required to create student' : 'Student not found',
                        ];
                    }
                }

                $processedRows++;
                $this->updateProgress($importProgress, $processedRows, $matchedCount, $createdCount, $enrolledCount, $skippedCount, $errorCount);
            }

            // Update final result
            $importProgress->update([
                'status' => 'completed',
                'result' => $result,
                'completed_at' => now(),
            ]);

            // Clean up uploaded file
            Storage::disk('local')->delete($importProgress->file_path);

            Log::info("Student import to class completed: {$processedRows} rows processed, {$matchedCount} matched, {$createdCount} created, {$enrolledCount} enrolled");
        } catch (\Exception $e) {
            Log::error("Student import to class failed: {$e->getMessage()}");

            $importProgress->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            // Clean up uploaded file
            Storage::disk('local')->delete($importProgress->file_path);

            throw $e;
        }
    }

    private function updateProgress(
        StudentImportProgress $importProgress,
        int $processedRows,
        int $matchedCount,
        int $createdCount,
        int $enrolledCount,
        int $skippedCount,
        int $errorCount
    ): void {
        $importProgress->update([
            'processed_rows' => $processedRows,
            'matched_count' => $matchedCount,
            'created_count' => $createdCount,
            'enrolled_count' => $enrolledCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
        ]);
    }
}
