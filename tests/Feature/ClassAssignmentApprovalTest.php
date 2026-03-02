<?php

declare(strict_types=1);

use App\AcademicStatus;
use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can create a pending class assignment approval', function () {
    $approval = ClassAssignmentApproval::factory()->create();

    expect($approval->status)->toBe('pending')
        ->and($approval->class)->toBeInstanceOf(ClassModel::class)
        ->and($approval->student)->toBeInstanceOf(Student::class)
        ->and($approval->productOrder)->toBeInstanceOf(ProductOrder::class)
        ->and($approval->assignedByUser)->toBeInstanceOf(User::class)
        ->and($approval->approved_by)->toBeNull()
        ->and($approval->approved_at)->toBeNull();
});

test('approving an assignment enrolls student in class', function () {
    $class = ClassModel::factory()->create(['status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $admin = User::factory()->create();

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $approval->approve($admin);

    expect($approval->fresh()->status)->toBe('approved')
        ->and($approval->fresh()->approved_by)->toBe($admin->id)
        ->and($approval->fresh()->approved_at)->not->toBeNull();

    $classStudent = ClassStudent::where('class_id', $class->id)
        ->where('student_id', $student->id)
        ->first();

    expect($classStudent)->not->toBeNull()
        ->and($classStudent->status)->toBe('active')
        ->and($classStudent->order_id)->toBe($order->order_number);
});

test('approving with subscription creates course enrollment', function () {
    $course = Course::factory()->create();
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $admin = User::factory()->create();

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $approval->approve($admin, enrollWithSubscription: true);

    $enrollment = Enrollment::where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->academic_status)->toBe(AcademicStatus::ACTIVE)
        ->and($enrollment->payment_method_type)->toBe('manual');
});

test('approving with subscription does not duplicate existing enrollment', function () {
    $course = Course::factory()->create();
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $admin = User::factory()->create();

    // Pre-existing enrollment
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_status' => AcademicStatus::ACTIVE,
    ]);

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $approval->approve($admin, enrollWithSubscription: true);

    $enrollmentCount = Enrollment::where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->count();

    expect($enrollmentCount)->toBe(1);
});

test('rejecting an assignment updates status and notes', function () {
    $admin = User::factory()->create();
    $approval = ClassAssignmentApproval::factory()->create();

    $approval->reject($admin, 'Student does not meet requirements');

    expect($approval->fresh()->status)->toBe('rejected')
        ->and($approval->fresh()->approved_by)->toBe($admin->id)
        ->and($approval->fresh()->approved_at)->not->toBeNull()
        ->and($approval->fresh()->notes)->toBe('Student does not meet requirements');
});

test('scopes filter by status correctly', function () {
    ClassAssignmentApproval::factory()->count(3)->create(['status' => 'pending']);
    ClassAssignmentApproval::factory()->count(2)->approved()->create();
    ClassAssignmentApproval::factory()->count(1)->rejected()->create();

    expect(ClassAssignmentApproval::pending()->count())->toBe(3)
        ->and(ClassAssignmentApproval::approved()->count())->toBe(2)
        ->and(ClassAssignmentApproval::rejected()->count())->toBe(1);
});

test('product order has class assignment approvals relationship', function () {
    $order = ProductOrder::factory()->create();
    ClassAssignmentApproval::factory()->count(2)->create(['product_order_id' => $order->id]);

    expect($order->classAssignmentApprovals)->toHaveCount(2);
});

test('class model has pending approvals relationship', function () {
    $class = ClassModel::factory()->create();
    ClassAssignmentApproval::factory()->count(2)->create(['class_id' => $class->id, 'status' => 'pending']);
    ClassAssignmentApproval::factory()->approved()->create(['class_id' => $class->id]);

    expect($class->pendingApprovals)->toHaveCount(2)
        ->and($class->assignmentApprovals)->toHaveCount(3);
});
