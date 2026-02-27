<?php

use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('bulk revoke revokes all selected issued certificates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issues = CertificateIssue::factory()->issued()->count(3)->create([
        'class_id' => $class->id,
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', $issues->pluck('id')->map(fn ($id) => (string) $id)->toArray())
        ->call('bulkRevokeCertificates')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect(CertificateIssue::whereIn('id', $issues->pluck('id'))->where('status', 'revoked')->count())
        ->toBe(3);
});

test('bulk revoke skips already-revoked certificates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issued = CertificateIssue::factory()->issued()->create(['class_id' => $class->id]);
    $revoked = CertificateIssue::factory()->revoked()->create(['class_id' => $class->id]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issued->id, (string) $revoked->id])
        ->call('bulkRevokeCertificates')
        ->assertHasNoErrors();

    expect($issued->fresh()->status)->toBe('revoked');
    expect($revoked->fresh()->status)->toBe('revoked');
});

test('bulk reinstate reinstates all selected revoked certificates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issues = CertificateIssue::factory()->revoked()->count(2)->create([
        'class_id' => $class->id,
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', $issues->pluck('id')->map(fn ($id) => (string) $id)->toArray())
        ->call('bulkReinstateCertificates')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect(CertificateIssue::whereIn('id', $issues->pluck('id'))->where('status', 'issued')->count())
        ->toBe(2);
});

test('bulk revoke with empty selection does nothing', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [])
        ->call('bulkRevokeCertificates')
        ->assertHasNoErrors();
});

test('bulk revoke ignores IDs from other classes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $otherClass = ClassModel::factory()->create();
    $foreignIssue = CertificateIssue::factory()->issued()->create([
        'class_id' => $otherClass->id,
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $foreignIssue->id])
        ->call('bulkRevokeCertificates')
        ->assertHasNoErrors();

    expect($foreignIssue->fresh()->status)->toBe('issued');
});

test('bulk download returns zip when files exist', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    $issue1 = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/2026/02/CERT-2026-1001.pdf',
        'certificate_number' => 'CERT-2026-1001',
    ]);
    $issue2 = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/2026/02/CERT-2026-1002.pdf',
        'certificate_number' => 'CERT-2026-1002',
    ]);

    Storage::disk('public')->put($issue1->file_path, 'fake-pdf-1');
    Storage::disk('public')->put($issue2->file_path, 'fake-pdf-2');

    $component = Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue1->id, (string) $issue2->id])
        ->call('bulkDownloadCertificates');

    $component->assertHasNoErrors();
});

test('bulk download dispatches error when no files exist', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'file_path' => 'certificates/generated/2026/02/nonexistent.pdf',
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue->id])
        ->call('bulkDownloadCertificates')
        ->assertDispatched('notify');
});

test('selection is cleared when filter status changes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issue = CertificateIssue::factory()->issued()->create(['class_id' => $class->id]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issue->id])
        ->set('selectAllIssued', true)
        ->set('filterStatus', 'revoked')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false);
});

test('inline edit updates student name and data snapshot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'data_snapshot' => ['student_name' => 'Old Name'],
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('startEditingName', $issue->id)
        ->assertSet('editingNameIssueId', $issue->id)
        ->set('editingName', 'New Student Name')
        ->call('saveStudentName')
        ->assertSet('editingNameIssueId', null)
        ->assertSet('editingName', '')
        ->assertDispatched('notify');

    expect($issue->student->user->fresh()->name)->toBe('New Student Name');
    expect($issue->fresh()->data_snapshot['student_name'])->toBe('New Student Name');
});

test('cancel editing name resets state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issue = CertificateIssue::factory()->issued()->create(['class_id' => $class->id]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('startEditingName', $issue->id)
        ->assertSet('editingNameIssueId', $issue->id)
        ->call('cancelEditingName')
        ->assertSet('editingNameIssueId', null)
        ->assertSet('editingName', '');
});
