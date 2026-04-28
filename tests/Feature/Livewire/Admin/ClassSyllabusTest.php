<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassSyllabus;
use App\Models\ClassTimetable;
use App\Models\Course;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->course = Course::factory()->create(['created_by' => $this->admin->id]);

    $teacher = createTeacher();

    $this->class = ClassModel::factory()->create([
        'course_id' => $this->course->id,
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
    ]);

    $this->timetable = ClassTimetable::create([
        'class_id' => $this->class->id,
        'weekly_schedule' => ['monday' => ['09:00']],
        'recurrence_pattern' => 'weekly',
        'start_date' => now()->startOfWeek(),
        'total_sessions' => 5,
        'is_active' => true,
    ]);

    $this->class->createSessionsFromTimetable();
});

test('admin can add a syllabus topic to a class', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->set('syllabusTitle', 'Lesson 1: Foundations')
        ->set('syllabusDescription', 'Cover the basics')
        ->call('saveSyllabus')
        ->assertHasNoErrors();

    expect($this->class->syllabi()->count())->toBe(1);

    $topic = $this->class->syllabi()->first();
    expect($topic->title)->toBe('Lesson 1: Foundations');
    expect($topic->description)->toBe('Cover the basics');
    expect($topic->sort_order)->toBe(0);
});

test('newly added topics get the next sort_order', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->set('syllabusTitle', 'First')
        ->call('saveSyllabus')
        ->set('syllabusTitle', 'Second')
        ->call('saveSyllabus')
        ->set('syllabusTitle', 'Third')
        ->call('saveSyllabus');

    $orders = $this->class->syllabi()->pluck('sort_order')->all();
    expect($orders)->toBe([0, 1, 2]);
});

test('saving a topic without a title fails validation', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->set('syllabusTitle', '')
        ->call('saveSyllabus')
        ->assertHasErrors(['syllabusTitle' => 'required']);
});

test('admin can edit an existing topic', function () {
    $topic = ClassSyllabus::factory()->for_class($this->class)->create([
        'title' => 'Original',
        'description' => null,
        'sort_order' => 0,
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('openEditSyllabusModal', $topic->id)
        ->set('syllabusTitle', 'Renamed')
        ->set('syllabusDescription', 'New notes')
        ->call('saveSyllabus')
        ->assertHasNoErrors();

    $topic->refresh();
    expect($topic->title)->toBe('Renamed');
    expect($topic->description)->toBe('New notes');
});

test('moving a topic up swaps it with the previous one', function () {
    $first = ClassSyllabus::factory()->for_class($this->class)->create(['title' => 'A', 'sort_order' => 0]);
    $second = ClassSyllabus::factory()->for_class($this->class)->create(['title' => 'B', 'sort_order' => 1]);
    $third = ClassSyllabus::factory()->for_class($this->class)->create(['title' => 'C', 'sort_order' => 2]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('moveSyllabusUp', $third->id);

    $titles = $this->class->syllabi()->pluck('title')->all();
    expect($titles)->toBe(['A', 'C', 'B']);
});

test('moving the first topic up is a no-op', function () {
    $first = ClassSyllabus::factory()->for_class($this->class)->create(['title' => 'A', 'sort_order' => 0]);
    $second = ClassSyllabus::factory()->for_class($this->class)->create(['title' => 'B', 'sort_order' => 1]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('moveSyllabusUp', $first->id);

    $titles = $this->class->syllabi()->pluck('title')->all();
    expect($titles)->toBe(['A', 'B']);
});

test('admin can attach a syllabus topic to a session', function () {
    $topic = ClassSyllabus::factory()->for_class($this->class)->create(['sort_order' => 0]);
    $session = $this->class->sessions()->first();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('addSessionSyllabus', $session->id, $topic->id);

    expect($session->fresh()->syllabus_ids)->toBe([$topic->id]);
});

test('admin can attach multiple syllabus topics to a session', function () {
    $topic1 = ClassSyllabus::factory()->for_class($this->class)->create(['sort_order' => 0]);
    $topic2 = ClassSyllabus::factory()->for_class($this->class)->create(['sort_order' => 1]);
    $session = $this->class->sessions()->first();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('addSessionSyllabus', $session->id, $topic1->id)
        ->call('addSessionSyllabus', $session->id, $topic2->id);

    expect($session->fresh()->syllabus_ids)->toBe([$topic1->id, $topic2->id]);
});

test('admin can detach a syllabus topic from a session', function () {
    $topic = ClassSyllabus::factory()->for_class($this->class)->create(['sort_order' => 0]);
    $session = $this->class->sessions()->first();
    $session->update(['syllabus_ids' => [$topic->id]]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('removeSessionSyllabus', $session->id, $topic->id);

    expect($session->fresh()->syllabus_ids)->toBeNull();
});

test('deleting a topic also detaches it from any sessions referencing it', function () {
    $topic = ClassSyllabus::factory()->for_class($this->class)->create(['sort_order' => 0]);
    $other = ClassSyllabus::factory()->for_class($this->class)->create(['sort_order' => 1]);
    $session = $this->class->sessions()->first();
    $session->update(['syllabus_ids' => [$topic->id, $other->id]]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('deleteSyllabus', $topic->id);

    expect(ClassSyllabus::find($topic->id))->toBeNull();
    expect($session->fresh()->syllabus_ids)->toBe([$other->id]);
});

test('session syllabusItems accessor returns topics in sort_order', function () {
    $topic1 = ClassSyllabus::factory()->for_class($this->class)->create(['title' => 'A', 'sort_order' => 0]);
    $topic2 = ClassSyllabus::factory()->for_class($this->class)->create(['title' => 'B', 'sort_order' => 1]);
    $session = $this->class->sessions()->first();
    $session->update(['syllabus_ids' => [$topic2->id, $topic1->id]]); // intentionally out of order

    $titles = $session->fresh()->syllabusItems()->pluck('title')->all();
    expect($titles)->toBe(['A', 'B']);
});
