<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the commission overview matrix for PIC role', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);

    actingAs($pic)
        ->get('/livehost/commission')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('commission/Index', false)
            ->has('hosts')
            ->has('hosts.0.id')
            ->has('hosts.0.name')
            ->has('hosts.0.email')
            ->has('hosts.0.base_salary_myr')
            ->has('hosts.0.per_live_rate_myr')
            ->has('hosts.0.primary_platform_rate_percent')
            ->has('hosts.0.override_rate_l1_percent')
            ->has('hosts.0.override_rate_l2_percent')
            ->has('hosts.0.upline_name')
            ->has('platforms')
        );
});

it('surfaces seeded Ahmad/Sarah/Amin with expected matrix values', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);

    actingAs($pic)
        ->get('/livehost/commission')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $rows = collect($page->toArray()['props']['hosts']);

            $ahmad = $rows->firstWhere('email', 'ahmad@livehost.com');
            $sarah = $rows->firstWhere('email', 'sarah@livehost.com');
            $amin = $rows->firstWhere('email', 'amin@livehost.com');

            expect($ahmad)->not->toBeNull();
            expect((float) $ahmad['base_salary_myr'])->toBe(2000.0);
            expect((float) $ahmad['per_live_rate_myr'])->toBe(30.0);
            expect((float) $ahmad['primary_platform_rate_percent'])->toBe(4.0);
            expect($ahmad['upline_name'])->toBeNull();

            expect($sarah)->not->toBeNull();
            expect((float) $sarah['base_salary_myr'])->toBe(1800.0);
            expect((float) $sarah['primary_platform_rate_percent'])->toBe(5.0);
            expect($sarah['upline_name'])->toBe('Ahmad Rahman');

            expect($amin)->not->toBeNull();
            expect((float) $amin['base_salary_myr'])->toBe(0.0);
            expect((float) $amin['per_live_rate_myr'])->toBe(50.0);
            expect((float) $amin['primary_platform_rate_percent'])->toBe(6.0);
            expect($amin['upline_name'])->toBe('Sarah Chen');
        });
});

it('forbids live_host role from viewing the commission overview', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->get('/livehost/commission')
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get('/livehost/commission')->assertRedirect('/login');
});

it('CSV export returns a streaming response with expected headers', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);

    $response = actingAs($pic)->get('/livehost/commission/export');
    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    $lines = preg_split('/\r\n|\n|\r/', trim($body));

    expect($lines[0])->toBe(
        'host_email,host_name,base_salary_myr,primary_platform_rate_percent,per_live_rate_myr,upline_email,l1_percent,l2_percent'
    );

    $ahmadLine = collect($lines)->first(fn ($l) => str_starts_with($l, 'ahmad@livehost.com'));
    expect($ahmadLine)->not->toBeNull();
    expect($ahmadLine)->toContain('Ahmad Rahman');
    expect($ahmadLine)->toContain('2000');
    expect($ahmadLine)->toContain('4');
    expect($ahmadLine)->toContain('30');
});

it('CSV export is forbidden for live_host role', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->get('/livehost/commission/export')
        ->assertForbidden();
});
