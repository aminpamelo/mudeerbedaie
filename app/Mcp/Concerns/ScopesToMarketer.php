<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\FacebookAdAccount;
use App\Models\Funnel;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes MCP tool data to the authenticated marketer, mirroring the Funnel
 * Studio web rules: fighters only ever see funnels they own and spend from ad
 * accounts under their own connections; admin/employee see everything.
 */
trait ScopesToMarketer
{
    /**
     * Funnel ids the given user may see.
     *
     * @return array<int, int>
     */
    protected function funnelIdsFor(User $user): array
    {
        return Funnel::query()
            ->when($user->isFighter(), fn (Builder $q) => $q->where('user_id', $user->id))
            ->pluck('id')
            ->all();
    }

    /**
     * Whether the user may see raw ad spend (admin/employee only).
     */
    protected function canSeeSpend(User $user): bool
    {
        return in_array($user->role, ['admin', 'employee'], true);
    }

    /**
     * Ad-account ids whose spend the given user may see.
     *
     * @return array<int, int>
     */
    protected function adAccountIdsFor(User $user): array
    {
        return FacebookAdAccount::query()
            ->when(! $this->canSeeSpend($user), fn (Builder $q) => $q->whereHas(
                'connection',
                fn (Builder $c) => $c->where('user_id', $user->id)
            ))
            ->pluck('id')
            ->all();
    }

    /**
     * Resolve a funnel by uuid, but only if the given user may see it
     * (fighters are limited to funnels they own). Null if out of scope.
     */
    protected function findScopedFunnel(User $user, string $uuid): ?Funnel
    {
        return Funnel::query()
            ->when($user->isFighter(), fn (Builder $q) => $q->where('user_id', $user->id))
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Products the given user may sell in a funnel: active catalog products,
     * limited to HQ + their own products when the user is a fighter.
     */
    protected function sellableProductsQuery(User $user): Builder
    {
        return Product::query()
            ->where('status', 'active')
            ->when($user->isFighter(), fn (Builder $q) => $q->sellableByFighter($user->id));
    }
}
