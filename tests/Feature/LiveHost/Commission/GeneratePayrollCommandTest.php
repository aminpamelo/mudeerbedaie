<?php

use App\Models\LiveHostPayrollItem;
use App\Models\LiveHostPayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->actor = User::factory()->create(['role' => 'admin']);
});

it('generates a draft run for the specified period', function () {
    $this->artisan('livehost:payroll-draft', ['--period' => '2026-04'])
        ->assertSuccessful()
        ->expectsOutputToContain('Generated draft payroll run');

    $this->assertDatabaseHas('live_host_payroll_runs', [
        'status' => 'draft',
        'period_start' => '2026-04-01 00:00:00',
        'period_end' => '2026-04-30 00:00:00',
    ]);

    $run = LiveHostPayrollRun::query()->latest('id')->first();
    expect($run->items()->count())->toBeGreaterThan(0);
});

it('refuses to regenerate when a run exists without --force', function () {
    LiveHostPayrollRun::create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'cutoff_date' => '2026-05-14',
        'status' => 'draft',
    ]);

    $this->artisan('livehost:payroll-draft', ['--period' => '2026-04'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(LiveHostPayrollRun::count())->toBe(1);
});

it('regenerates with --force when existing is draft', function () {
    $existing = LiveHostPayrollRun::create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'cutoff_date' => '2026-05-14',
        'status' => 'draft',
    ]);
    $existingId = $existing->id;

    $this->artisan('livehost:payroll-draft', ['--period' => '2026-04', '--force' => true])
        ->assertSuccessful();

    expect(LiveHostPayrollRun::find($existingId))->toBeNull();
    expect(LiveHostPayrollRun::count())->toBe(1);

    $new = LiveHostPayrollRun::first();
    expect($new->status)->toBe('draft');
    expect($new->items()->count())->toBeGreaterThan(0);
});

it('refuses --force on locked runs', function () {
    $locked = LiveHostPayrollRun::create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'cutoff_date' => '2026-05-14',
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->actor->id,
    ]);

    $this->artisan('livehost:payroll-draft', ['--period' => '2026-04', '--force' => true])
        ->expectsOutputToContain('locked')
        ->assertFailed();

    expect(LiveHostPayrollRun::find($locked->id))->not->toBeNull();
    expect(LiveHostPayrollItem::where('payroll_run_id', $locked->id)->count())->toBe(0);
});

it('refuses --force on paid runs', function () {
    $paid = LiveHostPayrollRun::create([
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'cutoff_date' => '2026-05-14',
        'status' => 'paid',
        'locked_at' => now()->subDay(),
        'locked_by' => $this->actor->id,
        'paid_at' => now(),
    ]);

    $this->artisan('livehost:payroll-draft', ['--period' => '2026-04', '--force' => true])
        ->expectsOutputToContain('paid')
        ->assertFailed();

    expect(LiveHostPayrollRun::find($paid->id))->not->toBeNull();
});

it('invalid period format fails', function () {
    $this->artisan('livehost:payroll-draft', ['--period' => '2026/04'])->assertFailed();
    $this->artisan('livehost:payroll-draft', ['--period' => '202604'])->assertFailed();
    $this->artisan('livehost:payroll-draft', ['--period' => 'abc'])->assertFailed();
});

it('fails with a helpful message when no admin user exists', function () {
    $this->actor->delete();

    $this->artisan('livehost:payroll-draft', ['--period' => '2026-04'])
        ->expectsOutputToContain('No admin user found')
        ->assertFailed();
});
