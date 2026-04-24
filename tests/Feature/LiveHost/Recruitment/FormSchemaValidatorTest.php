<?php

use App\Exceptions\Recruitment\InvalidFormSchemaException;
use App\Services\Recruitment\FormSchemaValidator;
use App\Support\Recruitment\DefaultFormSchema;

it('accepts the default schema', function () {
    (new FormSchemaValidator)->validate(DefaultFormSchema::get());

    expect(true)->toBeTrue();
});

it('rejects a schema with no email role', function () {
    $schema = DefaultFormSchema::get();
    foreach ($schema['pages'] as &$page) {
        foreach ($page['fields'] as &$field) {
            if (($field['role'] ?? null) === 'email') {
                unset($field['role']);
            }
        }
    }
    unset($page, $field);

    expect(fn () => (new FormSchemaValidator)->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, 'role "email"');
});

it('rejects duplicate field ids', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'dup', 'type' => 'text', 'label' => 'A'],
                ['id' => 'dup', 'type' => 'email', 'label' => 'B', 'role' => 'email'],
            ],
        ]],
    ];

    expect(fn () => (new FormSchemaValidator)->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, "'dup' is duplicated");
});

it('rejects incompatible role and type', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'x', 'type' => 'text', 'label' => 'X', 'role' => 'email'],
            ],
        ]],
    ];

    expect(fn () => (new FormSchemaValidator)->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, 'incompatible');
});

it('rejects choice field with no options', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'e', 'type' => 'email', 'label' => 'E', 'role' => 'email'],
                ['id' => 's', 'type' => 'select', 'label' => 'S'],
            ],
        ]],
    ];

    expect(fn () => (new FormSchemaValidator)->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, 'at least one option');
});
