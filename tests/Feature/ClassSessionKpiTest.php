<?php

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create necessary related models
    $teacher = Teacher::factory()->create();
    $course = Course::factory()->create();
    $class = ClassModel::factory()->create([
        'teacher_id' => $teacher->id,
        'course_id' => $course->id,
    ]);

    $this->class = $class;
});

test('session running longer than target meets KPI', function () {
    // Target: 90 minutes (1h 30m)
    // Actual: 101 minutes (1h 41m) - 11 minutes longer
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(101),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($session->meetsKpi())->toBeTrue()
        ->and($session->getDurationVarianceInMinutes())->toBe(11)
        ->and($session->duration_comparison)->toBe('+11m longer');
});

test('session running exactly on target meets KPI', function () {
    // Target: 90 minutes
    // Actual: 90 minutes - exact match
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(90),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($session->meetsKpi())->toBeTrue()
        ->and($session->getDurationVarianceInMinutes())->toBe(0)
        ->and($session->duration_comparison)->toBe('Exact match');
});

test('session running slightly shorter within tolerance meets KPI', function () {
    // Target: 90 minutes
    // Actual: 85 minutes - 5 minutes shorter (within 10-minute tolerance)
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(85),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($session->meetsKpi())->toBeTrue()
        ->and($session->getDurationVarianceInMinutes())->toBe(-5)
        ->and($session->duration_comparison)->toBe('5m shorter');
});

test('session running at exactly tolerance limit meets KPI', function () {
    // Target: 90 minutes
    // Actual: 80 minutes - 10 minutes shorter (exactly at tolerance limit)
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(80),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($session->meetsKpi())->toBeTrue()
        ->and($session->getDurationVarianceInMinutes())->toBe(-10);
});

test('session running significantly shorter outside tolerance misses KPI', function () {
    // Target: 90 minutes
    // Actual: 70 minutes - 20 minutes shorter (exceeds 10-minute tolerance)
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(70),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($session->meetsKpi())->toBeFalse()
        ->and($session->getDurationVarianceInMinutes())->toBe(-20)
        ->and($session->duration_comparison)->toBe('20m shorter');
});

test('incomplete session returns null for KPI status', function () {
    // Session that hasn't been completed yet
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'status' => 'scheduled',
        'started_at' => null,
        'completed_at' => null,
    ]);

    expect($session->meetsKpi())->toBeNull()
        ->and($session->getDurationVarianceInMinutes())->toBeNull();
});

test('ongoing session returns null for KPI status', function () {
    // Session that is currently ongoing
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'status' => 'ongoing',
        'started_at' => now()->subMinutes(30),
        'completed_at' => null,
    ]);

    expect($session->meetsKpi())->toBeNull()
        ->and($session->getDurationVarianceInMinutes())->toBeNull();
});

test('KPI status attribute returns correct values', function () {
    // Test 'met' status
    $metSession = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(95),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($metSession->kpi_status)->toBe('met');

    // Test 'missed' status
    $missedSession = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(70),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($missedSession->kpi_status)->toBe('missed');

    // Test 'pending' status
    $pendingSession = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'status' => 'scheduled',
    ]);

    expect($pendingSession->kpi_status)->toBe('pending');
});

test('custom tolerance parameter works correctly', function () {
    // Target: 90 minutes
    // Actual: 75 minutes - 15 minutes shorter
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(75),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    // With default 10-minute tolerance, should miss KPI
    expect($session->meetsKpi())->toBeFalse();

    // With custom 20-minute tolerance, should meet KPI
    expect($session->meetsKpi(20))->toBeTrue();

    // With custom 5-minute tolerance, should miss KPI
    expect($session->meetsKpi(5))->toBeFalse();
});

test('very long session meets KPI', function () {
    // Target: 90 minutes
    // Actual: 150 minutes - 60 minutes longer
    $session = ClassSession::factory()->create([
        'class_id' => $this->class->id,
        'duration_minutes' => 90,
        'started_at' => now()->subMinutes(150),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    expect($session->meetsKpi())->toBeTrue()
        ->and($session->getDurationVarianceInMinutes())->toBe(60)
        ->and($session->duration_comparison)->toBe('+60m longer');
});
