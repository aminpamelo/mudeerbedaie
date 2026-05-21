<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->class = ClassModel::factory()->create(['status' => 'active']);

    $this->students = Student::factory()->count(3)->create();
    $this->students->each(function ($student) {
        ClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $student->id,
            'enrolled_at' => now(),
            'status' => 'active',
        ]);
    });
});

test('enrolled students table stays visible after a livewire roundtrip', function () {
    $firstStudentName = $this->students->first()->user->name;

    $component = Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->set('activeTab', 'students');

    // Before the fix, the second roundtrip would re-fetch $class without loadCount,
    // making active_students_count NULL and falling through to the "No Students Enrolled"
    // empty state. Both assertions guard against that regression.
    $component->assertSee($firstStudentName)
        ->assertDontSee('No Students Enrolled');
});
