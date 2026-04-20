<?php

use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Support\Facades\Bus;
use Livewire\Volt\Volt;

function enrolledStudent(ClassModel $class): Student
{
    $student = Student::factory()->create();
    $class->addStudent($student);

    return $student;
}

test('issueToClass creates records and dispatches a PDF generation batch', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $s1 = enrolledStudent($class);
    $s2 = enrolledStudent($class);

    $service = app(CertificateService::class);

    $result = $service->issueToClass($certificate, $class, [$s1->id, $s2->id]);

    expect($result['success'])->toBeTrue();
    expect($result['issued_count'])->toBe(2);
    expect($result['batch_id'])->not->toBeNull();
    expect($class->fresh()->certificate_pdf_batch_id)->toBe($result['batch_id']);

    // Records created synchronously (with null file_path — PDF queued)
    expect(CertificateIssue::where('class_id', $class->id)->count())->toBe(2);
    expect(CertificateIssue::where('class_id', $class->id)->whereNull('file_path')->count())->toBe(2);

    // One job per student dispatched via batch
    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2
            && $batch->jobs->every(fn ($job) => $job instanceof GenerateCertificatePdfJob);
    });
});

test('issueToClass skips students already issued when skipExisting is true', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();
    $student = enrolledStudent($class);

    CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    $result = app(CertificateService::class)
        ->issueToClass($certificate, $class, [$student->id], skipExisting: true);

    expect($result['skipped_count'])->toBe(1);
    expect($result['issued_count'])->toBe(0);
    expect($result['batch_id'])->toBeNull();

    Bus::assertNothingBatched();
});

test('issueToClass reissues by deleting old and creating new when skipExisting is false', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();
    $student = enrolledStudent($class);

    $oldIssue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    $result = app(CertificateService::class)
        ->issueToClass($certificate, $class, [$student->id], skipExisting: false);

    expect($result['success'])->toBeTrue();
    expect($result['issued_count'])->toBe(1);
    expect($result['reissued_count'])->toBe(1);
    expect($result['failed_count'])->toBe(0);

    // Old issue must be hard-deleted
    expect(CertificateIssue::find($oldIssue->id))->toBeNull();

    // Exactly one fresh issue must exist for the same student+class+cert
    expect(CertificateIssue::where('student_id', $student->id)
        ->where('certificate_id', $certificate->id)
        ->where('class_id', $class->id)
        ->count())->toBe(1);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
});

test('bulkIssueCertificates Livewire action queues a batch', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();
    $class->certificates()->attach($certificate->id);

    $s1 = enrolledStudent($class);
    $s2 = enrolledStudent($class);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedCertificateId', $certificate->id)
        ->set('selectedStudentIds', [(string) $s1->id, (string) $s2->id])
        ->call('bulkIssueCertificates')
        ->assertHasNoErrors()
        ->assertDispatched('notify', fn ($name, $data) => $data[0]['type'] === 'success');

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2);

    expect(CertificateIssue::where('class_id', $class->id)->count())->toBe(2);
});
