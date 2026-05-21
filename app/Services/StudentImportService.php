<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class StudentImportService
{
    protected array $csvData = [];

    protected array $validatedData = [];

    protected array $errors = [];

    protected array $warnings = [];

    /** @var array<string, true> */
    protected array $existingPhones = [];

    /** @var array<string, true> */
    protected array $existingEmails = [];

    /** @var array<string, true> */
    protected array $existingIcNumbers = [];

    /** @var array<string, int> CSV-internal duplicate map: value => last row number */
    protected array $csvPhoneMap = [];

    /** @var array<string, int> */
    protected array $csvIcMap = [];

    /** @var array<string, int> */
    protected array $csvEmailMap = [];

    public function parseCsv(string $filePath): array
    {
        $this->csvData = [];
        $this->errors = [];
        $this->warnings = [];

        if (! file_exists($filePath)) {
            throw new \Exception('CSV file not found');
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \Exception('Unable to read CSV file');
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            throw new \Exception('CSV file appears to be empty');
        }

        // Clean up headers (normalize encoding, strip UTF-8 BOM, trim)
        $headers = array_map(fn ($h) => $this->normalizeCell($h), $headers);
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        $expectedHeaders = $this->getExpectedHeaders();

        // Validate required headers only
        $requiredHeaders = ['name', 'phone'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        if (! empty($missingHeaders)) {
            fclose($handle);
            throw new \Exception('Missing required columns: '.implode(', ', $missingHeaders));
        }

        $rowNumber = 1; // Start from 1 for header
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Pad row with empty values if it has fewer columns than headers
            while (count($row) < count($headers)) {
                $row[] = '';
            }

            // Trim row if it has more columns than headers
            if (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }

            $rowData = array_combine($headers, array_map(fn ($v) => $this->normalizeCell($v), $row));
            $rowData['_row_number'] = $rowNumber;

            $this->csvData[] = $rowData;
        }

        fclose($handle);

        return $this->csvData;
    }

    /**
     * Normalize a CSV cell to a trimmed, valid UTF-8 string.
     *
     * Excel exports on Windows commonly produce Windows-1252 / ISO-8859-1 bytes
     * (e.g. curly quotes 0x91/0x92) which break json_encode and prevent Livewire
     * from serializing previewData back to the client.
     */
    protected function normalizeCell(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && ! mb_check_encoding($value, 'UTF-8')) {
            $detected = mb_detect_encoding($value, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'Windows-1252';
            $value = mb_convert_encoding($value, 'UTF-8', $detected);
        }

        return trim($value);
    }

    public function validateData(): array
    {
        $this->validatedData = [];

        $this->preloadExistingRecords();
        $this->buildCsvDuplicateMaps();

        foreach ($this->csvData as $index => $row) {
            $validator = $this->validateRow($row);

            $status = 'valid';
            $errors = [];
            $warnings = [];

            if ($validator->fails()) {
                $status = 'invalid';
                $errors = $validator->errors()->all();
            }

            $duplicateWarnings = $this->checkDuplicates($row);
            if (! empty($duplicateWarnings)) {
                $warnings = array_merge($warnings, $duplicateWarnings);
                if ($status === 'valid') {
                    $status = 'warning';
                }
            }

            $csvDuplicates = $this->checkCsvDuplicates($row);
            if (! empty($csvDuplicates)) {
                $errors = array_merge($errors, $csvDuplicates);
                $status = 'invalid';
            }

            $this->validatedData[] = [
                'data' => $row,
                'status' => $status,
                'errors' => $errors,
                'warnings' => $warnings,
                'index' => $index,
            ];
        }

        return $this->validatedData;
    }

    /**
     * Bulk-load existing student phones/ICs and user emails appearing in the CSV.
     *
     * Replaces three SELECTs per row with three windowed SELECTs total.
     */
    protected function preloadExistingRecords(): void
    {
        $phones = collect($this->csvData)->pluck('phone')->filter()->unique()->values()->all();
        $emails = collect($this->csvData)->pluck('email')->filter()->unique()->values()->all();
        $icNumbers = collect($this->csvData)->pluck('ic_number')->filter()->unique()->values()->all();

        $this->existingPhones = $phones === []
            ? []
            : Student::whereIn('phone', $phones)->pluck('phone')->flip()->map(fn () => true)->all();

        $this->existingEmails = $emails === []
            ? []
            : User::whereIn('email', $emails)->pluck('email')->flip()->map(fn () => true)->all();

        $this->existingIcNumbers = $icNumbers === []
            ? []
            : Student::whereIn('ic_number', $icNumbers)->pluck('ic_number')->flip()->map(fn () => true)->all();
    }

    /**
     * Build O(1)-lookup maps for in-CSV duplicate detection.
     *
     * Replaces the O(n^2) nested loop in checkCsvDuplicates.
     * The LAST occurrence wins (matches original semantics), so we record the highest row number.
     */
    protected function buildCsvDuplicateMaps(): void
    {
        $this->csvPhoneMap = [];
        $this->csvIcMap = [];
        $this->csvEmailMap = [];

        foreach ($this->csvData as $row) {
            $rowNumber = $row['_row_number'] ?? 0;

            if (! empty($row['phone'])) {
                $this->csvPhoneMap[$row['phone']] = $rowNumber;
            }
            if (! empty($row['ic_number'])) {
                $this->csvIcMap[$row['ic_number']] = $rowNumber;
            }
            if (! empty($row['email'])) {
                $this->csvEmailMap[$row['email']] = $rowNumber;
            }
        }
    }

    protected function validateRow(array $row): \Illuminate\Validation\Validator
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'ic_number' => ['nullable', 'string', 'max:20'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,graduated,suspended'],
        ];

        return Validator::make($row, $rules);
    }

    protected function checkDuplicates(array $row): array
    {
        $warnings = [];

        if (! empty($row['phone']) && isset($this->existingPhones[$row['phone']])) {
            $warnings[] = 'Phone number already exists - will update existing record';
        }

        if (! empty($row['email']) && isset($this->existingEmails[$row['email']])) {
            $warnings[] = 'Email already exists - will update existing record';
        }

        if (! empty($row['ic_number']) && isset($this->existingIcNumbers[$row['ic_number']])) {
            $warnings[] = 'IC number already exists - will update existing record';
        }

        return $warnings;
    }

    /**
     * Flag this row as a duplicate only when a LATER row in the CSV holds the same key
     * (preserves "last occurrence wins" semantics from the previous nested loop).
     */
    protected function checkCsvDuplicates(array $row): array
    {
        $errors = [];
        $currentRowNumber = $row['_row_number'] ?? 0;
        $foundDuplicate = false;

        if (! empty($row['phone']) && ($this->csvPhoneMap[$row['phone']] ?? 0) > $currentRowNumber) {
            $errors[] = "Duplicate phone number '{$row['phone']}' - row {$this->csvPhoneMap[$row['phone']]} will be used instead";
            $foundDuplicate = true;
        }

        if (! $foundDuplicate && ! empty($row['ic_number']) && ($this->csvIcMap[$row['ic_number']] ?? 0) > $currentRowNumber) {
            $errors[] = "Duplicate IC number '{$row['ic_number']}' - row {$this->csvIcMap[$row['ic_number']]} will be used instead";
            $foundDuplicate = true;
        }

        if (! $foundDuplicate && ! empty($row['email']) && ($this->csvEmailMap[$row['email']] ?? 0) > $currentRowNumber) {
            $errors[] = "Duplicate email '{$row['email']}' - row {$this->csvEmailMap[$row['email']]} will be used instead";
        }

        return $errors;
    }

    protected function findExistingStudent(array $data): ?Student
    {
        // Build a query to check for ANY matching unique field to avoid constraint violations
        $query = Student::query();

        $hasConditions = false;

        // Check phone number
        if (! empty($data['phone'])) {
            $query->orWhere('phone', $data['phone']);
            $hasConditions = true;
        }

        // Check IC number (critical - has unique constraint)
        if (! empty($data['ic_number'])) {
            $query->orWhere('ic_number', $data['ic_number']);
            $hasConditions = true;
        }

        if ($hasConditions) {
            $student = $query->first();
            if ($student) {
                return $student;
            }
        }

        // Finally check email through user relationship
        if (! empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
            if ($user && $user->student) {
                return $user->student;
            }
        }

        return null;
    }

    /**
     * Import all valid+warning rows from the previously validated dataset.
     *
     * The optional $onProgress callback fires after every row with the running counts:
     *   fn(int $processed, int $imported, int $updated, int $skipped, array $errors): ?bool
     * Returning `false` from the callback aborts the loop (used by the queue job to honour cancellation).
     */
    public function importValidData(?callable $onProgress = null): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $processed = 0;

        foreach ($this->validatedData as $item) {
            if ($item['status'] === 'invalid') {
                $skipped++;
            } else {
                try {
                    $existingStudent = $this->findExistingStudent($item['data']);

                    if ($existingStudent) {
                        $this->updateStudent($existingStudent, $item['data']);
                        $updated++;
                    } else {
                        $this->createStudent($item['data']);
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = [
                        'row' => $item['data']['_row_number'],
                        'error' => $this->formatErrorMessage($e->getMessage(), $item['data']),
                    ];
                }
            }

            $processed++;

            if ($onProgress !== null) {
                $continue = $onProgress($processed, $imported, $updated, $skipped, $errors);
                if ($continue === false) {
                    break;
                }
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($this->validatedData),
        ];
    }

    protected function createStudent(array $data): Student
    {
        // Generate email if not provided
        $email = $data['email'] ?? null;
        if (empty($email)) {
            // Generate email from phone number
            $phone = preg_replace('/[^0-9]/', '', $data['phone']);
            $email = 'student'.$phone.'@example.com';
        }

        // Create user first
        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => Hash::make('password123'), // Default password
            'email_verified_at' => now(),
            'role' => 'student',
        ]);

        // Create student profile
        $student = Student::create([
            'user_id' => $user->id,
            'ic_number' => ! empty($data['ic_number']) ? $data['ic_number'] : null,
            'phone' => $data['phone'],
            'address_line_1' => ! empty($data['address_line_1']) ? $data['address_line_1'] : null,
            'address_line_2' => ! empty($data['address_line_2']) ? $data['address_line_2'] : null,
            'city' => ! empty($data['city']) ? $data['city'] : null,
            'state' => ! empty($data['state']) ? $data['state'] : null,
            'postcode' => ! empty($data['postcode']) ? $data['postcode'] : null,
            'country' => ! empty($data['country']) ? $data['country'] : null,
            'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
            'gender' => ! empty($data['gender']) ? $data['gender'] : null,
            'nationality' => ! empty($data['nationality']) ? $data['nationality'] : 'Malaysian',
            'status' => ! empty($data['status']) ? $data['status'] : 'active',
        ]);

        return $student;
    }

    protected function updateStudent(Student $student, array $data): Student
    {
        // Update user information only if user exists
        if ($student->user) {
            $userUpdateData = [
                'name' => $data['name'],
            ];

            // Only update email if provided
            if (! empty($data['email'])) {
                $userUpdateData['email'] = $data['email'];
            }

            $student->user->update($userUpdateData);
        } else {
            // If no user exists, create one for this student
            $email = $data['email'] ?? null;
            if (empty($email)) {
                // Generate email from phone number
                $phone = preg_replace('/[^0-9]/', '', $data['phone']);
                $email = 'student'.$phone.'@example.com';
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $email,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'role' => 'student',
            ]);

            // Link the user to the student
            $student->user_id = $user->id;
            $student->save();
        }

        // Update student profile
        $student->update([
            'ic_number' => ! empty($data['ic_number']) ? $data['ic_number'] : $student->ic_number,
            'phone' => $data['phone'],
            'address_line_1' => ! empty($data['address_line_1']) ? $data['address_line_1'] : $student->address_line_1,
            'address_line_2' => ! empty($data['address_line_2']) ? $data['address_line_2'] : $student->address_line_2,
            'city' => ! empty($data['city']) ? $data['city'] : $student->city,
            'state' => ! empty($data['state']) ? $data['state'] : $student->state,
            'postcode' => ! empty($data['postcode']) ? $data['postcode'] : $student->postcode,
            'country' => ! empty($data['country']) ? $data['country'] : $student->country,
            'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : $student->date_of_birth,
            'gender' => ! empty($data['gender']) ? $data['gender'] : $student->gender,
            'nationality' => ! empty($data['nationality']) ? $data['nationality'] : $student->nationality,
            'status' => ! empty($data['status']) ? $data['status'] : $student->status,
        ]);

        return $student;
    }

    public function exportToCsv($students = null): string
    {
        if ($students === null) {
            $students = Student::with('user')->get();
        }

        $csvData = [];
        $headers = $this->getExpectedHeaders();
        $csvData[] = $headers;

        foreach ($students as $student) {
            $csvData[] = [
                $student->user->name,
                $student->user->email,
                $student->student_id,
                $student->ic_number ?? '',
                $student->phone ?? '',
                $student->address_line_1 ?? '',
                $student->address_line_2 ?? '',
                $student->city ?? '',
                $student->state ?? '',
                $student->postcode ?? '',
                $student->country ?? '',
                $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '',
                $student->gender ?? '',
                $student->nationality ?? '',
                $student->status ?? 'active',
            ];
        }

        $filename = 'students_export_'.date('Y-m-d_H-i-s').'.csv';
        $filepath = storage_path('app/temp/'.$filename);

        // Ensure temp directory exists
        if (! file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $handle = fopen($filepath, 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $filepath;
    }

    protected function getExpectedHeaders(): array
    {
        return [
            'name',
            'email',
            'student_id',
            'ic_number',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postcode',
            'country',
            'date_of_birth',
            'gender',
            'nationality',
            'status',
        ];
    }

    public function generateSampleCsv(): string
    {
        $sampleData = [
            $this->getExpectedHeaders(),
            [
                'John Doe',
                'john.doe@example.com', // Optional
                '', // Student ID - Will be auto-generated
                '123456789012',
                '+60123456789', // Required
                '123 Main Street', // address_line_1
                'Apt 4B', // address_line_2 (Optional)
                'Kuala Lumpur', // city
                'Selangor', // state
                '50000', // postcode
                'Malaysia', // country
                '1995-05-15',
                'male',
                'Malaysian',
                'active',
            ],
            [
                'Jane Smith',
                '', // Email optional - will be auto-generated
                '', // Student ID - Will be auto-generated
                '987654321098',
                '+60198765432', // Required
                '456 Oak Avenue', // address_line_1
                '', // address_line_2 (Optional)
                'George Town', // city
                'Penang', // state
                '10200', // postcode
                'Malaysia', // country
                '1997-08-22',
                'female',
                'Malaysian',
                'active',
            ],
        ];

        $filename = 'students_import_sample.csv';
        $filepath = storage_path('app/temp/'.$filename);

        // Ensure temp directory exists
        if (! file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $handle = fopen($filepath, 'w');
        foreach ($sampleData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $filepath;
    }

    public function getValidatedData(): array
    {
        return $this->validatedData;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getCsvData(): array
    {
        return $this->csvData;
    }

    protected function formatErrorMessage(string $errorMessage, array $data): string
    {
        // Check for unique constraint violations
        if (str_contains($errorMessage, 'UNIQUE constraint failed') || str_contains($errorMessage, 'Integrity constraint violation')) {
            // Extract field name from error
            if (str_contains($errorMessage, 'ic_number')) {
                return "IC number '{$data['ic_number']}' already exists in the database. This student may have already been imported.";
            }

            if (str_contains($errorMessage, 'phone')) {
                return "Phone number '{$data['phone']}' already exists in the database. This student may have already been imported.";
            }

            if (str_contains($errorMessage, 'email')) {
                return "Email '{$data['email']}' already exists in the database. This student may have already been imported.";
            }

            if (str_contains($errorMessage, 'student_id')) {
                return 'Student ID already exists in the database.';
            }

            return 'Duplicate data found. This student may have already been imported.';
        }

        // Return original error message if it's not a constraint violation
        return $errorMessage;
    }
}
