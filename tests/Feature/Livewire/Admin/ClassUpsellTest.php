<?php

declare(strict_types=1);

use App\Models\ClassModel;
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
});

test('admin can view upsell tab on class detail page', function () {
    $this->actingAs($this->admin)
        ->get(route('classes.show', $this->class).'?tab=upsell')
        ->assertOk();
});

test('admin can create upsell configuration', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('setActiveTab', 'upsell')
        ->call('openUpsellModal')
        ->set('upsellDayOfWeek', 'monday')
        ->set('upsellTimeSlot', '09:00')
        ->set('upsellFunnelId', $this->funnel->id)
        ->set('upsellPicUserId', $this->admin->id)
        ->call('saveUpsell')
        ->assertHasNoErrors();

    expect(ClassTimetableUpsell::where('class_timetable_id', $this->timetable->id)->count())->toBe(1);

    $upsell = ClassTimetableUpsell::where('class_timetable_id', $this->timetable->id)->first();
    expect($upsell->day_of_week)->toBe('monday');
    expect($upsell->time_slot)->toBe('09:00');
    expect($upsell->funnel_id)->toBe($this->funnel->id);
    expect($upsell->pic_user_id)->toBe($this->admin->id);
    expect($upsell->is_active)->toBeTrue();
});

test('admin can delete upsell configuration', function () {
    $upsell = ClassTimetableUpsell::create([
        'class_timetable_id' => $this->timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $this->funnel->id,
        'pic_user_id' => $this->admin->id,
        'is_active' => true,
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('deleteUpsell', $upsell->id);

    expect(ClassTimetableUpsell::find($upsell->id))->toBeNull();
});

test('admin can toggle upsell active status', function () {
    $upsell = ClassTimetableUpsell::create([
        'class_timetable_id' => $this->timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $this->funnel->id,
        'pic_user_id' => $this->admin->id,
        'is_active' => true,
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('toggleUpsellActive', $upsell->id);

    expect($upsell->fresh()->is_active)->toBeFalse();
});

test('toggling upsell active status is reversible', function () {
    $upsell = ClassTimetableUpsell::create([
        'class_timetable_id' => $this->timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $this->funnel->id,
        'pic_user_id' => $this->admin->id,
        'is_active' => false,
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('toggleUpsellActive', $upsell->id);

    expect($upsell->fresh()->is_active)->toBeTrue();
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

test('saveUpsell validates required fields', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('setActiveTab', 'upsell')
        ->call('openUpsellModal')
        ->set('upsellDayOfWeek', '')
        ->set('upsellTimeSlot', '')
        ->set('upsellFunnelId', null)
        ->set('upsellPicUserId', null)
        ->call('saveUpsell')
        ->assertHasErrors(['upsellDayOfWeek', 'upsellTimeSlot', 'upsellFunnelId', 'upsellPicUserId']);

    expect(ClassTimetableUpsell::count())->toBe(0);
});

test('saveUpsell updates existing upsell for same slot', function () {
    $upsell = ClassTimetableUpsell::create([
        'class_timetable_id' => $this->timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $this->funnel->id,
        'pic_user_id' => $this->admin->id,
        'is_active' => true,
    ]);

    $newPic = User::factory()->admin()->create();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('openUpsellModal')
        ->set('upsellDayOfWeek', 'monday')
        ->set('upsellTimeSlot', '09:00')
        ->set('upsellFunnelId', $this->funnel->id)
        ->set('upsellPicUserId', $newPic->id)
        ->call('saveUpsell')
        ->assertHasNoErrors();

    // Should update, not duplicate
    expect(ClassTimetableUpsell::where('class_timetable_id', $this->timetable->id)->count())->toBe(1);
    expect($upsell->fresh()->pic_user_id)->toBe($newPic->id);
});
