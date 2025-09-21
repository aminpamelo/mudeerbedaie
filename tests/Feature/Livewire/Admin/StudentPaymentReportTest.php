<?php

use Livewire\Volt\Volt;

it('can render', function () {
    $component = Volt::test('admin.student-payment-report');

    $component->assertSee('');
});
