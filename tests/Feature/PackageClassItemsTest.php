<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can add a class item to a package', function () {
    $course = Course::factory()->create(['status' => 'active']);
    CourseFeeSettings::factory()->create(['course_id' => $course->id, 'fee_amount' => 150.00]);
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $package = Package::factory()->create();

    $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'original_price' => 150.00,
        'sort_order' => 0,
    ]);

    expect($package->classes)->toHaveCount(1);
    expect($package->classes->first()->id)->toBe($class->id);
});

it('correctly identifies class package items', function () {
    $class = ClassModel::factory()->create(['status' => 'active']);
    $package = Package::factory()->create();

    $item = $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'original_price' => 100.00,
        'sort_order' => 0,
    ]);

    expect($item->isClass())->toBeTrue();
    expect($item->isProduct())->toBeFalse();
    expect($item->isCourse())->toBeFalse();
});

it('calculates effective price for class items using parent course fee', function () {
    $course = Course::factory()->create(['status' => 'active']);
    CourseFeeSettings::factory()->create(['course_id' => $course->id, 'fee_amount' => 200.00]);
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $package = Package::factory()->create();

    $item = $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'original_price' => 200.00,
        'sort_order' => 0,
    ]);

    expect($item->getEffectivePrice())->toBe(200.0);
});

it('uses custom price for class items when set', function () {
    $course = Course::factory()->create(['status' => 'active']);
    CourseFeeSettings::factory()->create(['course_id' => $course->id, 'fee_amount' => 200.00]);
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $package = Package::factory()->create();

    $item = $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'custom_price' => 120.00,
        'original_price' => 200.00,
        'sort_order' => 0,
    ]);

    expect($item->getEffectivePrice())->toBe(120.0);
});

it('includes class items in package original price calculation', function () {
    $course = Course::factory()->create(['status' => 'active']);
    CourseFeeSettings::factory()->create(['course_id' => $course->id, 'fee_amount' => 150.00]);
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $package = Package::factory()->create(['price' => 100.00]);

    $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'original_price' => 150.00,
        'sort_order' => 0,
    ]);

    // Reload relationships
    $package->load(['classes.course.feeSettings']);

    expect($package->calculateOriginalPrice())->toBe(150.0);
});

it('displays class name with course name', function () {
    $course = Course::factory()->create(['name' => 'Math 101', 'status' => 'active']);
    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'title' => 'Batch A',
        'status' => 'active',
    ]);
    $package = Package::factory()->create();

    $item = $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'original_price' => 100.00,
        'sort_order' => 0,
    ]);

    expect($item->getDisplayName())->toBe('Batch A (Math 101)');
});

it('creates course enrollment and class assignment on package purchase with class items', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['status' => 'active']);
    CourseFeeSettings::factory()->create(['course_id' => $course->id, 'fee_amount' => 150.00]);
    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'status' => 'active',
        'max_capacity' => 30,
    ]);
    $package = Package::factory()->create(['price' => 100.00, 'track_stock' => false]);

    $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'original_price' => 150.00,
        'sort_order' => 0,
    ]);

    $purchase = PackagePurchase::create([
        'package_id' => $package->id,
        'user_id' => $user->id,
        'amount_paid' => 100.00,
        'original_amount' => 150.00,
        'status' => 'processing',
        'payment_method' => 'stripe',
        'purchased_at' => now(),
    ]);

    $result = $purchase->markAsCompleted();

    expect($result)->toBeTrue();

    // Verify enrollment was created
    $student = Student::where('user_id', $user->id)->first();
    expect($student)->not->toBeNull();

    $enrollment = Enrollment::where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->status)->toBe('enrolled');

    // Verify student was assigned to the class
    expect($class->students()->where('students.id', $student->id)->exists())->toBeTrue();
});

it('does not duplicate enrollment when package has both course and class for same course', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['status' => 'active']);
    CourseFeeSettings::factory()->create(['course_id' => $course->id, 'fee_amount' => 150.00]);
    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'status' => 'active',
        'max_capacity' => 30,
    ]);
    $package = Package::factory()->create(['price' => 200.00, 'track_stock' => false]);

    // Add course item
    $package->items()->create([
        'itemable_type' => Course::class,
        'itemable_id' => $course->id,
        'quantity' => 1,
        'original_price' => 150.00,
        'sort_order' => 0,
    ]);

    // Add class item (same course)
    $package->items()->create([
        'itemable_type' => ClassModel::class,
        'itemable_id' => $class->id,
        'quantity' => 1,
        'original_price' => 150.00,
        'sort_order' => 1,
    ]);

    $purchase = PackagePurchase::create([
        'package_id' => $package->id,
        'user_id' => $user->id,
        'amount_paid' => 200.00,
        'original_amount' => 300.00,
        'status' => 'processing',
        'payment_method' => 'stripe',
        'purchased_at' => now(),
    ]);

    $purchase->markAsCompleted();

    $student = Student::where('user_id', $user->id)->first();

    // Should only have one enrollment for the course, not two
    $enrollmentCount = Enrollment::where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->count();
    expect($enrollmentCount)->toBe(1);

    // But should still be assigned to the class
    expect($class->students()->where('students.id', $student->id)->exists())->toBeTrue();
});
