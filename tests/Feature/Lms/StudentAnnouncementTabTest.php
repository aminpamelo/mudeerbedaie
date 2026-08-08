<?php

declare(strict_types=1);

use App\Models\ClassAnnouncement;
use App\Models\ClassAnnouncementRead;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('student can see published announcements for their class', function () {
    $student = Student::factory()->create();
    $user = $student->user;
    $class = ClassModel::factory()->create(['status' => 'active']);
    ClassStudent::create(['class_id' => $class->id, 'student_id' => $student->id, 'enrolled_at' => now(), 'status' => 'active']);

    ClassAnnouncement::factory()->create(['class_id' => $class->id, 'title' => 'Peringatan ujian']);

    Volt::actingAs($user)
        ->test('student.class-show', ['class' => $class])
        ->set('activeTab', 'announcements')
        ->assertSee('Peringatan ujian');
});

test('student marking announcement as read creates a read record', function () {
    $student = Student::factory()->create();
    $user = $student->user;
    $class = ClassModel::factory()->create(['status' => 'active']);
    ClassStudent::create(['class_id' => $class->id, 'student_id' => $student->id, 'enrolled_at' => now(), 'status' => 'active']);

    $announcement = ClassAnnouncement::factory()->create(['class_id' => $class->id]);

    // Verify no read record exists before marking
    expect(ClassAnnouncementRead::where('announcement_id', $announcement->id)->where('student_id', $student->id)->exists())->toBeFalse();

    Volt::actingAs($user)
        ->test('student.class-show', ['class' => $class])
        ->call('markAnnouncementRead', $announcement->id);

    // Verify read record now exists
    expect(ClassAnnouncementRead::where('announcement_id', $announcement->id)->where('student_id', $student->id)->exists())->toBeTrue();
});
