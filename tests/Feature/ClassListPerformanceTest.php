<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('class list page loads successfully', function () {
    $this->get(route('classes.index'))
        ->assertSuccessful();
});

test('class list displays stats', function () {
    Volt::test('admin.class-list')
        ->assertSeeHtml('Total Classes')
        ->assertSeeHtml('Active')
        ->assertSeeHtml('Upcoming')
        ->assertSuccessful();
});

test('class list search works', function () {
    Volt::test('admin.class-list')
        ->set('search', 'Tajweed')
        ->assertSuccessful();
});

test('class list view modes work', function () {
    Volt::test('admin.class-list')
        ->call('setViewMode', 'list')
        ->assertSet('viewMode', 'list')
        ->assertSuccessful()
        ->call('setViewMode', 'grouped')
        ->assertSet('viewMode', 'grouped')
        ->assertSuccessful()
        ->call('setViewMode', 'pic')
        ->assertSet('viewMode', 'pic')
        ->assertSuccessful();
});

test('class list clear filters resets all filters', function () {
    Volt::test('admin.class-list')
        ->set('search', 'test')
        ->set('statusFilter', 'active')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('statusFilter', '')
        ->assertSet('courseFilter', '')
        ->assertSuccessful();
});
