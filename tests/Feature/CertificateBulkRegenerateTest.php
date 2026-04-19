<?php

use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('bulkRegeneratePdfs dispatches a batch of PDF jobs for selected certificates', function () {
    Bus::fake();
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $issue1 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    $issue2 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue1->id, (string) $issue2->id])
        ->call('bulkRegeneratePdfs')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    Bus::assertBatched(function ($batch) use ($issue1, $issue2) {
        return $batch->jobs->count() === 2
            && $batch->jobs->pluck('certificateIssueId')->sort()->values()->all() === collect([$issue1->id, $issue2->id])->sort()->values()->all();
    });

    expect($class->fresh()->certificate_pdf_batch_id)->not->toBeNull();
});

test('bulkRegeneratePdfs clears selection after completion', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue->id])
        ->call('bulkRegeneratePdfs')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false);
});

test('bulkRegeneratePdfs does nothing when no certificates selected', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [])
        ->call('bulkRegeneratePdfs')
        ->assertNotDispatched('notify');

    Bus::assertNothingBatched();
});

test('regenerateAllPdfs dispatches a batch for all issued certificates in class', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $issue1 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    $issue2 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    // Revoked certificate should NOT be queued
    CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'revoked',
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('regenerateAllPdfs')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    Bus::assertBatched(function ($batch) use ($issue1, $issue2) {
        return $batch->jobs->count() === 2
            && $batch->jobs->pluck('certificateIssueId')->sort()->values()->all() === collect([$issue1->id, $issue2->id])->sort()->values()->all();
    });
});

test('regenerateAllPdfs shows info when no issued certificates exist', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('regenerateAllPdfs')
        ->assertDispatched('notify', fn ($name, $data) => $data[0]['type'] === 'info');

    Bus::assertNothingBatched();
});

test('bulk regenerate refuses to start while another batch is active', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create([
        'certificate_pdf_batch_id' => 'fake-batch-id',
    ]);
    $certificate = Certificate::factory()->active()->create();

    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    // Stub Bus::findBatch to return a batch-like object that reports itself as still running
    $fakeBatch = new class
    {
        public string $id = 'fake-batch-id';

        public ?string $name = 'Fake batch';

        public int $totalJobs = 10;

        public int $pendingJobs = 5;

        public int $failedJobs = 0;

        public function processedJobs(): int
        {
            return 5;
        }

        public function progress(): int
        {
            return 50;
        }

        public function finished(): bool
        {
            return false;
        }

        public function cancelled(): bool
        {
            return false;
        }
    };

    Bus::shouldReceive('findBatch')
        ->with('fake-batch-id')
        ->andReturn($fakeBatch);
    Bus::shouldReceive('batch')->never();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue->id])
        ->call('bulkRegeneratePdfs')
        ->assertDispatched('notify', fn ($name, $data) => $data[0]['type'] === 'warning');
});

test('GenerateCertificatePdfJob generates PDF and updates file_path', function () {
    Storage::fake('public');

    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();
    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
        'file_path' => null,
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn('certificates/generated/2026/04/CERT-NEW.pdf');
    });

    (new GenerateCertificatePdfJob($issue->id, actorUserId: null, logAction: 'regenerated'))
        ->handle(app(CertificatePdfGenerator::class));

    expect($issue->fresh()->file_path)->toBe('certificates/generated/2026/04/CERT-NEW.pdf');
    expect($issue->logs()->where('action', 'regenerated')->exists())->toBeTrue();
});

test('saving certificate template warns about issued certificates needing regeneration', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $certificate = Certificate::factory()->active()->create();

    CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'status' => 'issued',
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-edit', ['certificate' => $certificate])
        ->call('save')
        ->assertDispatched('notify', fn ($name, $data) => $data[0]['type'] === 'warning' && str_contains($data[0]['message'], 'still use the old design'));
});

test('saving certificate template shows success when no issued certificates exist', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $certificate = Certificate::factory()->active()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-edit', ['certificate' => $certificate])
        ->call('save')
        ->assertDispatched('notify', fn ($name, $data) => $data[0]['type'] === 'success');
});

test('regenerateAllIssuedPdfs on certificate-edit regenerates all issued certificates for a template', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $certificate = Certificate::factory()->active()->create();

    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'file_path' => 'certificates/generated/old/old.pdf',
        'status' => 'issued',
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn('certificates/generated/2026/03/CERT-UPDATED.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-edit', ['certificate' => $certificate])
        ->call('regenerateAllIssuedPdfs')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($issue->fresh()->file_path)->toBe('certificates/generated/2026/03/CERT-UPDATED.pdf');
});
