<?php

declare(strict_types=1);

use App\Services\Ceo\CeoPeriod;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-06 10:00:00'); // Q2 2026
    app()->setLocale('en');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('resolves a calendar quarter from its ref', function () {
    $p = CeoPeriod::fromKey('quarter', '2026-1');

    expect($p->from->toDateString())->toBe('2026-01-01');
    expect($p->to->toDateString())->toBe('2026-03-31');
    expect($p->label())->toBe('Q1 2026');
    expect($p->cacheKey())->toBe('quarter:2026-1');
});

it('steps quarters and stops at the current quarter', function () {
    $q1 = CeoPeriod::fromKey('quarter', '2026-1')->toPayload();
    expect($q1['prevRef'])->toBe('2025-4');
    expect($q1['nextRef'])->toBe('2026-2'); // Q2 is current -> reachable

    $current = CeoPeriod::fromKey('quarter', null)->toPayload();
    expect($current['ref'])->toBe('2026-2');
    expect($current['nextRef'])->toBeNull(); // cannot step past the current quarter
});

it('compares a quarter against the previous quarter', function () {
    $prior = CeoPeriod::fromKey('quarter', '2026-1')->priorPeriod();

    expect($prior->from->toDateString())->toBe('2025-10-01');
    expect($prior->to->toDateString())->toBe('2025-12-31');
});

it('resolves a calendar month from its ref with prev/next', function () {
    $p = CeoPeriod::fromKey('month', '2026-04');

    expect($p->from->toDateString())->toBe('2026-04-01');
    expect($p->to->toDateString())->toBe('2026-04-30');
    expect($p->label())->toBe('Apr 2026');

    $payload = $p->toPayload();
    expect($payload['prevRef'])->toBe('2026-03');
    expect($payload['nextRef'])->toBe('2026-05');
});

it('clamps a future ref back to the current period', function () {
    expect(CeoPeriod::fromKey('month', '2027-01')->ref)->toBe('2026-06');
    expect(CeoPeriod::fromKey('quarter', '2027-3')->ref)->toBe('2026-2');
});

it('localizes the quarter label to Malay', function () {
    app()->setLocale('ms');
    expect(CeoPeriod::fromKey('quarter', '2026-1')->label())->toBe('Suku 1 2026');
});

it('falls back to today for an unknown period key', function () {
    $p = CeoPeriod::fromKey('weekly');
    expect($p->key)->toBe('today');
});
