<?php

declare(strict_types=1);

use App\Jobs\SendCampaignMessageJob;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Services\WhatsApp\WhatsAppBlastService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

function makeStalledCampaign(string $status = 'queued'): WhatsAppCampaign
{
    $campaign = WhatsAppCampaign::create([
        'name' => 'stalled blast',
        'template_name' => 'order_confirmed',
        'template_language' => 'ms',
        'status' => $status,
        'total_recipients' => 4,
    ]);

    $states = ['pending', 'pending', 'sent', 'sending'];
    foreach ($states as $i => $recipientStatus) {
        WhatsAppCampaignRecipient::create([
            'whatsapp_campaign_id' => $campaign->id,
            'customer_name' => "Customer {$i}",
            'phone' => '60160000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'status' => $recipientStatus,
        ]);
    }

    return $campaign;
}

it('re-dispatches only the unfinished recipients', function () {
    Queue::fake();

    $campaign = makeStalledCampaign();

    $count = app(WhatsAppBlastService::class)->resume($campaign);

    // 2 pending + 1 sending = 3 unfinished; the 1 already-sent is skipped.
    expect($count)->toBe(3);
    Queue::assertPushed(SendCampaignMessageJob::class, 3);
});

it('routes campaign sends onto the dedicated whatsapp queue', function () {
    Queue::fake();

    app(WhatsAppBlastService::class)->resume(makeStalledCampaign());

    Queue::assertPushedOn('whatsapp', SendCampaignMessageJob::class);
});

it('resets a failed campaign back to queued when re-dispatching', function () {
    Queue::fake();

    $campaign = makeStalledCampaign('failed');

    app(WhatsAppBlastService::class)->resume($campaign);

    expect($campaign->fresh()->status)->toBe('queued');
    expect($campaign->fresh()->completed_at)->toBeNull();
});

it('does nothing and finalizes when no recipients are outstanding', function () {
    Queue::fake();

    $campaign = WhatsAppCampaign::create([
        'name' => 'done blast',
        'template_name' => 't',
        'status' => 'sending',
        'total_recipients' => 1,
    ]);
    WhatsAppCampaignRecipient::create([
        'whatsapp_campaign_id' => $campaign->id,
        'phone' => '60160000999',
        'status' => 'sent',
    ]);

    $count = app(WhatsAppBlastService::class)->resume($campaign);

    expect($count)->toBe(0);
    expect($campaign->fresh()->status)->toBe('completed');
    Queue::assertNothingPushed();
});

it('leaves a cancelled campaign untouched', function () {
    Queue::fake();

    $campaign = makeStalledCampaign('cancelled');

    expect(app(WhatsAppBlastService::class)->resume($campaign))->toBe(0);
    Queue::assertNothingPushed();
});

it('lets an admin resume a stalled campaign from the list page', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $campaign = makeStalledCampaign();

    $this->actingAs($admin);

    Volt::test('admin.whatsapp-campaigns')
        ->call('resume', $campaign->id)
        ->assertHasNoErrors();

    Queue::assertPushed(SendCampaignMessageJob::class, 3);
});

it('forbids a non-admin from reaching the campaigns list', function () {
    $user = User::factory()->create(['role' => 'teacher']);

    $this->actingAs($user)
        ->get(route('admin.whatsapp.campaigns'))
        ->assertForbidden();
});

it('force-releases a stuck send lock so re-queued jobs can run', function () {
    Queue::fake();

    $service = app(WhatsAppBlastService::class);

    // Simulate a lock orphaned by a worker that died mid-send.
    $key = (new WithoutOverlapping('whatsapp-send'))
        ->getLockKey(new SendCampaignMessageJob(0));
    expect(Cache::lock($key)->get())->toBeTrue();

    $campaign = makeStalledCampaign();
    $service->resume($campaign);

    // The lock is now free: a fresh acquire succeeds.
    expect(Cache::lock($key)->get())->toBeTrue();
});

it('anchors the job give-up deadline to when it was queued', function () {
    $queuedAt = Carbon::parse('2026-07-28 15:00:00');

    $job = new SendCampaignMessageJob(1, $queuedAt->toIso8601String());

    expect($job->retryUntil()->getTimestamp())
        ->toBe($queuedAt->copy()->addHours(24)->getTimestamp());
});
