<?php

use App\Mail\LiveHost\Recruitment\ApplicationReceivedMail;
use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('accepts a valid application', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $response = $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Ahmad Test',
        'email' => 'ahmad.test@example.com',
        'phone' => '60123456789',
        'platforms' => ['tiktok'],
        'experience_summary' => 'Some experience',
        'motivation' => 'Because I love live selling',
    ]);

    $response->assertRedirect(route('recruitment.thank-you', $campaign->slug));

    $applicant = LiveHostApplicant::where('email', 'ahmad.test@example.com')->firstOrFail();
    expect($applicant->campaign_id)->toBe($campaign->id);
    expect($applicant->status)->toBe('active');

    $firstStage = $campaign->stages()->orderBy('position')->first();
    expect($applicant->current_stage_id)->toBe($firstStage->id);

    expect($applicant->history()->where('action', 'applied')->exists())->toBeTrue();
});

it('rejects duplicate applications for the same campaign+email', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'email' => 'dupe@example.com',
    ]);

    $response = $this->from(route('recruitment.show', $campaign->slug))
        ->post(route('recruitment.apply', $campaign->slug), [
            'full_name' => 'Dupe',
            'email' => 'dupe@example.com',
            'phone' => '60123456789',
            'platforms' => ['tiktok'],
        ]);

    $response->assertSessionHasErrors('email');

    expect(LiveHostApplicant::where('campaign_id', $campaign->id)
        ->where('email', 'dupe@example.com')
        ->count())->toBe(1);
});

it('rejects applications to closed campaigns', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'closed']);

    $this->get(route('recruitment.show', $campaign->slug))->assertStatus(410);

    $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Test',
        'email' => 'test@example.com',
        'phone' => '60123456789',
        'platforms' => ['tiktok'],
    ])->assertStatus(410);

    expect(LiveHostApplicant::where('email', 'test@example.com')->exists())->toBeFalse();
});

it('rejects applications to paused campaigns', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'paused']);

    $this->get(route('recruitment.show', $campaign->slug))->assertStatus(410);

    $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Paused Test',
        'email' => 'paused.test@example.com',
        'phone' => '60123456789',
        'platforms' => ['tiktok'],
    ])->assertStatus(410);

    expect(LiveHostApplicant::where('email', 'paused.test@example.com')->exists())->toBeFalse();
});

it('validates required fields when submitting an application', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $response = $this->from(route('recruitment.show', $campaign->slug))
        ->post(route('recruitment.apply', $campaign->slug), []);

    $response->assertSessionHasErrors(['full_name', 'email', 'phone', 'platforms']);

    expect(LiveHostApplicant::where('campaign_id', $campaign->id)->count())->toBe(0);
});

it('queues a confirmation email on successful application', function () {
    Mail::fake();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Ahmad Test',
        'email' => 'ahmad@mail.example',
        'phone' => '60123456789',
        'platforms' => ['tiktok'],
        'experience_summary' => 'x',
        'motivation' => 'y',
    ]);

    Mail::assertQueued(
        ApplicationReceivedMail::class,
        fn ($mail) => $mail->hasTo('ahmad@mail.example')
    );
});
