<?php

use App\AcademicStatus;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helper function to create a teacher
function createTeacher(): Teacher
{
    $user = User::factory()->create();

    return Teacher::create([
        'user_id' => $user->id,
        'teacher_id' => Teacher::generateTeacherId(),
        'academic_status' => AcademicStatus::ACTIVE,
        'joined_at' => now(),
    ]);
}

// Helper function to create a class
function createClass(Course $course, Teacher $teacher, string $title = 'Test Class'): ClassModel
{
    return ClassModel::create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'title' => $title,
        'date_time' => now()->addDay(),
        'duration_minutes' => 60,
        'class_type' => 'group',
        'academic_status' => AcademicStatus::ACTIVE,
    ]);
}

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
