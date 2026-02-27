<?php

use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('regeneratePdf generates PDF for certificate with null file_path on issued list', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $certificate = Certificate::factory()->active()->create();
    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'file_path' => null,
        'status' => 'issued',
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn('certificates/generated/2026/02/CERT-2026-0001.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-issued-list')
        ->call('regeneratePdf', $issue->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($issue->fresh()->file_path)->toBe('certificates/generated/2026/02/CERT-2026-0001.pdf');
});

test('regeneratePdf replaces existing PDF on issued list', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $certificate = Certificate::factory()->active()->create();
    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'file_path' => 'certificates/generated/old/old-file.pdf',
        'status' => 'issued',
    ]);

    Storage::disk('public')->put('certificates/generated/old/old-file.pdf', 'old-content');

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn('certificates/generated/2026/02/CERT-NEW.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-issued-list')
        ->call('regeneratePdf', $issue->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($issue->fresh()->file_path)->toBe('certificates/generated/2026/02/CERT-NEW.pdf');
    Storage::disk('public')->assertMissing('certificates/generated/old/old-file.pdf');
});

test('regeneratePdf logs the regeneration action', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $certificate = Certificate::factory()->active()->create();
    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'file_path' => null,
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn('certificates/generated/2026/02/test.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-issued-list')
        ->call('regeneratePdf', $issue->id);

    expect($issue->logs()->where('action', 'regenerated')->exists())->toBeTrue();
});

test('regeneratePdf dispatches error when PDF generation fails on issued list', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $certificate = Certificate::factory()->active()->create();
    $issue = CertificateIssue::factory()->create([
        'certificate_id' => $certificate->id,
        'file_path' => null,
    ]);

    $this->mock(CertificatePdfGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andThrow(new \Exception('Template rendering error'));
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-issued-list')
        ->call('regeneratePdf', $issue->id)
        ->assertDispatched('notify', fn ($name, $data) => $data[0]['type'] === 'error');

    expect($issue->fresh()->file_path)->toBeNull();
});

test('regeneratePdf works on class certificate management page', function () {
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
            ->andReturn('certificates/generated/2026/02/CERT-CLASS.pdf');
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('regeneratePdf', $issue->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($issue->fresh()->file_path)->toBe('certificates/generated/2026/02/CERT-CLASS.pdf');
});

test('revokeCertificate passes reason parameter on issued list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $issue = CertificateIssue::factory()->issued()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.certificate-issued-list')
        ->call('revokeCertificate', $issue->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($issue->fresh())
        ->status->toBe('revoked')
        ->revocation_reason->toBe('Revoked by admin');
});
