<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\ClassTimetable;
use App\Models\ClassTimetableUpsell;
use App\Models\Course;
use App\Models\Funnel;
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
        'weekly_schedule' => ['monday' => ['09:00'], 'wednesday' => ['14:00']],
        'recurrence_pattern' => 'weekly',
        'start_date' => now()->startOfWeek(),
        'total_sessions' => 10,
        'is_active' => true,
    ]);

    $this->funnel = Funnel::factory()->published()->create([
        'user_id' => $this->admin->id,
    ]);

    // Generate sessions from timetable
    $this->class->createSessionsFromTimetable();
});

test('admin can view upsell tab on class detail page', function () {
    $this->actingAs($this->admin)
        ->get(route('classes.show', $this->class).'?tab=upsell')
        ->assertOk();
});

test('admin can assign funnel to a session inline', function () {
    $session = $this->class->sessions()->first();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('updateSessionUpsell', $session->id, 'upsell_funnel_id', $this->funnel->id)
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_funnel_id)->toBe($this->funnel->id);
});

test('admin can assign PIC to a session inline', function () {
    $session = $this->class->sessions()->first();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('updateSessionUpsell', $session->id, 'upsell_pic_user_id', $this->admin->id)
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_pic_user_id)->toBe($this->admin->id);
});

test('admin can clear funnel from a session', function () {
    $session = $this->class->sessions()->first();
    $session->update(['upsell_funnel_id' => $this->funnel->id]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('updateSessionUpsell', $session->id, 'upsell_funnel_id', '')
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_funnel_id)->toBeNull();
});

test('sessions inherit upsell config from timetable slots', function () {
    ClassTimetableUpsell::create([
        'class_timetable_id' => $this->timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $this->funnel->id,
        'pic_user_id' => $this->admin->id,
        'is_active' => true,
    ]);

    // Re-generate sessions to pick up upsell config
    $this->class->createSessionsFromTimetable();

    $mondaySessions = $this->class->sessions()->where('session_time', '09:00')->get();
    expect($mondaySessions)->not->toBeEmpty();

    foreach ($mondaySessions as $session) {
        expect($session->upsell_funnel_id)->toBe($this->funnel->id);
        expect($session->upsell_pic_user_id)->toBe($this->admin->id);
    }

    $wednesdaySessions = $this->class->sessions()->where('session_time', '14:00')->get();
    expect($wednesdaySessions)->not->toBeEmpty();

    foreach ($wednesdaySessions as $session) {
        expect($session->upsell_funnel_id)->toBeNull();
        expect($session->upsell_pic_user_id)->toBeNull();
    }
});

test('upsell sessions list shows all sessions', function () {
    $sessionsCount = $this->class->sessions()->count();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('setActiveTab', 'upsell')
        ->assertOk();

    expect($sessionsCount)->toBeGreaterThan(0);
});
