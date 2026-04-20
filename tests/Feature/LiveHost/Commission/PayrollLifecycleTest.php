<?php

use App\Exceptions\LiveHost\PayrollRunStateException;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveHostPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** Seed a single verified April session for the given host and return it. */
function seedLifecycleSession(User $host, Platform $tiktok, string $dateTime = '2026-04-12 10:00', float $gmv = 1000): LiveSession
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $tiktok->id,
        'user_id' => $host->id,
    ]);
    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);

    $start = Carbon::parse($dateTime);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'verification_status' => 'pending',
        'scheduled_start_at' => $start,
        'actual_start_at' => $start,
        'actual_end_at' => $start->copy()->addMinutes(50),
        'duration_minutes' => 50,
        'gmv_amount' => $gmv,
        'gmv_adjustment' => 0,
    ]);
    $session->verification_status = 'verified';
    $session->save();

    return $session->fresh();
}

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
});

it('recompute regenerates items on a draft run', function () {
    seedLifecycleSession($this->ahmad, $this->tiktok, '2026-04-12 10:00', 1000);

    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $originalAhmad = $run->items->firstWhere('user_id', $this->ahmad->id);
    expect((int) $originalAhmad->sessions_count)->toBe(1);
    expect((float) $originalAhmad->net_gmv_myr)->toBe(1000.00);

    $originalItemId = $originalAhmad->id;

    // Add another session within the same period
    seedLifecycleSession($this->ahmad, $this->tiktok, '2026-04-20 10:00', 500);

    $recomputed = $service->recompute($run->fresh());

    expect($recomputed->status)->toBe('draft');

    $ahmadItem = $recomputed->items->firstWhere('user_id', $this->ahmad->id);
    expect((int) $ahmadItem->sessions_count)->toBe(2);
    expect((float) $ahmadItem->net_gmv_myr)->toBe(1500.00);

    // Items were regenerated — old row id no longer exists
    expect(\App\Models\LiveHostPayrollItem::find($originalItemId))->toBeNull();
});

it('recompute throws on a locked run', function () {
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $service->lock($run, $this->pic);

    expect(fn () => $service->recompute($run->fresh()))
        ->toThrow(PayrollRunStateException::class);
});

it('lock transitions to locked status', function () {
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $before = now()->subSecond();
    $locked = $service->lock($run, $this->pic);

    expect($locked->status)->toBe('locked');
    expect($locked->locked_at)->not->toBeNull();
    expect($locked->locked_at->greaterThanOrEqualTo($before))->toBeTrue();
    expect($locked->locked_by)->toBe($this->pic->id);
});

it('lock throws on an already-locked run', function () {
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $service->lock($run, $this->pic);

    expect(fn () => $service->lock($run->fresh(), $this->pic))
        ->toThrow(PayrollRunStateException::class);
});

it('markPaid throws on draft run', function () {
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    expect(fn () => $service->markPaid($run, $this->pic))
        ->toThrow(PayrollRunStateException::class);
});

it('markPaid transitions from locked to paid', function () {
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $service->lock($run, $this->pic);

    $before = now()->subSecond();
    $paid = $service->markPaid($run->fresh(), $this->pic);

    expect($paid->status)->toBe('paid');
    expect($paid->paid_at)->not->toBeNull();
    expect($paid->paid_at->greaterThanOrEqualTo($before))->toBeTrue();
});

it('adjustment on a session in a locked payroll period is 403 (integration with Task 15)', function () {
    $session = seedLifecycleSession($this->ahmad, $this->tiktok, '2026-04-12 10:00', 1000);

    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );
    $service->lock($run, $this->pic);

    $response = actingAs($this->pic)
        ->post("/livehost/sessions/{$session->id}/adjustments", [
            'amount' => -100,
            'reason' => 'Should be blocked because payroll is locked',
        ]);

    $response->assertForbidden();
    expect(strtolower($response->exception?->getMessage() ?? ''))->toContain('payroll locked');
});
