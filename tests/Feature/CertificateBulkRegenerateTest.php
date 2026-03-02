<?php

use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('bulkRegeneratePdfs regenerates selected certificates on class management page', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $issue1 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/old/old1.pdf',
        'status' => 'issued',
    ]);

    $issue2 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/old/old2.pdf',
        'status' => 'issued',
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->twice()
            ->andReturn('certificates/generated/2026/03/CERT-NEW.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue1->id, (string) $issue2->id])
        ->call('bulkRegeneratePdfs')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($issue1->fresh()->file_path)->toBe('certificates/generated/2026/03/CERT-NEW.pdf');
    expect($issue2->fresh()->file_path)->toBe('certificates/generated/2026/03/CERT-NEW.pdf');
});

test('bulkRegeneratePdfs logs regeneration actions', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'file_path' => null,
        'status' => 'issued',
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn('certificates/generated/2026/03/test.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue->id])
        ->call('bulkRegeneratePdfs');

    expect($issue->logs()->where('action', 'regenerated')->exists())->toBeTrue();
});

test('bulkRegeneratePdfs clears selection after completion', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'status' => 'issued',
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn('certificates/generated/2026/03/test.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue->id])
        ->call('bulkRegeneratePdfs')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false);
});

test('bulkRegeneratePdfs does nothing when no certificates selected', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [])
        ->call('bulkRegeneratePdfs')
        ->assertNotDispatched('notify');
});

test('regenerateAllPdfs regenerates all issued certificates for the class', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $certificate = Certificate::factory()->active()->create();

    $issue1 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/old/old1.pdf',
        'status' => 'issued',
    ]);

    $issue2 = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/old/old2.pdf',
        'status' => 'issued',
    ]);

    // Revoked certificate should NOT be regenerated
    $revokedIssue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/old/old3.pdf',
        'status' => 'revoked',
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->twice()
            ->andReturn('certificates/generated/2026/03/CERT-REGEN.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('regenerateAllPdfs')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($issue1->fresh()->file_path)->toBe('certificates/generated/2026/03/CERT-REGEN.pdf');
    expect($issue2->fresh()->file_path)->toBe('certificates/generated/2026/03/CERT-REGEN.pdf');
    // Revoked should remain unchanged
    expect($revokedIssue->fresh()->file_path)->toBe('certificates/generated/old/old3.pdf');
});

test('regenerateAllPdfs shows info when no issued certificates exist', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('regenerateAllPdfs')
        ->assertDispatched('notify', fn ($name, $data) => $data[0]['type'] === 'info');
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
