<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('generateStudentId creates valid student ID format', function () {
    $studentId = Student::generateStudentId();

    expect($studentId)
        ->toStartWith('STU'.date('Y'))
        ->toHaveLength(11); // STU + YYYY + 4 digits
});

test('generateUniqueStudentId creates valid student ID format', function () {
    $studentId = Student::generateUniqueStudentId();

    expect($studentId)
        ->toStartWith('STU'.date('Y'));
});

test('generateUniqueStudentId increments correctly', function () {
    $user = User::factory()->create();

    // Create a student with specific student_id
    Student::create([
        'user_id' => $user->id,
        'student_id' => 'STU'.date('Y').'0001',
        'status' => 'active',
    ]);

    $nextId = Student::generateUniqueStudentId();

    expect($nextId)->toBe('STU'.date('Y').'0002');
});

test('generateUniqueStudentId avoids duplicate IDs', function () {
    $users = User::factory()->count(3)->create();

    // Create students with sequential IDs
    foreach ($users as $index => $user) {
        Student::create([
            'user_id' => $user->id,
            'student_id' => 'STU'.date('Y').str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'status' => 'active',
        ]);
    }

    $nextId = Student::generateUniqueStudentId();

    // Should not be any of the existing IDs
    expect($nextId)->toBe('STU'.date('Y').'0004');

    // Verify it doesn't exist in database
    expect(Student::where('student_id', $nextId)->exists())->toBeFalse();
});

test('student creation automatically generates unique student ID', function () {
    $user = User::factory()->create();

    $student = Student::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    expect($student->student_id)
        ->not->toBeNull()
        ->toStartWith('STU'.date('Y'));
});

test('student creation generates unique IDs for multiple students', function () {
    $users = User::factory()->count(5)->create();
    $studentIds = [];

    foreach ($users as $user) {
        $student = Student::create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $studentIds[] = $student->student_id;
    }

    // All IDs should be unique
    expect($studentIds)->toHaveCount(5);
    expect(array_unique($studentIds))->toHaveCount(5);
});
