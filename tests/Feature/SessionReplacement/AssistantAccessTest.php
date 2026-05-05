<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->assistant = User::factory()->create(['role' => 'livehost_assistant']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
    ]);
});

it('lets a livehost_assistant view the replacement queue', function () {
    SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $this->actingAs($this->assistant)
        ->get(route('livehost.replacements.index'))
        ->assertOk();
});

it('lets a livehost_assistant view a single replacement request', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $this->actingAs($this->assistant)
        ->get(route('livehost.replacements.show', $req))
        ->assertOk();
});

it('lets a livehost_assistant assign a replacement', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);
    $candidate = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($this->assistant)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $candidate->id,
        ])
        ->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe('assigned');
    expect($req->replacement_host_id)->toBe($candidate->id);
    expect($req->assigned_by_id)->toBe($this->assistant->id);
});

it('lets a livehost_assistant reject a replacement', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $this->actingAs($this->assistant)
        ->post(route('livehost.replacements.reject', $req), [
            'rejection_reason' => 'Tiada pengganti tersedia.',
        ])
        ->assertRedirect();

    expect($req->fresh()->status)->toBe('rejected');
});
