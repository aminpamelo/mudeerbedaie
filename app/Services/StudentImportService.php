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

        // Clean up headers
        $headers = array_map('trim', $headers);
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

            $rowData = array_combine($headers, array_map('trim', $row));
            $rowData['_row_number'] = $rowNumber;

            $this->csvData[] = $rowData;
        }

        fclose($handle);

        return $this->csvData;
    }

    public function validateData(): array
    {
        $this->validatedData = [];

        foreach ($this->csvData as $index => $row) {
            $validator = $this->validateRow($row);

            $status = 'valid';
            $errors = [];
            $warnings = [];

            if ($validator->fails()) {
                $status = 'invalid';
                $errors = $validator->errors()->all();
            }

            // Check for potential duplicates in database
            $duplicateWarnings = $this->checkDuplicates($row);
            if (! empty($duplicateWarnings)) {
                $warnings = array_merge($warnings, $duplicateWarnings);
                if ($status === 'valid') {
                    $status = 'warning';
                }
            }

            // Check for duplicates within CSV file
            $csvDuplicates = $this->checkCsvDuplicates($row, $index);
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

        // Check for existing phone number (required field)
        if (! empty($row['phone']) && Student::where('phone', $row['phone'])->exists()) {
            $warnings[] = 'Phone number already exists - will update existing record';
        }

        // Check for existing email
        if (! empty($row['email']) && User::where('email', $row['email'])->exists()) {
            $warnings[] = 'Email already exists - will update existing record';
        }

        // Check for existing IC number
        if (! empty($row['ic_number']) && Student::where('ic_number', $row['ic_number'])->exists()) {
            $warnings[] = 'IC number already exists - will update existing record';
        }

        return $warnings;
    }

    protected function checkCsvDuplicates(array $row, int $currentIndex): array
    {
        $errors = [];

        // Check for duplicates within the CSV file
        // We check rows that come AFTER the current row, so the LAST occurrence wins
        foreach ($this->csvData as $index => $csvRow) {
            // Only check rows that come after current row
            if ($index <= $currentIndex) {
                continue;
            }

            $foundDuplicate = false;

            // Check for duplicate phone numbers (required field - most important)
            if (! empty($row['phone']) && ! empty($csvRow['phone']) && $row['phone'] === $csvRow['phone']) {
                $errors[] = "Duplicate phone number '{$row['phone']}' - row {$csvRow['_row_number']} will be used instead";
                $foundDuplicate = true;
            }

            // Check for duplicate IC numbers (only if phone didn't match)
            if (! $foundDuplicate && ! empty($row['ic_number']) && ! empty($csvRow['ic_number']) && $row['ic_number'] === $csvRow['ic_number']) {
                $errors[] = "Duplicate IC number '{$row['ic_number']}' - row {$csvRow['_row_number']} will be used instead";
                $foundDuplicate = true;
            }

            // Check for duplicate email addresses (only if phone and IC didn't match)
            if (! $foundDuplicate && ! empty($row['email']) && ! empty($csvRow['email']) && $row['email'] === $csvRow['email']) {
                $errors[] = "Duplicate email '{$row['email']}' - row {$csvRow['_row_number']} will be used instead";
            }
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

    public function importValidData(): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($this->validatedData as $item) {
            if ($item['status'] === 'invalid') {
                $skipped++;

                continue;
            }

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
            'ic_number' => $data['ic_number'] ?? null,
            'phone' => $data['phone'],
            'address_line_1' => $data['address_line_1'] ?? null,
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'country' => $data['country'] ?? null,
            'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
            'gender' => $data['gender'] ?? null,
            'nationality' => $data['nationality'] ?? 'Malaysian',
            'status' => $data['status'] ?? 'active',
        ]);

        return $student;
    }

    protected function updateStudent(Student $student, array $data): Student
    {
        // Update user information
        $userUpdateData = [
            'name' => $data['name'],
        ];

        // Only update email if provided
        if (! empty($data['email'])) {
            $userUpdateData['email'] = $data['email'];
        }

        $student->user->update($userUpdateData);

        // Update student profile
        $student->update([
            'ic_number' => $data['ic_number'] ?? $student->ic_number,
            'phone' => $data['phone'],
            'address_line_1' => $data['address_line_1'] ?? $student->address_line_1,
            'address_line_2' => $data['address_line_2'] ?? $student->address_line_2,
            'city' => $data['city'] ?? $student->city,
            'state' => $data['state'] ?? $student->state,
            'postcode' => $data['postcode'] ?? $student->postcode,
            'country' => $data['country'] ?? $student->country,
            'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : $student->date_of_birth,
            'gender' => $data['gender'] ?? $student->gender,
            'nationality' => $data['nationality'] ?? $student->nationality,
            'status' => $data['status'] ?? $student->status,
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
