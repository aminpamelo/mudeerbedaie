<?php

declare(strict_types=1);

use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('approval search filters by student name', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    $studentUser1 = User::factory()->create(['name' => 'Ahmad Fahmi']);
    $student1 = Student::factory()->create(['user_id' => $studentUser1->id]);
    $order1 = ProductOrder::factory()->create(['student_id' => $student1->id]);

    $studentUser2 = User::factory()->create(['name' => 'Siti Aminah']);
    $student2 = Student::factory()->create(['user_id' => $studentUser2->id]);
    $order2 = ProductOrder::factory()->create(['student_id' => $student2->id]);

    ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student1->id,
        'product_order_id' => $order1->id,
    ]);

    ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student2->id,
        'product_order_id' => $order2->id,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.class-show', ['class' => $class])
        ->set('approvalSearch', 'Ahmad');

    $approvals = $component->instance()->pendingApprovals;

    expect($approvals)->toHaveCount(1)
        ->and($approvals->first()->student_id)->toBe($student1->id);
});

test('approval search filters by order number', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    $student1 = Student::factory()->create();
    $order1 = ProductOrder::factory()->create([
        'student_id' => $student1->id,
        'order_number' => 'AGT-UNIQUE123',
    ]);

    $student2 = Student::factory()->create();
    $order2 = ProductOrder::factory()->create([
        'student_id' => $student2->id,
        'order_number' => 'AGT-OTHER456',
    ]);

    ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student1->id,
        'product_order_id' => $order1->id,
    ]);

    ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student2->id,
        'product_order_id' => $order2->id,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.class-show', ['class' => $class])
        ->set('approvalSearch', 'UNIQUE123');

    $approvals = $component->instance()->pendingApprovals;

    expect($approvals)->toHaveCount(1)
        ->and($approvals->first()->product_order_id)->toBe($order1->id);
});

test('approval search clears selected approval ids', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.class-show', ['class' => $class])
        ->set('selectedApprovalIds', [$approval->id])
        ->assertSet('selectedApprovalIds', [$approval->id])
        ->set('approvalSearch', 'test')
        ->assertSet('selectedApprovalIds', []);
});

test('approval search with no match returns empty results', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.class-show', ['class' => $class])
        ->set('approvalSearch', 'nonexistentstudentxyz');

    $approvals = $component->instance()->pendingApprovals;

    expect($approvals)->toHaveCount(0);
});

test('approval search with empty string returns all pending approvals', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);

    ClassAssignmentApproval::factory()->count(3)->create([
        'class_id' => $class->id,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.class-show', ['class' => $class])
        ->set('approvalSearch', '');

    $approvals = $component->instance()->pendingApprovals;

    expect($approvals)->toHaveCount(3);
});
