<?php

use App\AcademicStatus;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('enrollment can access available classes in course', function () {
    // Create test data
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_status' => AcademicStatus::ACTIVE,
    ]);

    // Create classes for the course
    $class1 = createClass($course, $teacher, 'Class 1');
    $class2 = createClass($course, $teacher, 'Class 2');

    // Create class in different course
    $otherCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
    $otherClass = createClass($otherCourse, $teacher, 'Other Class');

    // Test availableClasses relationship
    $availableClasses = $enrollment->availableClasses;

    expect($availableClasses)->toHaveCount(2);
    expect($availableClasses->pluck('id'))->toContain($class1->id, $class2->id);
    expect($availableClasses->pluck('id'))->not->toContain($otherClass->id);
});

test('enrollment can join and leave classes', function () {
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_status' => AcademicStatus::ACTIVE,
    ]);

    $class = createClass($course, $teacher);

    // Test joining class
    $classStudent = $enrollment->joinClass($class);
    expect($classStudent)->not->toBeNull();
    expect($classStudent->student_id)->toBe($student->id);
    expect($classStudent->class_id)->toBe($class->id);
    expect($classStudent->status)->toBe('active');

    // Test student is now in class
    expect($enrollment->isStudentInClass($class))->toBeTrue();

    // Test leaving class
    $result = $enrollment->leaveClass($class, 'Test reason');
    expect($result)->toBeTrue();

    // Refresh the classStudent record
    $classStudent->refresh();
    expect($classStudent->status)->toBe('quit');
});

test('enrollment validates class joining eligibility', function () {
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_status' => AcademicStatus::ACTIVE,
    ]);

    $class = createClass($course, $teacher);
    $otherCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
    $otherCourseClass = createClass($otherCourse, $teacher);

    // Test canJoinClass method
    expect($enrollment->canJoinClass($class))->toBeTrue();
    expect($enrollment->canJoinClass($otherCourseClass))->toBeFalse();
});
