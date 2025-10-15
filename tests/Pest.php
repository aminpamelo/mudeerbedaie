<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\AcademicStatus;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;

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
