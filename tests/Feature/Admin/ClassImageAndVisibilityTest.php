<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('exposes a class image url from its own image path', function () {
    $class = ClassModel::factory()->create(['image_path' => 'class-images/cover.jpg']);

    expect($class->image_url)->toContain('class-images/cover.jpg');
});

it('falls back to the course thumbnail when the class has no image', function () {
    $course = Course::factory()->create(['thumbnail_path' => 'thumbnails/course.jpg']);
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'image_path' => null]);

    expect($class->image_url)->toContain('thumbnails/course.jpg');
});

it('returns null image url when neither class nor course has an image', function () {
    $course = Course::factory()->create(['thumbnail_path' => null]);
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'image_path' => null]);

    expect($class->image_url)->toBeNull();
});

it('scopes classes to those visible to students', function () {
    $visible = ClassModel::factory()->create(['is_visible_to_students' => true]);
    ClassModel::factory()->create(['is_visible_to_students' => false]);

    $ids = ClassModel::visibleToStudents()->pluck('id');

    expect($ids)->toContain($visible->id)
        ->and($ids)->toHaveCount(1);
});

it('hides classes flagged invisible from the student portal list', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create(['user_id' => $user->id]);

    $shown = ClassModel::factory()->create([
        'title' => 'Kelas Nampak',
        'is_visible_to_students' => true,
    ]);
    $hidden = ClassModel::factory()->create([
        'title' => 'Kelas Sorok',
        'is_visible_to_students' => false,
    ]);

    ClassStudent::factory()->create(['class_id' => $shown->id, 'student_id' => $student->id, 'status' => 'active']);
    ClassStudent::factory()->create(['class_id' => $hidden->id, 'student_id' => $student->id, 'status' => 'active']);

    $this->actingAs($user)
        ->get('/my/classes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('aktif', 1)
            ->where('aktif.0.title', 'Kelas Nampak')
        );
});

it('renders the admin class list with the new image column', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ClassModel::factory()->create(['title' => 'Kelas Ujian Gambar', 'status' => 'active']);

    $this->actingAs($admin)
        ->get('/admin/classes')
        ->assertOk()
        ->assertSee('Kelas Ujian Gambar');
});

it('renders the admin class create form with image + visibility controls', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/classes/create')
        ->assertOk()
        ->assertSee('Class Image')
        ->assertSee('Show to students');
});

it('renders the admin class edit form with image + visibility controls', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/classes/{$class->id}/edit")
        ->assertOk()
        ->assertSee('Class Image')
        ->assertSee('Show to students');
});

it('blocks direct access to a hidden class detail page', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create(['user_id' => $user->id]);

    $hidden = ClassModel::factory()->create(['is_visible_to_students' => false]);
    ClassStudent::factory()->create(['class_id' => $hidden->id, 'student_id' => $student->id, 'status' => 'active']);

    $this->actingAs($user)
        ->get("/my/classes/{$hidden->id}")
        ->assertNotFound();
});
