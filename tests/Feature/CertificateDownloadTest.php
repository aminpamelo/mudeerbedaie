<?php

use App\Models\CertificateIssue;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('certificate download returns file when it exists on public disk', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $student = \App\Models\Student::factory()->create(['phone' => '60123456789']);

    $certificateIssue = CertificateIssue::factory()->create([
        'student_id' => $student->id,
        'file_path' => 'certificates/generated/2026/02/CERT-2026-0001.pdf',
        'certificate_number' => 'CERT-2026-0001',
        'status' => 'issued',
        'data_snapshot' => ['student_name' => 'Ahmad Amin'],
    ]);

    Storage::disk('public')->put($certificateIssue->file_path, 'fake-pdf-content');

    $response = $this->get(route('certificates.download', $certificateIssue));

    $response->assertSuccessful();
    $response->assertDownload('Ahmad_Amin_60123456789_CERT-2026-0001.pdf');
});

test('certificate download returns 404 when file does not exist', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $certificateIssue = CertificateIssue::factory()->create([
        'file_path' => 'certificates/generated/2026/02/nonexistent.pdf',
        'certificate_number' => 'CERT-2026-0002',
        'status' => 'issued',
    ]);

    $response = $this->get(route('certificates.download', $certificateIssue));

    $response->assertNotFound();
});

test('certificate download returns 404 when file_path is empty', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $certificateIssue = CertificateIssue::factory()->create([
        'file_path' => null,
        'certificate_number' => 'CERT-2026-0003',
        'status' => 'issued',
    ]);

    $response = $this->get(route('certificates.download', $certificateIssue));

    $response->assertNotFound();
});

test('hasFile returns false when file does not exist on disk', function () {
    Storage::fake('public');

    $certificateIssue = CertificateIssue::factory()->create([
        'file_path' => 'certificates/generated/2026/02/CERT-TEMP.pdf',
    ]);

    expect($certificateIssue->hasFile())->toBeFalse();
});

test('hasFile returns true when file exists on public disk', function () {
    Storage::fake('public');

    $certificateIssue = CertificateIssue::factory()->create([
        'file_path' => 'certificates/generated/2026/02/CERT-2026-0005.pdf',
    ]);

    Storage::disk('public')->put($certificateIssue->file_path, 'fake-pdf-content');

    expect($certificateIssue->hasFile())->toBeTrue();
});

test('revoked certificate can be reinstated', function () {
    $user = User::factory()->create();
    $certificateIssue = CertificateIssue::factory()->revoked()->create();

    expect($certificateIssue->canBeReinstated())->toBeTrue();

    $certificateIssue->reinstate($user);

    expect($certificateIssue->fresh())
        ->status->toBe('issued')
        ->revoked_at->toBeNull()
        ->revoked_by->toBeNull()
        ->revocation_reason->toBeNull();
});

test('issued certificate cannot be reinstated', function () {
    $user = User::factory()->create();
    $certificateIssue = CertificateIssue::factory()->issued()->create();

    expect($certificateIssue->canBeReinstated())->toBeFalse();

    expect(fn () => $certificateIssue->reinstate($user))
        ->toThrow(\Exception::class, 'Certificate cannot be reinstated.');
});

test('reinstate logs the action', function () {
    $user = User::factory()->create();
    $certificateIssue = CertificateIssue::factory()->revoked()->create();

    $certificateIssue->reinstate($user);

    expect($certificateIssue->logs()->where('action', 'reinstated')->exists())->toBeTrue();
});

test('getDownloadFilename includes student name phone and certificate number', function () {
    $student = \App\Models\Student::factory()->create(['phone' => '60198765432']);

    $certificateIssue = CertificateIssue::factory()->create([
        'student_id' => $student->id,
        'certificate_number' => 'CERT-2026-0050',
        'data_snapshot' => ['student_name' => 'Muhammad Ali bin Abu'],
    ]);

    expect($certificateIssue->getDownloadFilename())
        ->toBe('Muhammad_Ali_bin_Abu_60198765432_CERT-2026-0050.pdf');
});
