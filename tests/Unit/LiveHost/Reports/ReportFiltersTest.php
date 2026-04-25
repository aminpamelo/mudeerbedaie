<?php

use App\Services\LiveHost\Reports\Filters\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

it('defaults to current month when no dates given', function () {
    CarbonImmutable::setTestNow('2026-04-25 10:00:00');
    $request = Request::create('/test');

    $filters = ReportFilters::fromRequest($request);

    expect($filters->dateFrom->toDateString())->toBe('2026-04-01')
        ->and($filters->dateTo->toDateString())->toBe('2026-04-25')
        ->and($filters->hostIds)->toBe([])
        ->and($filters->platformAccountIds)->toBe([]);
});

it('parses explicit dates and arrays', function () {
    $request = Request::create('/test', 'GET', [
        'dateFrom' => '2026-03-01',
        'dateTo' => '2026-03-31',
        'hostIds' => ['1', '2'],
        'platformAccountIds' => ['7'],
    ]);

    $filters = ReportFilters::fromRequest($request);

    expect($filters->dateFrom->toDateString())->toBe('2026-03-01')
        ->and($filters->dateTo->toDateString())->toBe('2026-03-31')
        ->and($filters->hostIds)->toBe([1, 2])
        ->and($filters->platformAccountIds)->toBe([7]);
});

it('rejects an inverted date range', function () {
    $request = Request::create('/test', 'GET', [
        'dateFrom' => '2026-04-30',
        'dateTo' => '2026-04-01',
    ]);

    expect(fn () => ReportFilters::fromRequest($request))
        ->toThrow(\InvalidArgumentException::class);
});

it('computes the prior period of equal length', function () {
    $request = Request::create('/test', 'GET', [
        'dateFrom' => '2026-04-01',
        'dateTo' => '2026-04-10', // 10 days
    ]);
    $filters = ReportFilters::fromRequest($request);

    $prior = $filters->priorPeriod();

    expect($prior->dateFrom->toDateString())->toBe('2026-03-22')
        ->and($prior->dateTo->toDateString())->toBe('2026-03-31');
});
