<?php

declare(strict_types=1);

use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can assign an order to classes from order detail page', function () {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $class1 = ClassModel::factory()->create(['status' => 'active']);
    $class2 = ClassModel::factory()->create(['status' => 'active']);

    $this->actingAs($admin);

    Volt::test('admin.orders.order-show', ['order' => $order])
        ->call('openAssignClassModal')
        ->assertSet('showAssignClassModal', true)
        ->call('toggleClassSelection', $class1->id)
        ->call('toggleClassSelection', $class2->id)
        ->call('assignToClasses');

    expect(ClassAssignmentApproval::where('product_order_id', $order->id)->count())->toBe(2);
    expect(ClassAssignmentApproval::where('product_order_id', $order->id)->first()->status)->toBe('pending');
});

test('admin can approve assignment from class approval list tab', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.class-show', ['class' => $class])
        ->call('setActiveTab', 'students')
        ->set('studentSubTab', 'approvals')
        ->call('approveAssignment', $approval->id);

    expect($approval->fresh()->status)->toBe('approved');
    expect(ClassStudent::where('class_id', $class->id)->where('student_id', $student->id)->exists())->toBeTrue();
});

test('admin can reject assignment from class approval list tab', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.class-show', ['class' => $class])
        ->call('setActiveTab', 'students')
        ->set('studentSubTab', 'approvals')
        ->call('rejectAssignment', $approval->id);

    expect($approval->fresh()->status)->toBe('rejected');
});

test('duplicate assignment to same class is prevented', function () {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $class = ClassModel::factory()->create(['status' => 'active']);

    ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Volt::test('admin.orders.order-show', ['order' => $order])
        ->call('openAssignClassModal')
        ->call('toggleClassSelection', $class->id)
        ->call('assignToClasses');

    // Should still be just 1 record (firstOrCreate prevents duplicate)
    expect(ClassAssignmentApproval::where('product_order_id', $order->id)->count())->toBe(1);
});
