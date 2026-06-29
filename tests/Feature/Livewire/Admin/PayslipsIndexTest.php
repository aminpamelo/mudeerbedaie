<?php

declare(strict_types=1);

use App\Models\Payslip;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('renders the payslips list for a normal payslip', function () {
    $this->actingAs($this->admin);

    $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Active Teacher']);
    Payslip::factory()->create([
        'teacher_id' => $teacher->id,
        'generated_by' => $this->admin->id,
    ]);

    Volt::test('admin.payslips-index')
        ->assertStatus(200)
        ->assertSee('Active Teacher');
});

it('still renders payslips whose teacher has been soft-deleted', function () {
    $this->actingAs($this->admin);

    $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Soft Deleted Teacher']);
    Payslip::factory()->create([
        'teacher_id' => $teacher->id,
        'generated_by' => $this->admin->id,
    ]);

    // Soft-deleting the teacher leaves the payslip behind (the FK cascade does not
    // fire for soft deletes). Without withTrashed, $payslip->teacher resolves to null
    // and the page 500s on "Attempt to read property name on null".
    $teacher->delete();

    Volt::test('admin.payslips-index')
        ->assertStatus(200)
        ->assertSee('Soft Deleted Teacher');
});

it('still renders payslips whose generating admin has been soft-deleted', function () {
    $generatingAdmin = User::factory()->create(['role' => 'admin', 'name' => 'Former Admin']);
    $this->actingAs($this->admin);

    $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Active Teacher']);
    Payslip::factory()->create([
        'teacher_id' => $teacher->id,
        'generated_by' => $generatingAdmin->id,
    ]);

    $generatingAdmin->delete();

    Volt::test('admin.payslips-index')
        ->assertStatus(200)
        ->assertSee('Former Admin');
});

it('reports accurate status statistics in a single grouped query', function () {
    $this->actingAs($this->admin);

    // A distinct teacher per payslip avoids the (teacher_id, month) unique constraint.
    $make = function (string $status): void {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Payslip::factory()->{$status}()->create([
            'teacher_id' => $teacher->id,
            'generated_by' => $this->admin->id,
        ]);
    };

    foreach (['draft', 'draft', 'draft', 'finalized', 'finalized', 'paid'] as $status) {
        $make($status);
    }

    $stats = Volt::test('admin.payslips-index')->viewData('statistics');

    expect($stats)->toMatchArray([
        'total_payslips' => 6,
        'draft_payslips' => 3,
        'finalized_payslips' => 2,
        'paid_payslips' => 1,
    ]);
});
