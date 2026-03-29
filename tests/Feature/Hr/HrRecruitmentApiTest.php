<?php

declare(strict_types=1);

use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createRecruitmentAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createRecruitmentSetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);

    return compact('department', 'position', 'admin');
}

test('unauthenticated users get 401 on recruitment endpoints', function () {
    $this->getJson('/api/hr/recruitment/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/recruitment/postings')->assertUnauthorized();
    $this->getJson('/api/hr/recruitment/applicants')->assertUnauthorized();
});

test('admin can create job posting', function () {
    $setup = createRecruitmentSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/recruitment/postings', [
        'title' => 'Software Engineer',
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
        'description' => 'We are looking for a software engineer.',
        'requirements' => 'PHP, Laravel, React',
        'employment_type' => 'full_time',
        'vacancies' => 2,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Software Engineer');
});

test('admin can list job postings', function () {
    $admin = createRecruitmentAdmin();
    JobPosting::factory()->count(3)->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->getJson('/api/hr/recruitment/postings');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin can publish job posting', function () {
    $admin = createRecruitmentAdmin();
    $posting = JobPosting::factory()->create(['created_by' => $admin->id, 'status' => 'draft']);

    $response = $this->actingAs($admin)->patchJson("/api/hr/recruitment/postings/{$posting->id}/publish");

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'open');
});

test('admin can add applicant manually', function () {
    Storage::fake('public');
    $setup = createRecruitmentSetup();
    $posting = JobPosting::factory()->open()->create(['created_by' => $setup['admin']->id]);

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/recruitment/applicants', [
        'job_posting_id' => $posting->id,
        'full_name' => 'Ahmad Ali',
        'email' => 'ahmad@example.com',
        'phone' => '0123456789',
        'source' => 'referral',
        'resume' => UploadedFile::fake()->create('resume.pdf', 500),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.full_name', 'Ahmad Ali');
});

test('admin can move applicant stage', function () {
    $admin = createRecruitmentAdmin();
    $applicant = Applicant::factory()->create();

    $response = $this->actingAs($admin)->patchJson("/api/hr/recruitment/applicants/{$applicant->id}/stage", [
        'stage' => 'screening',
        'notes' => 'Resume looks good',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.current_stage', 'screening');
});

test('admin can schedule interview', function () {
    $setup = createRecruitmentSetup();
    $employee = Employee::factory()->create(['department_id' => $setup['department']->id, 'position_id' => $setup['position']->id]);
    $applicant = Applicant::factory()->create();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/recruitment/interviews', [
        'applicant_id' => $applicant->id,
        'interviewer_id' => $employee->id,
        'interview_date' => now()->addDays(7)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'type' => 'in_person',
    ]);

    $response->assertCreated();
});

test('public careers page lists only open positions', function () {
    JobPosting::factory()->create(['status' => 'draft']);
    JobPosting::factory()->open()->create();

    $response = $this->getJson('/api/careers');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('public can apply to open position', function () {
    Storage::fake('public');
    $posting = JobPosting::factory()->open()->create();

    $response = $this->postJson("/api/careers/{$posting->id}/apply", [
        'full_name' => 'Test Applicant',
        'email' => 'test@example.com',
        'phone' => '0123456789',
        'resume' => UploadedFile::fake()->create('resume.pdf', 500),
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['applicant_number']]);
});

test('admin can get recruitment dashboard stats', function () {
    $admin = createRecruitmentAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/recruitment/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['open_positions', 'total_applicants', 'pipeline']]);
});
