<?php

declare(strict_types=1);

use App\Services\BayarcashService;

function invokePrivate(object $object, string $method, array $args): mixed
{
    return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
}

beforeEach(function () {
    $this->service = app(BayarcashService::class);
});

describe('normalizePhoneNumber', function () {
    it('strips a leading + so Bayarcash accepts the number', function () {
        expect(invokePrivate($this->service, 'normalizePhoneNumber', ['+60123456789']))->toBe('60123456789');
    });

    it('maps a local 0-prefixed number to the 60 international format', function () {
        expect(invokePrivate($this->service, 'normalizePhoneNumber', ['0123456789']))->toBe('60123456789');
    });

    it('strips spaces, dashes and parentheses to digits only', function () {
        expect(invokePrivate($this->service, 'normalizePhoneNumber', ['+60 12-345 6789']))->toBe('60123456789');
    });

    it('returns an empty string for an empty phone', function () {
        expect(invokePrivate($this->service, 'normalizePhoneNumber', ['']))->toBe('');
    });
});

describe('formatValidationErrors', function () {
    it('flattens nested field errors into a readable message', function () {
        $errors = ['errors' => ['payer_telephone_number' => ['The phone number format is invalid.']]];

        expect(invokePrivate($this->service, 'formatValidationErrors', [$errors]))
            ->toBe('The phone number format is invalid.');
    });

    it('handles a flat field-error map', function () {
        $errors = ['payer_name' => ['The name field is required.']];

        expect(invokePrivate($this->service, 'formatValidationErrors', [$errors]))
            ->toBe('The name field is required.');
    });

    it('falls back to a generic message when no field errors are present', function () {
        expect(invokePrivate($this->service, 'formatValidationErrors', [[]]))->toContain('rejected');
    });
});
