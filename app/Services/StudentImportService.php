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
            'address' => ['nullable', 'string', 'max:500'],
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

    protected function findExistingStudent(array $data): ?Student
    {
        // Priority order: phone -> email -> ic_number
        if (! empty($data['phone'])) {
            $student = Student::where('phone', $data['phone'])->first();
            if ($student) {
                return $student;
            }
        }

        if (! empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
            if ($user && $user->student) {
                return $user->student;
            }
        }

        if (! empty($data['ic_number'])) {
            $student = Student::where('ic_number', $data['ic_number'])->first();
            if ($student) {
                return $student;
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
        ]);

        // Assign student role
        $user->assignRole('student');

        // Create student profile
        $student = Student::create([
            'user_id' => $user->id,
            'ic_number' => $data['ic_number'] ?? null,
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
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
            'address' => $data['address'] ?? $student->address,
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
                $student->address ?? '',
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
            'address',
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
                '123 Main Street, Kuala Lumpur',
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
                '456 Oak Avenue, Penang',
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
}
