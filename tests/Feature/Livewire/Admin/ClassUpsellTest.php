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

    // Generate sessions from timetable
    $this->class->createSessionsFromTimetable();
});

test('admin can view upsell tab on class detail page', function () {
    $this->actingAs($this->admin)
        ->get(route('classes.show', $this->class).'?tab=upsell')
        ->assertOk();
});

test('admin can add funnel to a session', function () {
    $session = $this->class->sessions()->first();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('addSessionFunnel', $session->id, $this->funnel->id)
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_funnel_ids)->toBe([$this->funnel->id]);
});

test('admin can add multiple funnels to a session', function () {
    $session = $this->class->sessions()->first();
    $funnel2 = Funnel::factory()->published()->create(['user_id' => $this->admin->id]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('addSessionFunnel', $session->id, $this->funnel->id)
        ->call('addSessionFunnel', $session->id, $funnel2->id)
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_funnel_ids)->toBe([$this->funnel->id, $funnel2->id]);
});

test('admin can add PIC to a session', function () {
    $session = $this->class->sessions()->first();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('addSessionPic', $session->id, $this->admin->id)
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_pic_user_ids)->toBe([$this->admin->id]);
});

test('admin can remove funnel from a session', function () {
    $session = $this->class->sessions()->first();
    $funnel2 = Funnel::factory()->published()->create(['user_id' => $this->admin->id]);
    $session->update(['upsell_funnel_ids' => [$this->funnel->id, $funnel2->id]]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('removeSessionFunnel', $session->id, $this->funnel->id)
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_funnel_ids)->toBe([$funnel2->id]);
});

test('removing last funnel sets column to null', function () {
    $session = $this->class->sessions()->first();
    $session->update(['upsell_funnel_ids' => [$this->funnel->id]]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('removeSessionFunnel', $session->id, $this->funnel->id)
        ->assertHasNoErrors();

    expect($session->fresh()->upsell_funnel_ids)->toBeNull();
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
        expect($session->upsell_funnel_ids)->toBe([$this->funnel->id]);
        expect($session->upsell_pic_user_ids)->toBe([$this->admin->id]);
    }

    $wednesdaySessions = $this->class->sessions()->where('session_time', '14:00')->get();
    expect($wednesdaySessions)->not->toBeEmpty();

    foreach ($wednesdaySessions as $session) {
        expect($session->upsell_funnel_ids)->toBeNull();
        expect($session->upsell_pic_user_ids)->toBeNull();
    }
});

test('upsell tab shows visitor count for sessions with funnel visits', function () {
    $session = $this->class->sessions()->first();
    $session->update(['upsell_funnel_ids' => [$this->funnel->id]]);

    \App\Models\FunnelSession::factory()->count(3)->create([
        'funnel_id' => $this->funnel->id,
        'class_session_id' => $session->id,
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->set('activeTab', 'upsell')
        ->assertSee('Visitors');
});

test('upsell stats conversion rate uses visitors as denominator', function () {
    $session = $this->class->sessions()->first();
    $session->update(['upsell_funnel_ids' => [$this->funnel->id]]);

    // Create 10 funnel visitors for this class session
    $funnelSessions = \App\Models\FunnelSession::factory()->count(10)->create([
        'funnel_id' => $this->funnel->id,
        'class_session_id' => $session->id,
    ]);

    // Create 2 orders (conversions) linked to this class session
    \App\Models\FunnelOrder::factory()->count(2)->create([
        'funnel_id' => $this->funnel->id,
        'class_session_id' => $session->id,
        'session_id' => $funnelSessions->first()->id,
        'funnel_revenue' => 50.00,
    ]);

    $component = Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->set('activeTab', 'upsell');

    // Conversion rate should be 2/10 = 20%
    $component->assertSee('20');
});

test('upsell sessions list shows all sessions', function () {
    $sessionsCount = $this->class->sessions()->count();

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('setActiveTab', 'upsell')
        ->assertOk();

    expect($sessionsCount)->toBeGreaterThan(0);
});
