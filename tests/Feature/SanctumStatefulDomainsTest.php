<?php

declare(strict_types=1);

test('production domain is registered as a sanctum stateful domain', function () {
    $stateful = config('sanctum.stateful');

    expect($stateful)->toContain('kelasify.com')
        ->and($stateful)->toContain('*.kelasify.com');
})->skip(
    fn () => filled(env('SANCTUM_STATEFUL_DOMAINS')),
    'SANCTUM_STATEFUL_DOMAINS is explicitly set; default stateful list is overridden.'
);
