<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Ceo\Reports\MonthlyReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    CarbonImmutable::setTestNow('2026-06-06 10:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function seedOrder(string $date, string $paymentStatus, float $amount, string $status = 'completed'): void
{
    ProductOrder::factory()->create([
        'payment_status' => $paymentStatus,
        'status' => $status,
        'total_amount' => $amount,
        'created_at' => $date,
    ]);
}

describe('access control', function () {
    it('forbids non-executive roles', function () {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get('/ceo/reports/monthly')->assertForbidden();
    });

    it('allows the ceo role and 404s an unsupported department', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);
        $this->actingAs($ceo)->get('/ceo/reports/monthly')->assertOk();
        $this->actingAs($ceo)->get('/ceo/reports/monthly?department=hr')->assertNotFound();
    });
});

describe('payload', function () {
    it('renders the MonthlyReport component with 12 months and metric rows', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)
            ->get('/ceo/reports/monthly')
            ->assertInertia(fn (Assert $page) => $page
                ->component('MonthlyReport', false)
                ->where('report.department', 'ecommerce')
                ->where('report.year', 2026)
                ->has('report.months', 12)
                ->has('report.rows', 7)
                ->has('report.summary.revenueTrend', 12)
            );
    });

    it('clamps a future year back to the current year', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)->get('/ceo/reports/monthly?year=2999')
            ->assertInertia(fn (Assert $page) => $page->where('report.year', 2026));
    });
});

describe('monthly metrics', function () {
    it('computes per-month values, YTD totals, best/worst and MoM', function () {
        // January: 2 paid (RM100 each) + 1 failed.
        seedOrder('2026-01-15 09:00:00', 'paid', 100);
        seedOrder('2026-01-16 09:00:00', 'paid', 100);
        seedOrder('2026-01-17 09:00:00', 'failed', 100, 'cancelled');
        // February: 3 paid (RM100 each).
        seedOrder('2026-02-10 09:00:00', 'paid', 100);
        seedOrder('2026-02-11 09:00:00', 'paid', 100);
        seedOrder('2026-02-12 09:00:00', 'paid', 100);

        $report = app(MonthlyReportService::class)->build('ecommerce', 2026);

        $revenue = collect($report['rows'])->firstWhere('key', 'revenue');
        expect($revenue['display'][0])->toBe('RM 200'); // Jan
        expect($revenue['display'][1])->toBe('RM 300'); // Feb
        expect($revenue['display'][2])->toBe('');        // Mar (no orders -> blank)
        expect($revenue['ytdTotal'])->toBe('RM 500');
        expect($revenue['bestIndex'])->toBe(1);          // Feb highest
        expect($revenue['worstIndex'])->toBe(0);         // Jan lowest
        expect($revenue['mom']['text'])->toBe('+50%');   // 300 vs 200
        expect($revenue['mom']['tone'])->toBe('positive');

        // Orders = 3 in Jan (2 paid + 1 failed), 3 in Feb.
        $orders = collect($report['rows'])->firstWhere('key', 'orders');
        expect($orders['display'][0])->toBe('3');

        // Payment success YTD avg = 5 paid / 6 settled = 83%.
        $success = collect($report['rows'])->firstWhere('key', 'success');
        expect($success['ytdTotal'])->toBe('—');        // rate metric has no total
        expect($success['ytdAvg'])->toBe('83%');
        expect($success['display'][0])->toBe('67%');     // Jan 2/3
    });

    it('marks a higher month as negative MoM for down-polarity metrics', function () {
        seedOrder('2026-01-15 09:00:00', 'failed', 50, 'cancelled');
        seedOrder('2026-02-10 09:00:00', 'failed', 50, 'cancelled');
        seedOrder('2026-02-11 09:00:00', 'failed', 50, 'cancelled');

        $report = app(MonthlyReportService::class)->build('ecommerce', 2026);
        $failed = collect($report['rows'])->firstWhere('key', 'failed');

        // Failed went 1 -> 2 (worse), polarity down => negative tone.
        expect($failed['mom']['text'])->toBe('+100%');
        expect($failed['mom']['tone'])->toBe('negative');
    });
});
