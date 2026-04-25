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

it('shows the request with available replacement hosts excluding overlapping ones', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $candidate = User::factory()->create(['role' => 'live_host']);
    $busyHost = User::factory()->create(['role' => 'live_host']);

    // busyHost has an assignment overlapping the same time slot on the same day_of_week
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $busyHost->id,
        'day_of_week' => $this->assignment->day_of_week,
        'time_slot_id' => $this->assignment->time_slot_id,
    ]);

    $response = $this->actingAs($this->pic)
        ->get(route('livehost.replacements.show', $req));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Replacements/Show', false)
            ->where('request.id', $req->id)
            ->has('availableHosts', 1, fn ($h) => $h->where('id', $candidate->id)->etc())
    );

    // Assert busyHost is NOT in availableHosts.
    $payload = $response->viewData('page')['props']['availableHosts'];
    expect(collect($payload)->pluck('id')->all())->not->toContain($busyHost->id);
});

it('lets PIC assign a one_date replacement', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);
    $candidate = User::factory()->create(['role' => 'live_host']);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $candidate->id,
        ]);

    $response->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe('assigned');
    expect($req->replacement_host_id)->toBe($candidate->id);
    expect($req->assigned_by_id)->toBe($this->pic->id);
    expect($req->assigned_at)->not->toBeNull();
});

it('rejects assigning a host who already has an overlapping slot', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);
    $busy = User::factory()->create(['role' => 'live_host']);
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $busy->id,
        'day_of_week' => $this->assignment->day_of_week,
        'time_slot_id' => $this->assignment->time_slot_id,
    ]);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $busy->id,
        ]);

    $response->assertSessionHasErrors('replacement_host_id');
    expect($req->fresh()->status)->toBe('pending');
});

it('cannot re-assign an already-assigned request', function () {
    $candidate = User::factory()->create(['role' => 'live_host']);
    $req = SessionReplacementRequest::factory()->assigned($candidate)->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);
    $other = User::factory()->create(['role' => 'live_host']);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $other->id,
        ]);

    $response->assertStatus(422);
});

it('transfers assignment ownership when scope is permanent', function () {
    $req = SessionReplacementRequest::factory()
        ->pending()
        ->permanent()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);
    $candidate = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $candidate->id,
        ]);

    expect($this->assignment->fresh()->live_host_id)->toBe($candidate->id);
    expect($req->fresh()->status)->toBe('assigned');
});

it('lets PIC reject a pending request with a reason', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.reject', $req), [
            'rejection_reason' => 'Slot tidak boleh diganti minggu ini.',
        ]);

    $response->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe('rejected');
    expect($req->rejection_reason)->toBe('Slot tidak boleh diganti minggu ini.');
});

it('requires a rejection_reason', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.reject', $req), []);

    $response->assertSessionHasErrors('rejection_reason');
});
