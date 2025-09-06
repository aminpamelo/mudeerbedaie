<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test user and student
    $this->user = User::factory()->create([
        'role' => 'student',
        'email' => 'student@example.com',
    ]);

    $this->student = Student::factory()->create([
        'user_id' => $this->user->id,
    ]);

    // Create course and fee settings
    $this->course = Course::factory()->create([
        'name' => 'Test Course',
        'status' => 'active',
    ]);

    $this->feeSettings = CourseFeeSettings::factory()->create([
        'course_id' => $this->course->id,
        'fee_amount' => 100.00,
        'billing_cycle' => 'monthly',
        'currency' => 'MYR',
    ]);

    // Create enrollment with subscription
    $this->enrollment = Enrollment::factory()->create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'status' => 'enrolled',
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
        'collection_status' => 'active',
    ]);
});

test('it can detect active collection status', function () {
    expect($this->enrollment->isCollectionActive())->toBeTrue();
    expect($this->enrollment->isCollectionPaused())->toBeFalse();
    expect($this->enrollment->getCollectionStatusLabel())->toBe('Active');
});

test('it can pause collection status', function () {
    $this->enrollment->pauseCollection();

    expect($this->enrollment->isCollectionPaused())->toBeTrue();
    expect($this->enrollment->isCollectionActive())->toBeFalse();
    expect($this->enrollment->getCollectionStatusLabel())->toBe('Collection Paused');
    expect($this->enrollment->collection_paused_at)->not->toBeNull();
});

test('it can resume collection status', function () {
    // First pause collection
    $this->enrollment->pauseCollection();
    expect($this->enrollment->isCollectionPaused())->toBeTrue();

    // Then resume collection
    $this->enrollment->resumeCollection();

    expect($this->enrollment->isCollectionActive())->toBeTrue();
    expect($this->enrollment->isCollectionPaused())->toBeFalse();
    expect($this->enrollment->getCollectionStatusLabel())->toBe('Active');
    expect($this->enrollment->collection_paused_at)->toBeNull();
});

test('it shows proper full status description', function () {
    // Active subscription with active collection
    expect($this->enrollment->getFullStatusDescription())->toBe('Active');

    // Active subscription with paused collection
    $this->enrollment->pauseCollection();
    expect($this->enrollment->getFullStatusDescription())->toBe('Active (Collection Paused)');

    // Canceled subscription (collection status doesn't matter)
    $this->enrollment->updateSubscriptionStatus('canceled');
    expect($this->enrollment->getFullStatusDescription())->toBe('Canceled');
});

test('student can view subscriptions with collection status', function () {
    $this->actingAs($this->user)
        ->get('/my/subscriptions')
        ->assertStatus(200)
        ->assertSee($this->course->name)
        ->assertSee('Active'); // Should see active status
});

test('student can view paused collection status', function () {
    // Pause the collection
    $this->enrollment->pauseCollection();

    $this->actingAs($this->user)
        ->get('/my/subscriptions')
        ->assertStatus(200)
        ->assertSee($this->course->name)
        ->assertSee('Active (Collection Paused)'); // Should see paused collection status
});

test('enrollment model correctly handles collection status updates', function () {
    // Test updateCollectionStatus method
    $pausedAt = now();
    $this->enrollment->updateCollectionStatus('paused', $pausedAt);

    expect($this->enrollment->collection_status)->toBe('paused');
    expect($this->enrollment->collection_paused_at->format('Y-m-d H:i:s'))->toBe($pausedAt->format('Y-m-d H:i:s'));

    // Test resuming
    $this->enrollment->updateCollectionStatus('active', null);

    expect($this->enrollment->collection_status)->toBe('active');
    expect($this->enrollment->collection_paused_at)->toBeNull();
});

test('formatted collection paused date returns null when not paused', function () {
    expect($this->enrollment->getFormattedCollectionPausedDate())->toBeNull();
});

test('formatted collection paused date returns formatted string when paused', function () {
    $this->enrollment->pauseCollection();

    $formattedDate = $this->enrollment->getFormattedCollectionPausedDate();

    expect($formattedDate)->not->toBeNull();
    expect($formattedDate)->toBeString();
    expect($formattedDate)->toContain(date('M')); // Should contain current month
});
