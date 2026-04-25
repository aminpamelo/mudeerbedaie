<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
    ]);
});

it('shows pending replacement requests on the PIC index', function () {
    $pending = SessionReplacementRequest::factory()
        ->pending()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);

    $resolved = SessionReplacementRequest::factory()
        ->expired()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);

    $response = $this->actingAs($this->pic)
        ->get(route('livehost.replacements.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Replacements/Index', false)
            ->where('counts.pending', 1)
            ->where('counts.expired', 1)
            ->has('requests', 1, fn ($r) => $r->where('id', $pending->id)->etc())
    );
});

it('forbids non-PIC users from the index', function () {
    $response = $this->actingAs($this->host)->get(route('livehost.replacements.index'));
    $response->assertForbidden();
});
