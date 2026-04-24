<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/*
 * NOTE — Pest Browser plugin limitation (as of v4):
 *
 * `LaravelHttpServer::handleRequest()` only parses the request body for
 * `application/x-www-form-urlencoded` content types. For `multipart/form-data`
 * submissions, the uploaded fields never make it into the Laravel request —
 * see `vendor/pestphp/pest-plugin-browser/src/Drivers/LaravelHttpServer.php`
 * line 244 and the `// @TODO files...` comment on line 257.
 *
 * The public recruitment form uses `enctype="multipart/form-data"` because
 * candidates may attach a resume (PDF/DOC/DOCX). As a result, the final
 * submission step of this end-to-end flow (admin builds custom field →
 * candidate fills public form → admin reviews) cannot reliably exercise
 * the POST through Chromium: any `form_data` value would be dropped at the
 * server boundary. Full coverage of the schema round-trip (custom field
 * appears on public form, submission saves to `form_data`, snapshot is
 * preserved per applicant) already exists at
 * `tests/Feature/LiveHost/Recruitment/FormBuilderIntegrationTest.php`,
 * which drives the same flow through Laravel's normal testing request
 * pipeline.
 *
 * The test is kept here as a scaffold so it can be re-enabled the moment
 * the Pest Browser plugin adds multipart parsing support.
 */
it('admin builds a custom field and candidate sees it on the public form', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost']);
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create([
        'title' => 'Hiring TikTok Live Hosts April 2026',
    ]);

    // Admin: add a custom "TikTok handle" field via the form builder UI.
    // The full-fidelity admin flow would click into the Application form tab,
    // add a text field, rename it, and save — skipped because the Pest
    // Browser plugin multipart parsing limitation prevents the submit round
    // trip at the candidate step below. Schema round-trip coverage already
    // lives at tests/Feature/LiveHost/Recruitment/FormBuilderIntegrationTest.php.
    $adminPage = visit(route('livehost.recruitment.campaigns.edit', $campaign))
        ->actingAs($admin);

    // Candidate: visit public form and submit.
    $publicPage = visit(route('recruitment.show', $campaign->slug));
    $publicPage->assertSee($campaign->title)->assertNoJavascriptErrors();

    expect(LiveHostApplicant::where('campaign_id', $campaign->id)->count())->toBe(0);
})->skip('Pest Browser plugin does not yet parse multipart/form-data request bodies; full schema round-trip coverage lives at tests/Feature/LiveHost/Recruitment/FormBuilderIntegrationTest.php.');
