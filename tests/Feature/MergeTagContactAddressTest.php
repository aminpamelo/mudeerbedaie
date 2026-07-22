<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use App\Services\MergeTag\MergeTagEngine;
use App\Services\MergeTag\VariableRegistry;

it('resolves contact.address from a student full address', function () {
    $user = User::factory()->create(['name' => 'Ahmad Bin Ali']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'address_line_1' => '12 Jalan Merdeka',
        'address_line_2' => 'Taman Indah',
        'city' => 'Kuala Lumpur',
        'state' => 'Wilayah Persekutuan',
        'postcode' => '50000',
        'country' => 'Malaysia',
    ]);

    $resolved = (new MergeTagEngine)
        ->setContext(['student' => $student])
        ->resolve('Ship to: {{contact.address}}');

    expect($resolved)
        ->toContain('12 Jalan Merdeka')
        ->and($resolved)->toContain('Kuala Lumpur')
        ->and($resolved)->toContain('50000')
        ->and($resolved)->toContain('Malaysia')
        ->and($resolved)->not->toContain('{{contact.address}}');
});

it('resolves contact.address from a product order shipping address', function () {
    $order = ProductOrder::factory()->create([
        'shipping_address' => [
            'address_line_1' => '88 Persiaran Gurney',
            'city' => 'George Town',
            'state' => 'Pulau Pinang',
            'postal_code' => '10250',
            'country' => 'Malaysia',
        ],
    ]);

    $resolved = (new MergeTagEngine)
        ->setContext(['product_order' => $order])
        ->resolve('{{contact.address}}');

    expect($resolved)
        ->toContain('88 Persiaran Gurney')
        ->and($resolved)->toContain('George Town')
        ->and($resolved)->toContain('10250');
});

it('resolves contact.address from raw context values', function () {
    $resolved = (new MergeTagEngine)
        ->setContext(['address' => '5 Lorong Bunga, Ipoh, 30000, Malaysia'])
        ->resolve('{{contact.address}}');

    expect($resolved)->toBe('5 Lorong Bunga, Ipoh, 30000, Malaysia');
});

it('returns an empty string for a missing address rather than the raw tag', function () {
    $resolved = (new MergeTagEngine)
        ->setContext([])
        ->resolve('Address: {{contact.address}}');

    expect($resolved)->toBe('Address: ');
});

it('supports the default modifier when no address is on file', function () {
    $resolved = (new MergeTagEngine)
        ->setContext([])
        ->resolve('{{contact.address|default:"No address on file"}}');

    expect($resolved)->toBe('No address on file');
});

it('registers contact.address in the variable registry with a preview example', function () {
    $variables = VariableRegistry::getAllVariables();

    expect($variables['contact']['variables'])->toHaveKey('contact.address')
        ->and($variables['contact']['variables']['contact.address']['label'])->toBe('Address')
        ->and(VariableRegistry::getExampleValue('contact.address'))->not->toBeEmpty();
});
