<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\User;
use App\Rules\NoCircularUpline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Build a chain: Ahmad (no upline) -> Sarah (upline=Ahmad) -> Amin (upline=Sarah)
    $this->ahmad = User::factory()->create(['name' => 'Ahmad']);
    $this->sarah = User::factory()->create(['name' => 'Sarah']);
    $this->amin = User::factory()->create(['name' => 'Amin']);
    $this->unrelated = User::factory()->create(['name' => 'Unrelated']);

    LiveHostCommissionProfile::factory()->for($this->ahmad)->create([
        'upline_user_id' => null,
    ]);
    LiveHostCommissionProfile::factory()->for($this->sarah)->create([
        'upline_user_id' => $this->ahmad->id,
    ]);
    LiveHostCommissionProfile::factory()->for($this->amin)->create([
        'upline_user_id' => $this->sarah->id,
    ]);
});

it('passes when upline is an unrelated user', function () {
    $validator = Validator::make(
        ['upline_user_id' => $this->unrelated->id],
        ['upline_user_id' => [new NoCircularUpline(targetUserId: $this->sarah->id)]],
    );
    expect($validator->passes())->toBeTrue();
});

it('passes when upline is null', function () {
    $validator = Validator::make(
        ['upline_user_id' => null],
        ['upline_user_id' => [new NoCircularUpline(targetUserId: $this->sarah->id)]],
    );
    expect($validator->passes())->toBeTrue();
});

it('fails when setting upline to the user themself', function () {
    $validator = Validator::make(
        ['upline_user_id' => $this->ahmad->id],
        ['upline_user_id' => [new NoCircularUpline(targetUserId: $this->ahmad->id)]],
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('upline_user_id'))->toContain('circular');
});

it('fails when setting a direct descendants as upline (would create cycle)', function () {
    // Ahmad has downline Sarah. Setting Ahmad.upline = Sarah creates Ahmad -> Sarah -> Ahmad cycle.
    $validator = Validator::make(
        ['upline_user_id' => $this->sarah->id],
        ['upline_user_id' => [new NoCircularUpline(targetUserId: $this->ahmad->id)]],
    );
    expect($validator->fails())->toBeTrue();
});

it('fails when setting an L2 descendant as upline (would create longer cycle)', function () {
    // Ahmad -> Sarah -> Amin. Setting Ahmad.upline = Amin creates Ahmad -> Amin -> Sarah -> Ahmad cycle.
    $validator = Validator::make(
        ['upline_user_id' => $this->amin->id],
        ['upline_user_id' => [new NoCircularUpline(targetUserId: $this->ahmad->id)]],
    );
    expect($validator->fails())->toBeTrue();
});

it('passes when setting upline on a new user (no existing downlines)', function () {
    $newHost = User::factory()->create();
    // newHost has no profile yet, no downlines - setting their upline to Amin is fine
    $validator = Validator::make(
        ['upline_user_id' => $this->amin->id],
        ['upline_user_id' => [new NoCircularUpline(targetUserId: $newHost->id)]],
    );
    expect($validator->passes())->toBeTrue();
});

it('rejects when upline chain is deeper than reasonable (runaway cycle safety)', function () {
    // Create a 25-long chain. Then try to set the head's upline to the tail - should fail cycle detection.
    // This is more of a "depth limit" safety test - the rule should not hang indefinitely.
    $users = collect(range(1, 25))->map(fn ($i) => User::factory()->create())->all();
    for ($i = 1; $i < 25; $i++) {
        LiveHostCommissionProfile::factory()->for($users[$i])->create([
            'upline_user_id' => $users[$i - 1]->id,
        ]);
    }
    LiveHostCommissionProfile::factory()->for($users[0])->create();

    $validator = Validator::make(
        ['upline_user_id' => $users[24]->id],
        ['upline_user_id' => [new NoCircularUpline(targetUserId: $users[0]->id)]],
    );
    // Should fail (users[24] is descendant of users[0])
    expect($validator->fails())->toBeTrue();
});
