<?php

use App\Models\LiveSlotTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('lists slot templates on the index page', function () {
    LiveSlotTemplate::factory()->create(['name' => 'Standard 8-slot']);

    actingAs($this->pic)
        ->get('/livehost/slot-templates')
        ->assertInertia(fn (Assert $p) => $p
            ->component('slot-templates/Index', false)
            ->has('templates', 1)
            ->where('templates.0.name', 'Standard 8-slot')
            ->where('templates.0.slotCount', 2));
});

it('creates a slot template and normalises slot order + time format', function () {
    actingAs($this->pic)
        ->post('/livehost/slot-templates', [
            'name' => 'Ramadan hours',
            'description' => 'Late nights',
            'slots' => [
                ['day_of_week' => 3, 'start_time' => '20:00', 'end_time' => '22:00'],
                ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '11:00'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $template = LiveSlotTemplate::firstWhere('name', 'Ramadan hours');
    expect($template)->not->toBeNull()
        ->and($template->created_by)->toBe($this->pic->id)
        // sorted by day then start
        ->and($template->slots[0])->toMatchArray(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '11:00'])
        ->and($template->slots[1])->toMatchArray(['day_of_week' => 3, 'start_time' => '20:00', 'end_time' => '22:00']);
});

it('rejects a template with no slots', function () {
    actingAs($this->pic)
        ->post('/livehost/slot-templates', ['name' => 'Empty', 'slots' => []])
        ->assertSessionHasErrors('slots');
});

it('rejects a slot whose end is not after the start', function () {
    actingAs($this->pic)
        ->post('/livehost/slot-templates', [
            'name' => 'Bad window',
            'slots' => [
                ['day_of_week' => 1, 'start_time' => '11:00', 'end_time' => '09:00'],
            ],
        ])
        ->assertSessionHasErrors('slots.0.end_time');
});

it('updates a slot template', function () {
    $template = LiveSlotTemplate::factory()->create(['name' => 'Old name']);

    actingAs($this->pic)
        ->put("/livehost/slot-templates/{$template->id}", [
            'name' => 'New name',
            'slots' => [['day_of_week' => 5, 'start_time' => '08:00', 'end_time' => '10:00']],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($template->fresh())
        ->name->toBe('New name')
        ->slots->toBe([['day_of_week' => 5, 'start_time' => '08:00', 'end_time' => '10:00']]);
});

it('deletes a slot template', function () {
    $template = LiveSlotTemplate::factory()->create();

    actingAs($this->pic)
        ->delete("/livehost/slot-templates/{$template->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(LiveSlotTemplate::find($template->id))->toBeNull();
});

it('exposes active templates on the calendar for the override modal', function () {
    LiveSlotTemplate::factory()->create(['name' => 'Weekend heavy', 'is_active' => true]);
    LiveSlotTemplate::factory()->create(['name' => 'Retired', 'is_active' => false]);

    actingAs($this->pic)
        ->get('/livehost/session-slots/calendar')
        ->assertInertia(fn (Assert $p) => $p
            ->has('slotTemplates', 1)
            ->where('slotTemplates.0.name', 'Weekend heavy')
            ->has('slotTemplates.0.slots')
            ->etc());
});

it('forbids a live_host from managing slot templates', function () {
    actingAs(User::factory()->create(['role' => 'live_host']))
        ->post('/livehost/slot-templates', [
            'name' => 'X',
            'slots' => [['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '11:00']],
        ])
        ->assertForbidden();
});
