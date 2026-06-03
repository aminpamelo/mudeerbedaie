<?php

use App\Models\AttendanceLog;
use App\Models\AttendancePenalty;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Notifications\Hr\ShowCauseAutoIssuedAdminAlert;
use App\Notifications\Hr\ShowCauseLetterIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Storage::fake('public');

    LetterTemplate::factory()->create(['type' => 'show_cause', 'is_active' => true]);
});

/**
 * Give an employee N late arrivals (log + penalty) in a given month/year.
 */
function giveLateArrivals(Employee $employee, int $count, int $month = 11, int $year = 2090): void
{
    for ($i = 1; $i <= $count; $i++) {
        $log = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => sprintf('%04d-%02d-%02d', $year, $month, $i),
            'status' => 'late',
            'late_minutes' => 15,
        ]);

        AttendancePenalty::create([
            'employee_id' => $employee->id,
            'attendance_log_id' => $log->id,
            'penalty_type' => 'late_arrival',
            'penalty_minutes' => 15,
            'month' => $month,
            'year' => $year,
        ]);
    }
}

test('employee with three late arrivals gets a show cause case auto-issued', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    giveLateArrivals($employee, 3);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090])
        ->assertSuccessful();

    $action = DisciplinaryAction::where('employee_id', $employee->id)->first();

    expect($action)->not->toBeNull()
        ->and($action->type)->toBe('show_cause')
        ->and($action->status)->toBe('pending_response')
        ->and($action->response_required)->toBeTrue()
        ->and($action->source)->toBe(DisciplinaryAction::SOURCE_ATTENDANCE_LATE)
        ->and($action->source_period)->toBe('2090-11')
        ->and($action->issued_by)->toBeNull()
        ->and($action->response_deadline)->not->toBeNull();
});

test('employee below the threshold gets no case', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    giveLateArrivals($employee, 2);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090])
        ->assertSuccessful();

    expect(DisciplinaryAction::where('employee_id', $employee->id)->count())->toBe(0);
});

test('a custom threshold is respected', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    giveLateArrivals($employee, 3);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090, '--threshold' => 5])
        ->assertSuccessful();

    expect(DisciplinaryAction::where('employee_id', $employee->id)->count())->toBe(0);
});

test('running the command twice does not create duplicate cases', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    giveLateArrivals($employee, 3);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090])->assertSuccessful();
    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090])->assertSuccessful();

    expect(DisciplinaryAction::where('employee_id', $employee->id)->count())->toBe(1);
});

test('the show cause letter pdf is generated and stored', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    giveLateArrivals($employee, 3);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090])->assertSuccessful();

    $action = DisciplinaryAction::where('employee_id', $employee->id)->first();

    expect($action->letter_pdf_path)->not->toBeNull();
    Storage::disk('public')->assertExists($action->letter_pdf_path);
});

test('the employee and admins are notified', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employeeUser = User::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active', 'user_id' => $employeeUser->id]);
    giveLateArrivals($employee, 3);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090])->assertSuccessful();

    Notification::assertSentTo($employeeUser, ShowCauseLetterIssued::class);
    Notification::assertSentTo($admin, ShowCauseAutoIssuedAdminAlert::class);
});

test('the dry run reports without creating cases', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    giveLateArrivals($employee, 3);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090, '--dry-run' => true])
        ->assertSuccessful();

    expect(DisciplinaryAction::where('employee_id', $employee->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

test('inactive employees are skipped', function () {
    $employee = Employee::factory()->create(['status' => 'resigned']);
    giveLateArrivals($employee, 3);

    $this->artisan('hr:issue-late-show-cause', ['--month' => 11, '--year' => 2090])->assertSuccessful();

    expect(DisciplinaryAction::where('employee_id', $employee->id)->count())->toBe(0);
});
