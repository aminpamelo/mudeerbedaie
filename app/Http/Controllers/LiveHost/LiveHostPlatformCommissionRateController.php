<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreLiveHostPlatformCommissionRateRequest;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class LiveHostPlatformCommissionRateController extends Controller
{
    public function store(StoreLiveHostPlatformCommissionRateRequest $request, User $host): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $this->rotate($host, $request->validated());

        return back()->with('success', 'Platform rate saved.');
    }

    public function update(
        StoreLiveHostPlatformCommissionRateRequest $request,
        User $host,
        LiveHostPlatformCommissionRate $rate,
    ): RedirectResponse {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);
        abort_unless($rate->user_id === $host->id, 404);

        $this->rotate($host, $request->validated());

        return back()->with('success', 'Platform rate updated.');
    }

    /**
     * Deactivate the active rate for this (host, platform) tuple, then insert a
     * fresh active row.
     *
     * @param  array<string, mixed>  $data
     */
    private function rotate(User $host, array $data): void
    {
        DB::transaction(function () use ($host, $data) {
            $now = now();
            $platformId = (int) $data['platform_id'];

            $active = LiveHostPlatformCommissionRate::query()
                ->where('user_id', $host->id)
                ->where('platform_id', $platformId)
                ->where('is_active', true)
                ->get();

            foreach ($active as $row) {
                $row->is_active = false;
                $row->effective_to = $now;
                $row->save();
            }

            // The composite unique (user_id, platform_id, effective_from)
            // blocks exact-timestamp collisions; nudge if needed.
            $newEffectiveFrom = $now;
            $collision = LiveHostPlatformCommissionRate::query()
                ->where('user_id', $host->id)
                ->where('platform_id', $platformId)
                ->where('effective_from', $newEffectiveFrom)
                ->exists();
            if ($collision) {
                $newEffectiveFrom = $newEffectiveFrom->copy()->addSecond();
            }

            LiveHostPlatformCommissionRate::create([
                'user_id' => $host->id,
                'platform_id' => $platformId,
                'commission_rate_percent' => $data['commission_rate_percent'],
                'effective_from' => $newEffectiveFrom,
                'effective_to' => null,
                'is_active' => true,
            ]);
        });
    }
}
