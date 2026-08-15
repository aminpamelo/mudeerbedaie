<?php

use App\Jobs\SyncTikTokLive;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
});

it('dispatches a scoped LIVE sync for every active TikTok account', function () {
    Queue::fake();

    $a = PlatformAccount::factory()->create(['platform_id' => $this->platform->id, 'is_active' => true]);
    $b = PlatformAccount::factory()->create(['platform_id' => $this->platform->id, 'is_active' => true]);
    PlatformAccount::factory()->create(['platform_id' => $this->platform->id, 'is_active' => false]);

    actingAs($this->pic)
        ->post('/livehost/session-slots/sync-tiktok', [
            'from' => '2026-07-19',
            'until' => '2026-07-25',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(SyncTikTokLive::class, 2);

    // End date is pushed out by one day because the sync API treats it as exclusive.
    Queue::assertPushed(SyncTikTokLive::class, fn ($job) => in_array($job->account->id, [$a->id, $b->id], true)
        && $job->from === '2026-07-19'
        && $job->to === '2026-07-26');
});

it('can scope the sync to a single shop', function () {
    Queue::fake();

    $a = PlatformAccount::factory()->create(['platform_id' => $this->platform->id, 'is_active' => true]);
    PlatformAccount::factory()->create(['platform_id' => $this->platform->id, 'is_active' => true]);

    actingAs($this->pic)
        ->post('/livehost/session-slots/sync-tiktok', [
            'from' => '2026-07-19',
            'until' => '2026-07-25',
            'platform_account_id' => $a->id,
        ])
        ->assertRedirect();

    Queue::assertPushed(SyncTikTokLive::class, 1);
    Queue::assertPushed(SyncTikTokLive::class, fn ($job) => $job->account->id === $a->id);
});

it('rejects a range wider than three months', function () {
    Queue::fake();
    PlatformAccount::factory()->create(['platform_id' => $this->platform->id, 'is_active' => true]);

    actingAs($this->pic)
        ->post('/livehost/session-slots/sync-tiktok', [
            'from' => '2026-01-01',
            'until' => '2026-06-01',
        ])
        ->assertSessionHasErrors('until');

    Queue::assertNothingPushed();
});

it('validates that until is not before from', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots/sync-tiktok', [
            'from' => '2026-07-25',
            'until' => '2026-07-19',
        ])
        ->assertSessionHasErrors('until');
});

it('forbids non-desk roles', function () {
    Queue::fake();
    $student = User::factory()->create(['role' => 'user']);

    actingAs($student)
        ->post('/livehost/session-slots/sync-tiktok', [
            'from' => '2026-07-19',
            'until' => '2026-07-25',
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});
