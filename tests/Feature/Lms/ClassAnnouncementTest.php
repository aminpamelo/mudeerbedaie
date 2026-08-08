<?php

declare(strict_types=1);

use App\Models\ClassAnnouncement;
use App\Models\ClassAnnouncementRead;
use App\Models\ClassModel;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('announcement belongs to a class', function () {
    $announcement = ClassAnnouncement::factory()->create();

    expect($announcement->class)->toBeInstanceOf(ClassModel::class);
});

test('published scope excludes future announcements', function () {
    $class = ClassModel::factory()->create();

    $past = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()->subHour()]);
    $future = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()->addHour()]);

    $results = ClassAnnouncement::published()->pluck('id');

    expect($results)->toContain($past->id);
    expect($results)->not->toContain($future->id);
});

test('ordered scope puts pinned first then by date desc', function () {
    $class = ClassModel::factory()->create();

    $old = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()->subDays(3)]);
    $new = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()]);
    $pinned = ClassAnnouncement::factory()->pinned()->create(['class_id' => $class->id, 'published_at' => now()->subWeek()]);

    $ordered = ClassAnnouncement::ordered()->pluck('id')->toArray();

    expect($ordered[0])->toBe($pinned->id);
});

test('mark as read creates read record and isReadBy returns true', function () {
    $announcement = ClassAnnouncement::factory()->create();
    $student = Student::factory()->create();

    expect($announcement->isReadBy($student))->toBeFalse();

    $announcement->markAsRead($student);

    expect($announcement->isReadBy($student))->toBeTrue();
    expect($announcement->read_count)->toBe(1);
});

test('mark as read is idempotent', function () {
    $announcement = ClassAnnouncement::factory()->create();
    $student = Student::factory()->create();

    $announcement->markAsRead($student);
    $announcement->markAsRead($student);

    expect(ClassAnnouncementRead::where('announcement_id', $announcement->id)->count())->toBe(1);
});
