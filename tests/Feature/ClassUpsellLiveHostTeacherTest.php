<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('live hosts can be picked as upsell session teachers', function () {
    $admin = User::factory()->admin()->create();
    $class = ClassModel::factory()->create(['status' => 'active']);
    $host = User::factory()->create(['role' => 'live_host', 'status' => 'active', 'name' => 'Host Aisyah']);
    $inactiveHost = User::factory()->create(['role' => 'live_host', 'status' => 'inactive', 'name' => 'Host Lama']);
    $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Pelajar Biasa']);

    $this->actingAs($admin);

    $component = Volt::test('admin.class-show', ['class' => $class]);
    $names = $component->instance()->upsellAvailableTeachers->pluck('name');

    expect($names)->toContain('Host Aisyah')
        ->not->toContain('Host Lama')
        ->not->toContain('Pelajar Biasa');

    $session = ClassSession::factory()->create(['class_id' => $class->id]);
    $component->call('addSessionTeacher', $session->id, $host->id);

    expect($session->fresh()->upsell_teacher_ids)->toBe([$host->id]);
});
