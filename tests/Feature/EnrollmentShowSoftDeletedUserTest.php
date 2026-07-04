<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('renders the enrollment page when the enrolling admin was soft-deleted', function () {
    $admin = User::factory()->admin()->create();

    $enrollingAdmin = User::factory()->admin()->create(['name' => 'Ex Staff Member']);
    $enrollment = Enrollment::factory()->create([
        'student_id' => Student::factory()->create(),
        'course_id' => Course::factory()->create(),
        'enrolled_by' => $enrollingAdmin->id,
    ]);

    // The staff member who enrolled the student later leaves and their account is soft-deleted.
    $enrollingAdmin->delete();

    actingAs($admin)
        ->get(route('enrollments.show', $enrollment))
        ->assertOk()
        // The soft-deleted admin's name is still displayed via withTrashed().
        ->assertSee('Ex Staff Member');
});

it('renders the enrollment page when the student user was soft-deleted', function () {
    $admin = User::factory()->admin()->create();

    $studentUser = User::factory()->student()->create();
    $student = Student::factory()->create(['user_id' => $studentUser->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => Course::factory()->create(),
        'enrolled_by' => User::factory()->admin()->create()->id,
    ]);

    $studentUser->delete();

    actingAs($admin)
        ->get(route('enrollments.show', $enrollment))
        ->assertOk();
});
