<?php

namespace App\Policies;

use App\Models\User;

class LiveHostPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isPic($actor);
    }

    public function view(User $actor, User $host): bool
    {
        return $this->isPic($actor) && $host->role === 'live_host';
    }

    public function create(User $actor): bool
    {
        return $this->isPic($actor);
    }

    public function update(User $actor, User $host): bool
    {
        return $this->isPic($actor) && $host->role === 'live_host';
    }

    public function delete(User $actor, User $host): bool
    {
        return $this->isPic($actor) && $host->role === 'live_host';
    }

    private function isPic(User $actor): bool
    {
        return in_array($actor->role, ['admin_livehost', 'admin'], true);
    }
}
