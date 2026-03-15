<?php

use App\Models\Audience;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audience show page displays audience details', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $audience = Audience::factory()->create(['name' => 'VIP Students', 'status' => 'active']);

    $students = Student::factory()->count(3)->create();
    $audience->students()->attach($students->pluck('id'), ['subscribed_at' => now()]);

    $this->actingAs($admin)
        ->get(route('crm.audiences.show', $audience))
        ->assertSuccessful()
        ->assertSee('VIP Students')
        ->assertSee('3'); // total members count
});

test('audience show page is searchable', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $audience = Audience::factory()->create();

    $user1 = User::factory()->create(['name' => 'Ahmad Amin']);
    $user2 = User::factory()->create(['name' => 'Jane Doe']);
    $student1 = Student::factory()->create(['user_id' => $user1->id]);
    $student2 = Student::factory()->create(['user_id' => $user2->id]);
    $audience->students()->attach([$student1->id, $student2->id], ['subscribed_at' => now()]);

    Livewire\Volt\Volt::test('crm.audience-show', ['audience' => $audience])
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad')
        ->assertDontSee('Jane');
});

test('audience list page has view link', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $audience = Audience::factory()->create(['name' => 'Test Audience']);

    $this->actingAs($admin)
        ->get(route('crm.audiences.index'))
        ->assertSuccessful()
        ->assertSee('View');
});
