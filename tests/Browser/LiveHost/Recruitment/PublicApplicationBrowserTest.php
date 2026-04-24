<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;

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
 * candidates may attach a resume (PDF/DOC/DOCX). As a result, this browser
 * test can render the public form and populate every field (verified via
 * `assertValue` / `assertChecked`), but the final POST from Chromium loses
 * the form values inside the Pest server. The feature itself is fully
 * covered by `tests/Feature/LiveHost/Recruitment/PublicApplicationTest.php`,
 * which exercises the same happy path through Laravel's normal testing
 * request pipeline (which does support multipart).
 *
 * The test is kept here as a scaffold so it can be re-enabled the moment
 * the Pest Browser plugin adds multipart parsing support.
 */
it('lets a candidate complete the public recruitment form', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create([
        'title' => 'Hiring TikTok Live Hosts April 2026',
    ]);

    $page = visit(route('recruitment.show', $campaign->slug));

    $page->assertSee('Hiring TikTok Live Hosts April 2026')
        ->fill('full_name', 'Ahmad Rahman')
        ->fill('email', 'ahmad.rahman@test.example')
        ->fill('phone', '60187654321')
        ->check('platforms[]', 'tiktok')
        ->fill('motivation', 'I love live selling')
        ->assertValue('full_name', 'Ahmad Rahman')
        ->assertChecked('platforms[]', 'tiktok')
        ->click('Submit application')
        ->assertSee('Thanks')
        ->assertNoJavascriptErrors();

    expect(LiveHostApplicant::where('email', 'ahmad.rahman@test.example')->exists())->toBeTrue();
})->skip('Pest Browser plugin does not yet parse multipart/form-data request bodies; covered by PublicApplicationTest at the HTTP layer.');
