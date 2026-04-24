<?php

use App\Models\User;

it('can create a livehost_assistant user and detect the role', function () {
    $user = User::factory()->liveHostAssistant()->create();

    expect($user->role)->toBe('livehost_assistant');
    expect($user->isLiveHostAssistant())->toBeTrue();
    expect($user->isAdminLiveHost())->toBeFalse();
    expect($user->isLiveHost())->toBeFalse();
});
