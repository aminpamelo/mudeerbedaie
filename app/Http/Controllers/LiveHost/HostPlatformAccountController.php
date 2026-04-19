<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreHostPlatformAccountRequest;
use App\Http\Requests\LiveHost\UpdateHostPlatformAccountRequest;
use App\Models\LiveHostPlatformAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Task 24: links a live host to a platform account with creator identity
 * (handle + TikTok internal user id) and a default (is_primary) flag used
 * when auto-picking the identity during session-slot creation.
 *
 * The pivot table enforces a unique (user_id, platform_account_id) pair, so
 * `attach` doubles as an upsert — re-attaching the same pair updates the
 * creator fields in place. The `is_primary=true` transition flips every
 * OTHER pivot row belonging to the same host to false, inside a DB
 * transaction so the "exactly one primary per host" invariant holds even
 * under concurrent writes.
 */
class HostPlatformAccountController extends Controller
{
    public function attach(
        StoreHostPlatformAccountRequest $request,
        User $host,
        PlatformAccount $platformAccount,
    ): RedirectResponse {
        abort_unless($host->role === 'live_host', 404);

        $data = $request->validated();

        DB::transaction(function () use ($host, $platformAccount, $data) {
            $pivot = LiveHostPlatformAccount::query()
                ->where('user_id', $host->id)
                ->where('platform_account_id', $platformAccount->id)
                ->first();

            if ($pivot) {
                $pivot->fill([
                    'creator_handle' => $data['creator_handle'] ?? null,
                    'creator_platform_user_id' => $data['creator_platform_user_id'] ?? null,
                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                ])->save();
            } else {
                $pivot = LiveHostPlatformAccount::create([
                    'user_id' => $host->id,
                    'platform_account_id' => $platformAccount->id,
                    'creator_handle' => $data['creator_handle'] ?? null,
                    'creator_platform_user_id' => $data['creator_platform_user_id'] ?? null,
                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                ]);
            }

            $this->demoteOtherPrimaries($host, $pivot);
        });

        return back()->with('success', 'Host attached to platform account.');
    }

    public function update(
        UpdateHostPlatformAccountRequest $request,
        User $host,
        PlatformAccount $platformAccount,
    ): RedirectResponse {
        abort_unless($host->role === 'live_host', 404);

        $pivot = LiveHostPlatformAccount::query()
            ->where('user_id', $host->id)
            ->where('platform_account_id', $platformAccount->id)
            ->firstOrFail();

        $data = $request->validated();

        DB::transaction(function () use ($pivot, $host, $data) {
            if (array_key_exists('creator_handle', $data)) {
                $pivot->creator_handle = $data['creator_handle'];
            }
            if (array_key_exists('creator_platform_user_id', $data)) {
                $pivot->creator_platform_user_id = $data['creator_platform_user_id'];
            }
            if (array_key_exists('is_primary', $data)) {
                $pivot->is_primary = (bool) $data['is_primary'];
            }
            $pivot->save();

            $this->demoteOtherPrimaries($host, $pivot);
        });

        return back()->with('success', 'Host platform identity updated.');
    }

    public function detach(User $host, PlatformAccount $platformAccount): RedirectResponse
    {
        abort_unless($host->role === 'live_host', 404);

        $user = request()->user();
        abort_unless(
            $user && in_array($user->role, ['admin_livehost', 'admin'], true),
            403
        );

        LiveHostPlatformAccount::query()
            ->where('user_id', $host->id)
            ->where('platform_account_id', $platformAccount->id)
            ->delete();

        return back()->with('success', 'Host detached from platform account.');
    }

    /**
     * When the freshly-saved pivot is flagged primary, demote every other
     * pivot row belonging to this host so the "one primary per host"
     * invariant holds. No-op when the saved row is not primary.
     */
    private function demoteOtherPrimaries(User $host, LiveHostPlatformAccount $saved): void
    {
        if (! $saved->is_primary) {
            return;
        }

        LiveHostPlatformAccount::query()
            ->where('user_id', $host->id)
            ->where('id', '!=', $saved->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
