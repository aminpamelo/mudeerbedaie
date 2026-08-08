<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassResource;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('teacher can create a resource in their class', function () {
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;
    $class = ClassModel::factory()->create(['teacher_id' => $teacher->id, 'status' => 'active']);

    Volt::actingAs($user)
        ->test('teacher.classes-show', ['class' => $class])
        ->set('resourceTitle', 'Nota Tajwid Bab 1')
        ->set('resourceType', 'link')
        ->set('resourceUrl', 'https://example.com/nota')
        ->set('resourcePublished', true)
        ->call('saveResource')
        ->assertHasNoErrors();

    expect(ClassResource::where('class_id', $class->id)->where('title', 'Nota Tajwid Bab 1')->exists())->toBeTrue();
});

test('teacher can delete a resource from their class', function () {
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;
    $class = ClassModel::factory()->create(['teacher_id' => $teacher->id, 'status' => 'active']);
    $resource = ClassResource::factory()->create(['class_id' => $class->id, 'uploaded_by' => $user->id]);

    Volt::actingAs($user)
        ->test('teacher.classes-show', ['class' => $class])
        ->call('deleteResource', $resource->id);

    expect(ClassResource::find($resource->id))->toBeNull();
});
