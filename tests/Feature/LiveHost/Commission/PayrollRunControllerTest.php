<?php

use App\Models\LiveHostPayrollRun;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\LiveHostPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Seed a single verified live session so generateDraft has real aggregates to
 * compute against. Mirrors the helper from PayrollLifecycleTest / PayrollGenerateDraftTest.
 */
function seedVerifiedSession(User $host, Platform $platform, string $dateTime = '2026-04-12 10:00', float $gmv = 1000): LiveSession
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
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
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('index lists payroll runs with counts and totals', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    seedVerifiedSession($ahmad, $tiktok, '2026-03-10 10:00', 1000);
    seedVerifiedSession($ahmad, $tiktok, '2026-04-10 10:00', 1000);

    $service = app(LiveHostPayrollService::class);

    // Older run — locked
    $lockedRun = $service->generateDraft(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31')->endOfDay(),
        $this->pic,
    );
    $service->lock($lockedRun, $this->pic);

    // Newer run — draft
    $draftRun = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($this->pic)
        ->get('/livehost/payroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payroll/Index', false)
            ->has('runs.data', 2)
            ->has('runs.data.0.id')
            ->has('runs.data.0.period_start')
            ->has('runs.data.0.period_end')
            ->has('runs.data.0.status')
            ->has('runs.data.0.items_count')
            ->has('runs.data.0.net_payout_total_myr')
            ->where('runs.data.0.id', $draftRun->id)
            ->where('runs.data.0.status', 'draft')
            ->where('runs.data.1.id', $lockedRun->id)
            ->where('runs.data.1.status', 'locked')
        );
});

it('store creates a draft run via service', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);

    $response = actingAs($this->pic)->post('/livehost/payroll', [
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('live_host_payroll_runs', [
        'status' => 'draft',
    ]);
    $run = LiveHostPayrollRun::latest('id')->first();
    expect($run->period_start->toDateString())->toBe('2026-04-01');
    expect($run->period_end->toDateString())->toBe('2026-04-30');

    $run = LiveHostPayrollRun::latest('id')->first();
    $response->assertRedirect("/livehost/payroll/{$run->id}");
});

it('store validates period_end is after period_start', function () {
    actingAs($this->pic)
        ->post('/livehost/payroll', [
            'period_start' => '2026-04-30',
            'period_end' => '2026-04-01',
        ])
        ->assertSessionHasErrors('period_end');
});

it('store validates that both dates are required', function () {
    actingAs($this->pic)
        ->post('/livehost/payroll', [])
        ->assertSessionHasErrors(['period_start', 'period_end']);
});

it('show returns the run with items loaded', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    seedVerifiedSession($ahmad, $tiktok, '2026-04-12 10:00', 1000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($this->pic)
        ->get("/livehost/payroll/{$run->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payroll/Show', false)
            ->has('run')
            ->where('run.id', $run->id)
            ->where('run.status', 'draft')
            ->has('run.items')
            ->has('run.items.0.user_id')
            ->has('run.items.0.host_name')
            ->has('run.items.0.base_salary_myr')
            ->has('run.items.0.net_payout_myr')
            ->has('run.items.0.calculation_breakdown_json')
        );
});

it('recompute succeeds on draft run', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($this->pic)
        ->post("/livehost/payroll/{$run->id}/recompute")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($run->fresh()->status)->toBe('draft');
});

it('recompute fails with error flash on locked run', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );
    $service->lock($run, $this->pic);

    actingAs($this->pic)
        ->post("/livehost/payroll/{$run->id}/recompute")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($run->fresh()->status)->toBe('locked');
});

it('lock transitions status', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($this->pic)
        ->post("/livehost/payroll/{$run->id}/lock")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($run->fresh()->status)->toBe('locked');
});

it('lock returns error flash if already locked', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );
    $service->lock($run, $this->pic);

    actingAs($this->pic)
        ->post("/livehost/payroll/{$run->id}/lock")
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('mark-paid fails with error flash on draft run', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($this->pic)
        ->post("/livehost/payroll/{$run->id}/mark-paid")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($run->fresh()->status)->toBe('draft');
});

it('mark-paid succeeds on locked run', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $service = app(LiveHostPayrollService::class);
    $run = $service->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );
    $service->lock($run, $this->pic);

    actingAs($this->pic)
        ->post("/livehost/payroll/{$run->id}/mark-paid")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($run->fresh()->status)->toBe('paid');
});

it('export streams CSV with expected headers and row per host', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $sarah = User::where('email', 'sarah@livehost.com')->first();
    $tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
    seedVerifiedSession($ahmad, $tiktok, '2026-04-12 10:00', 1000);
    seedVerifiedSession($sarah, $tiktok, '2026-04-13 10:00', 2000);

    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    $response = actingAs($this->pic)->get("/livehost/payroll/{$run->id}/export");
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))
        ->toContain('payroll-2026-04-01-2026-04-30.csv');

    $body = $response->streamedContent();
    $lines = preg_split('/\r\n|\n|\r/', trim($body));

    // PHP fputcsv wraps fields containing spaces in double quotes — the
    // semantic content is what matters, not the quoting.
    $headerFields = str_getcsv($lines[0]);
    expect($headerFields)->toBe([
        'Host',
        'Base Salary',
        'Sessions',
        'Per-Live Total',
        'Gross GMV',
        'Adjustments',
        'Net GMV',
        'GMV Commission',
        'Override L1',
        'Override L2',
        'Gross Total',
        'Deductions',
        'Net Payout',
    ]);

    $body = implode("\n", $lines);
    expect($body)->toContain('Ahmad Rahman');
    expect($body)->toContain('Sarah Chen');
});

it('live_host role is forbidden on all payroll routes', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $host = User::factory()->create(['role' => 'live_host']);
    $run = app(LiveHostPayrollService::class)->generateDraft(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-30')->endOfDay(),
        $this->pic,
    );

    actingAs($host)->get('/livehost/payroll')->assertForbidden();
    actingAs($host)->post('/livehost/payroll', [
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ])->assertForbidden();
    actingAs($host)->get("/livehost/payroll/{$run->id}")->assertForbidden();
    actingAs($host)->post("/livehost/payroll/{$run->id}/recompute")->assertForbidden();
    actingAs($host)->post("/livehost/payroll/{$run->id}/lock")->assertForbidden();
    actingAs($host)->post("/livehost/payroll/{$run->id}/mark-paid")->assertForbidden();
    actingAs($host)->get("/livehost/payroll/{$run->id}/export")->assertForbidden();
});

it('guests are redirected to login', function () {
    $this->get('/livehost/payroll')->assertRedirect('/login');
});
