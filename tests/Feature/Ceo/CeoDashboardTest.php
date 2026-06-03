<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\ProductOrder;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Services\Ceo\CeoDashboardService;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\DepartmentHealth;
use App\Services\Ceo\Reports\EcommerceHealthReport;
use App\Services\Ceo\Reports\LiveHostHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The dashboard service caches each department report for 60s, keyed by
    // period only. Flush so seeded data in one test never leaks into the next.
    Cache::flush();
});

describe('access control', function () {
    it('redirects guests to login', function () {
        $this->get('/ceo')->assertRedirect('/login');
    });

    it('forbids non-executive roles', function () {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/ceo')->assertForbidden();
    });

    it('allows the ceo role', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)->get('/ceo')->assertOk();
    });

    it('allows admins', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/ceo')->assertOk();
    });
});

describe('dashboard payload', function () {
    it('renders the Dashboard component with the expected prop shape', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)
            ->get('/ceo')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard', false)
                ->has('period.key')
                ->has('period.options', 3)
                ->has('health.score')
                ->has('health.status')
                ->has('health.segments', 4)
                ->has('pulse', 6)
                ->has('departments', 4)
                ->has('attention')
                ->has('departments.0.status')
                ->has('departments.0.metrics')
                ->has('departments.0.gauges')
                ->has('departments.0.bars')
            );
    });

    it('defaults to the today period and accepts a period query', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)->get('/ceo')
            ->assertInertia(fn (Assert $page) => $page->where('period.key', 'today'));

        $this->actingAs($ceo)->get('/ceo?period=30d')
            ->assertInertia(fn (Assert $page) => $page->where('period.key', '30d'));
    });
});

describe('localization', function () {
    it('defaults the CEO dashboard to Malay', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)
            ->get('/ceo')
            ->assertInertia(fn (Assert $page) => $page
                ->where('ceoLocale', 'ms')
                ->where('period.label', 'Hari ini')
                ->has('i18n.company_overview')
            );
    });

    it('switches to English and back via the locale endpoint', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)->post('/ceo/locale', ['locale' => 'en'])->assertRedirect();

        $this->actingAs($ceo)->get('/ceo')
            ->assertInertia(fn (Assert $page) => $page
                ->where('ceoLocale', 'en')
                ->where('period.label', 'Today')
            );

        $this->actingAs($ceo)->post('/ceo/locale', ['locale' => 'ms'])->assertRedirect();

        $this->actingAs($ceo)->get('/ceo')
            ->assertInertia(fn (Assert $page) => $page->where('ceoLocale', 'ms'));
    });

    it('ignores an unsupported locale and keeps the default', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)->post('/ceo/locale', ['locale' => 'fr'])->assertRedirect();

        $this->actingAs($ceo)->get('/ceo')
            ->assertInertia(fn (Assert $page) => $page->where('ceoLocale', 'ms'));
    });
});

describe('department detail pages', function () {
    it('forbids non-executive roles from detail pages', function () {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/ceo/livehost')->assertForbidden();
    });

    it('renders the detail page for each department', function (string $key) {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)
            ->get("/ceo/{$key}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DepartmentDetail', false)
                ->where('department.key', $key)
                ->has('department.kpis', 6)
                ->has('department.gauges')
                ->has('department.sections')
                ->has('period.key')
            );
    })->with(['livehost', 'education', 'ecommerce', 'hr']);

    it('404s for an unknown department', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)->get('/ceo/marketing')->assertNotFound();
    });
});

describe('e-commerce health', function () {
    it('computes payment success rate, revenue and unfulfilled backlog', function () {
        ProductOrder::factory()->count(9)->create(['payment_status' => 'paid', 'status' => 'delivered', 'total_amount' => 100]);
        ProductOrder::factory()->create(['payment_status' => 'failed', 'status' => 'cancelled', 'total_amount' => 100]);
        ProductOrder::factory()->count(3)->create(['payment_status' => 'paid', 'status' => 'pending', 'total_amount' => 50]);

        $health = app(EcommerceHealthReport::class)->run(CeoPeriod::fromKey('today'));

        $success = collect($health->metrics)->firstWhere('label', 'Payment success');
        $unfulfilled = collect($health->metrics)->firstWhere('label', 'Unfulfilled');

        // 12 paid of 13 settled = 92%.
        expect($success['value'])->toBe('92%');
        // 3 paid orders still in a pre-ship status.
        expect($unfulfilled['value'])->toBe('3');
        expect($health->extra['revenuePeriod'])->toBe(1050.0);
    });

    it('flags red when the payment success rate collapses', function () {
        ProductOrder::factory()->count(8)->create(['payment_status' => 'failed', 'status' => 'cancelled', 'total_amount' => 100]);
        ProductOrder::factory()->count(2)->create(['payment_status' => 'paid', 'status' => 'delivered', 'total_amount' => 100]);

        $health = app(EcommerceHealthReport::class)->run(CeoPeriod::fromKey('today'));

        expect($health->status)->toBe(DepartmentHealth::RED);
    });
});

describe('live host health', function () {
    it('raises an attention alert when a roster slot is uncovered today', function () {
        LiveScheduleAssignment::factory()->forDate(today()->toDateString())->create(['live_host_id' => null]);

        $health = app(LiveHostHealthReport::class)->run(CeoPeriod::fromKey('today'));

        expect($health->status)->not->toBe(DepartmentHealth::GREEN);
        expect(collect($health->alerts)->pluck('message')->implode(' '))
            ->toContain('uncovered today');
    });

    it('surfaces pending replacement requests in the attention feed', function () {
        SessionReplacementRequest::factory()->create(['status' => SessionReplacementRequest::STATUS_PENDING]);

        $payload = app(CeoDashboardService::class)->build(CeoPeriod::fromKey('today'));

        expect(collect($payload['attention'])->pluck('message')->implode(' '))
            ->toContain('replacement');
    });
});
