<?php

declare(strict_types=1);

use App\Models\ClassAnnouncement;
use App\Models\ClassAnnouncementRead;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('student can see published announcements for their class', function () {
    $student = Student::factory()->create();
    $user = $student->user;
    $class = ClassModel::factory()->create(['status' => 'active']);
    ClassStudent::create(['class_id' => $class->id, 'student_id' => $student->id, 'enrolled_at' => now(), 'status' => 'active']);

    ClassAnnouncement::factory()->create(['class_id' => $class->id, 'title' => 'Peringatan ujian']);

    $response = $this->actingAs($user)->get("/my/classes/{$class->id}?tab=announcements");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ClassShow', false)
        ->has('announcements', 1)
        ->where('announcements.0.title', 'Peringatan ujian')
    );
});

test('unread announcement count is correct for student', function () {
    $student = Student::factory()->create();
    $user = $student->user;
    $class = ClassModel::factory()->create(['status' => 'active']);
    ClassStudent::create(['class_id' => $class->id, 'student_id' => $student->id, 'enrolled_at' => now(), 'status' => 'active']);

    $announcement1 = ClassAnnouncement::factory()->create(['class_id' => $class->id]);
    $announcement2 = ClassAnnouncement::factory()->create(['class_id' => $class->id]);

    // Mark one as read
    ClassAnnouncementRead::create([
        'announcement_id' => $announcement1->id,
        'student_id' => $student->id,
        'read_at' => now(),
    ]);

    $response = $this->actingAs($user)->get("/my/classes/{$class->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ClassShow', false)
        ->where('unreadAnnouncementCount', 1)
    );
});
