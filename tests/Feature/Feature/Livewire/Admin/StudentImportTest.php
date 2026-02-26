<?php

use App\Jobs\ProcessStudentImportToClass;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\StudentImportProgress;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('import skips students with no associated user account', function () {
    Storage::fake('local');

    $class = ClassModel::factory()->create(['max_capacity' => 50]);

    // Create a student without an associated user (orphaned record)
    $student = Student::factory()->create([
        'phone' => '60123456789',
    ]);
    // Delete the user to simulate orphaned student
    User::where('id', $student->user_id)->delete();

    // Create CSV content
    $csvContent = "phone,name,email\n60123456789,Test Student,test@example.com\n";
    $filePath = 'imports/students/test_import.csv';
    Storage::disk('local')->put($filePath, $csvContent);

    $importProgress = StudentImportProgress::create([
        'class_id' => $class->id,
        'user_id' => User::factory()->create(['role' => 'admin'])->id,
        'file_path' => $filePath,
        'status' => 'pending',
        'auto_enroll' => true,
        'create_missing' => false,
    ]);

    $job = new ProcessStudentImportToClass($importProgress->id);
    $job->handle();

    $importProgress->refresh();

    expect($importProgress->status)->toBe('completed')
        ->and($importProgress->error_count)->toBe(1)
        ->and($importProgress->result['errors'][0])->toContain('no associated user account');
});

test('import successfully processes students with valid user accounts', function () {
    Storage::fake('local');

    $class = ClassModel::factory()->create(['max_capacity' => 50]);

    $user = User::factory()->create(['name' => 'Valid Student', 'role' => 'student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    $csvContent = "phone,name,email\n60123456789,Valid Student,valid@example.com\n";
    $filePath = 'imports/students/test_import.csv';
    Storage::disk('local')->put($filePath, $csvContent);

    $importProgress = StudentImportProgress::create([
        'class_id' => $class->id,
        'user_id' => User::factory()->create(['role' => 'admin'])->id,
        'file_path' => $filePath,
        'status' => 'pending',
        'auto_enroll' => true,
        'create_missing' => false,
    ]);

    $job = new ProcessStudentImportToClass($importProgress->id);
    $job->handle();

    $importProgress->refresh();

    expect($importProgress->status)->toBe('completed')
        ->and($importProgress->matched_count)->toBe(1)
        ->and($importProgress->error_count)->toBe(0);
});
