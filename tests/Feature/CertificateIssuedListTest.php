<?php

declare(strict_types=1);

use App\Models\CertificateIssue;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('issued certificates page loads successfully', function () {
    $this->get(route('certificates.issued'))
        ->assertSuccessful();
});

test('issued certificates page displays stats', function () {
    Volt::test('admin.certificates.certificate-issued-list')
        ->assertSeeHtml('Total Issued')
        ->assertSeeHtml('Active')
        ->assertSeeHtml('Revoked')
        ->assertSuccessful();
});

test('issued certificates search works', function () {
    Volt::test('admin.certificates.certificate-issued-list')
        ->set('search', 'CERT-2026')
        ->assertSuccessful();
});

test('issued certificates status filter works', function () {
    Volt::test('admin.certificates.certificate-issued-list')
        ->set('statusFilter', 'issued')
        ->assertSuccessful()
        ->set('statusFilter', 'revoked')
        ->assertSuccessful();
});

test('issued certificates clear filters resets all filters', function () {
    Volt::test('admin.certificates.certificate-issued-list')
        ->set('search', 'test')
        ->set('statusFilter', 'issued')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('statusFilter', '')
        ->assertSet('certificateFilter', null)
        ->assertSet('sortBy', 'latest')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false)
        ->assertSuccessful();
});

test('select all issued checkboxes selects current page items', function () {
    CertificateIssue::factory()->count(3)->create();

    Volt::test('admin.certificates.certificate-issued-list')
        ->set('selectAllIssued', true)
        ->assertNotSet('selectedIssueIds', [])
        ->assertSuccessful();
});

test('bulk revoke certificates works', function () {
    $issues = CertificateIssue::factory()->issued()->count(2)->create();

    $ids = $issues->pluck('id')->map(fn ($id) => (string) $id)->toArray();

    Volt::test('admin.certificates.certificate-issued-list')
        ->set('selectedIssueIds', $ids)
        ->call('bulkRevokeCertificates')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false)
        ->assertSuccessful();

    foreach ($issues as $issue) {
        expect($issue->fresh()->status)->toBe('revoked');
    }
});

test('bulk reinstate certificates works', function () {
    $issues = CertificateIssue::factory()->revoked()->count(2)->create();

    $ids = $issues->pluck('id')->map(fn ($id) => (string) $id)->toArray();

    Volt::test('admin.certificates.certificate-issued-list')
        ->set('selectedIssueIds', $ids)
        ->call('bulkReinstateCertificates')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false)
        ->assertSuccessful();

    foreach ($issues as $issue) {
        expect($issue->fresh()->status)->toBe('issued');
    }
});

test('bulk delete certificates works', function () {
    $issues = CertificateIssue::factory()->count(2)->create();

    $ids = $issues->pluck('id')->map(fn ($id) => (string) $id)->toArray();

    Volt::test('admin.certificates.certificate-issued-list')
        ->set('selectedIssueIds', $ids)
        ->call('bulkDeleteCertificates')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false)
        ->assertSuccessful();

    foreach ($issues as $issue) {
        expect(CertificateIssue::find($issue->id))->toBeNull();
    }
});

test('reinstate individual certificate works', function () {
    $issue = CertificateIssue::factory()->revoked()->create();

    Volt::test('admin.certificates.certificate-issued-list')
        ->call('reinstateCertificate', $issue->id)
        ->assertSuccessful();

    expect($issue->fresh()->status)->toBe('issued');
});

test('filter changes reset selections', function () {
    CertificateIssue::factory()->count(2)->create();

    Volt::test('admin.certificates.certificate-issued-list')
        ->set('selectAllIssued', true)
        ->set('statusFilter', 'issued')
        ->assertSet('selectedIssueIds', [])
        ->assertSet('selectAllIssued', false)
        ->assertSuccessful();
});
