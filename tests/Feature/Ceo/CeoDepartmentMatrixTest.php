<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\DepartmentMatrixReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

describe('matrix payload', function () {
    it('attaches a matrix to every department detail page', function (string $department, int $rows) {
        $this->actingAs(User::factory()->create(['role' => 'ceo']))
            ->get("/ceo/{$department}?period=7d")
            ->assertInertia(fn (Assert $page) => $page
                ->component('DepartmentDetail', false)
                ->has('department.matrix.months', 7)        // 7 daily buckets
                ->has('department.matrix.rows', $rows)
                ->has('department.matrix.columns.metric')
                ->has('department.matrix.columns.mom')
            );
    })->with([
        ['livehost', 4],
        ['education', 4],
        ['ecommerce', 4],
        ['hr', 3],
        ['sales', 4],
    ]);

    it('buckets long periods by week instead of by day', function () {
        $matrix = app(DepartmentMatrixReport::class)->build('ecommerce', CeoPeriod::fromKey('30d'));

        // 30 days collapses into <= 5 weekly buckets, not 30 daily columns.
        expect(count($matrix['months']))->toBeLessThanOrEqual(5);
        expect(count($matrix['months']))->toBeGreaterThan(1);
    });
});

describe('aggregation', function () {
    it('sums paid revenue and order counts across the period', function () {
        // 3 paid orders (RM100 each) + 1 failed, all within the last 7 days.
        ProductOrder::factory()->count(3)->create([
            'created_at' => now()->subDays(2),
            'payment_status' => 'paid',
            'total_amount' => 100,
        ]);
        ProductOrder::factory()->create([
            'created_at' => now()->subDays(2),
            'payment_status' => 'failed',
            'total_amount' => 50,
        ]);

        $matrix = app(DepartmentMatrixReport::class)->build('ecommerce', CeoPeriod::fromKey('7d'));

        expect($matrix['empty'])->toBeFalse();

        $rowsByKey = collect($matrix['rows'])->keyBy('key');
        expect($rowsByKey['revenue']['ytdTotal'])->toBe('RM 300');
        expect($rowsByKey['orders']['ytdTotal'])->toBe('4');
        expect($rowsByKey['paid']['ytdTotal'])->toBe('3');
        expect($rowsByKey['failed']['ytdTotal'])->toBe('1');
        // Avg is per elapsed day (7 days in a 7d window): RM 300 / 7 = ~43.
        expect($rowsByKey['revenue']['ytdAvg'])->toBe('RM 43');

        // Every row carries a per-day drill-down series for the inline expansion:
        // one entry per elapsed day, with the day the orders landed showing RM 300.
        expect($rowsByKey['revenue']['daily'])->toHaveCount(7);
        $landedLabel = now()->subDays(2)->format('j/n');
        $landed = collect($rowsByKey['revenue']['daily'])->firstWhere('label', $landedLabel);
        expect($landed['display'])->toBe('RM 300');
        expect($landed['value'])->toBe(300.0);
    });

    it('reports an empty matrix when nothing happened', function () {
        $matrix = app(DepartmentMatrixReport::class)->build('ecommerce', CeoPeriod::fromKey('today'));

        expect($matrix['empty'])->toBeTrue();
        expect($matrix['rows'])->toHaveCount(4);
    });

    it('computes a vs-previous-period change', function () {
        ProductOrder::factory()->count(2)->create(['created_at' => now()->subDays(1), 'payment_status' => 'paid', 'total_amount' => 100]);
        // Prior 7-day window (8-14 days ago): only 1 paid order.
        ProductOrder::factory()->create(['created_at' => now()->subDays(9), 'payment_status' => 'paid', 'total_amount' => 100]);

        $matrix = app(DepartmentMatrixReport::class)->build('ecommerce', CeoPeriod::fromKey('7d'));
        $orders = collect($matrix['rows'])->firstWhere('key', 'orders');

        // 2 this period vs 1 prior = +100%.
        expect($orders['mom'])->not->toBeNull();
        expect($orders['mom']['text'])->toBe('+100%');
        expect($orders['mom']['tone'])->toBe('positive');
    });
});

describe('access control', function () {
    it('still 404s an unsupported department', function () {
        $this->actingAs(User::factory()->create(['role' => 'ceo']))
            ->get('/ceo/marketing')
            ->assertNotFound();
    });
});
