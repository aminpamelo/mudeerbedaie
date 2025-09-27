<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TeacherImportService
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

            // Check for potential duplicates
            $duplicateWarnings = $this->checkDuplicates($row);
            if (! empty($duplicateWarnings)) {
                $warnings = array_merge($warnings, $duplicateWarnings);
                if ($status === 'valid') {
                    $status = 'warning';
                }
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
            'joined_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
        ];

        return Validator::make($row, $rules);
    }

    protected function checkDuplicates(array $row): array
    {
        $warnings = [];

        // Check for existing phone number (required field)
        if (! empty($row['phone']) && Teacher::where('phone', $row['phone'])->exists()) {
            $warnings[] = 'Phone number already exists - will update existing record';
        }

        // Check for existing email
        if (! empty($row['email']) && User::where('email', $row['email'])->exists()) {
            $warnings[] = 'Email already exists - will update existing record';
        }

        // Check for existing IC number
        if (! empty($row['ic_number']) && Teacher::where('ic_number', $row['ic_number'])->exists()) {
            $warnings[] = 'IC number already exists - will update existing record';
        }

        return $warnings;
    }

    protected function findExistingTeacher(array $data): ?Teacher
    {
        // Priority order: phone -> email -> ic_number
        if (! empty($data['phone'])) {
            $teacher = Teacher::where('phone', $data['phone'])->first();
            if ($teacher) {
                return $teacher;
            }
        }

        if (! empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
            if ($user && $user->teacher) {
                return $user->teacher;
            }
        }

        if (! empty($data['ic_number'])) {
            $teacher = Teacher::where('ic_number', $data['ic_number'])->first();
            if ($teacher) {
                return $teacher;
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
                $existingTeacher = $this->findExistingTeacher($item['data']);

                if ($existingTeacher) {
                    $this->updateTeacher($existingTeacher, $item['data']);
                    $updated++;
                } else {
                    $this->createTeacher($item['data']);
                    $imported++;
                }
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = [
                    'row' => $item['data']['_row_number'],
                    'error' => $e->getMessage(),
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

    protected function createTeacher(array $data): Teacher
    {
        // Generate email if not provided
        $email = $data['email'] ?? null;
        if (empty($email)) {
            // Generate email from phone number
            $phone = preg_replace('/[^0-9]/', '', $data['phone']);
            $email = 'teacher'.$phone.'@example.com';
        }

        // Create user first
        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => Hash::make('password123'), // Default password
            'email_verified_at' => now(),
        ]);

        // Assign teacher role
        $user->assignRole('teacher');

        // Create teacher profile
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'teacher_id' => Teacher::generateTeacherId(),
            'ic_number' => $data['ic_number'] ?? null,
            'phone' => $data['phone'],
            'status' => $data['status'] ?? 'active',
            'joined_at' => ! empty($data['joined_at']) ? $data['joined_at'] : now(),
            'bank_account_holder' => $data['bank_account_holder'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
        ]);

        return $teacher;
    }

    protected function updateTeacher(Teacher $teacher, array $data): Teacher
    {
        // Update user information
        $userUpdateData = [
            'name' => $data['name'],
        ];

        // Only update email if provided
        if (! empty($data['email'])) {
            $userUpdateData['email'] = $data['email'];
        }

        $teacher->user->update($userUpdateData);

        // Update teacher profile
        $teacher->update([
            'ic_number' => $data['ic_number'] ?? $teacher->ic_number,
            'phone' => $data['phone'],
            'status' => $data['status'] ?? $teacher->status,
            'joined_at' => ! empty($data['joined_at']) ? $data['joined_at'] : $teacher->joined_at,
            'bank_account_holder' => $data['bank_account_holder'] ?? $teacher->bank_account_holder,
            'bank_account_number' => $data['bank_account_number'] ?? $teacher->bank_account_number,
            'bank_name' => $data['bank_name'] ?? $teacher->bank_name,
        ]);

        return $teacher;
    }

    public function exportToCsv($teachers = null): string
    {
        if ($teachers === null) {
            $teachers = Teacher::with('user')->get();
        }

        $csvData = [];
        $headers = $this->getExpectedHeaders();
        $csvData[] = $headers;

        foreach ($teachers as $teacher) {
            $csvData[] = [
                $teacher->user->name,
                $teacher->user->email,
                $teacher->teacher_id,
                $teacher->ic_number ?? '',
                $teacher->phone ?? '',
                $teacher->joined_at ? $teacher->joined_at->format('Y-m-d') : '',
                $teacher->status ?? 'active',
                $teacher->bank_account_holder ?? '',
                '', // Don't export bank account number for security
                $teacher->bank_name ?? '',
            ];
        }

        $filename = 'teachers_export_'.date('Y-m-d_H-i-s').'.csv';
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
            'teacher_id',
            'ic_number',
            'phone',
            'joined_at',
            'status',
            'bank_account_holder',
            'bank_account_number',
            'bank_name',
        ];
    }

    public function generateSampleCsv(): string
    {
        $sampleData = [
            $this->getExpectedHeaders(),
            [
                'John Doe',
                'john.doe@example.com', // Optional
                '', // Teacher ID - Will be auto-generated
                '123456789012',
                '+60123456789', // Required
                '2024-01-15',
                'active',
                'John Doe',
                '', // Bank account number - leave empty for security
                'Maybank',
            ],
            [
                'Jane Smith',
                '', // Email optional - will be auto-generated
                '', // Teacher ID - Will be auto-generated
                '987654321098',
                '+60198765432', // Required
                '2024-02-01',
                'active',
                'Jane Smith',
                '', // Bank account number - leave empty for security
                'CIMB Bank',
            ],
        ];

        $filename = 'teachers_import_sample.csv';
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
}
