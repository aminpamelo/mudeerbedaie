<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassResource;
use App\Models\ClassResourceView;
use App\Models\ClassSession;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('class resource belongs to a class', function () {
    $resource = ClassResource::factory()->create();

    expect($resource->class)->toBeInstanceOf(ClassModel::class);
});

test('class resource can be attached to a session', function () {
    $session = ClassSession::factory()->create();
    $resource = ClassResource::factory()->create(['session_id' => $session->id, 'class_id' => $session->class_id]);

    expect($resource->session)->toBeInstanceOf(ClassSession::class);
    expect($resource->session->id)->toBe($session->id);
});

test('published scope excludes unpublished and future-scheduled resources', function () {
    $class = ClassModel::factory()->create();

    $published = ClassResource::factory()->create(['class_id' => $class->id, 'is_published' => true, 'published_at' => now()->subHour()]);
    $unpublished = ClassResource::factory()->create(['class_id' => $class->id, 'is_published' => false]);
    $future = ClassResource::factory()->scheduledRelease()->create(['class_id' => $class->id]);

    $results = ClassResource::published()->pluck('id');

    expect($results)->toContain($published->id);
    expect($results)->not->toContain($unpublished->id);
    expect($results)->not->toContain($future->id);
});

test('record view creates or updates view tracking', function () {
    $resource = ClassResource::factory()->create();
    $student = Student::factory()->create();

    $view = $resource->recordView($student);

    expect($view)->toBeInstanceOf(ClassResourceView::class);
    expect($view->student_id)->toBe($student->id);
    expect($view->class_resource_id)->toBe($resource->id);

    // Second view should not create duplicate
    $resource->recordView($student);
    expect(ClassResourceView::where('class_resource_id', $resource->id)->where('student_id', $student->id)->count())->toBe(1);
});

test('icon attribute returns correct icon per type', function () {
    $resource = ClassResource::factory()->create(['type' => 'recording']);
    expect($resource->icon)->toBe('video-camera');

    $resource->type = 'pdf';
    expect($resource->icon)->toBe('document-text');

    $resource->type = 'note';
    expect($resource->icon)->toBe('pencil-square');
});
