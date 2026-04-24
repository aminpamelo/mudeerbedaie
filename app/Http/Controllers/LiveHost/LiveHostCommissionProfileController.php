<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreLiveHostCommissionProfileRequest;
use App\Http\Requests\LiveHost\UpdateLiveHostCommissionProfileRequest;
use App\Models\LiveHostCommissionProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class LiveHostCommissionProfileController extends Controller
{
    public function store(StoreLiveHostCommissionProfileRequest $request, User $host): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $this->rotate($host, $request->validated());

        return back()->with('success', 'Commission profile saved.');
    }

    public function update(UpdateLiveHostCommissionProfileRequest $request, User $host): RedirectResponse
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $this->rotate($host, $request->validated());

        return back()->with('success', 'Commission profile updated.');
    }

    /**
     * Deactivate any currently active profile for this host, then insert a new
     * active row. Wrapped in a transaction so the invariant holds.
     *
     * @param  array<string, mixed>  $data
     */
    private function rotate(User $host, array $data): void
    {
        DB::transaction(function () use ($host, $data) {
            $now = now();

            $active = LiveHostCommissionProfile::query()
                ->where('user_id', $host->id)
                ->where('is_active', true)
                ->get();

            foreach ($active as $row) {
                $row->is_active = false;
                $row->effective_to = $now;
                $row->save();
            }

            // The composite unique (user_id, effective_from) means if an old row
            // happened to share the same second, we nudge the new row forward.
            $newEffectiveFrom = $now;
            $collision = LiveHostCommissionProfile::query()
                ->where('user_id', $host->id)
                ->where('effective_from', $newEffectiveFrom)
                ->exists();
            if ($collision) {
                $newEffectiveFrom = $newEffectiveFrom->copy()->addSecond();
            }

            LiveHostCommissionProfile::create([
                'user_id' => $host->id,
                'base_salary_myr' => $data['base_salary_myr'],
                'per_live_rate_myr' => $data['per_live_rate_myr'],
                'upline_user_id' => $data['upline_user_id'] ?? null,
                'override_rate_l1_percent' => $data['override_rate_l1_percent'],
                'override_rate_l2_percent' => $data['override_rate_l2_percent'],
                'notes' => $data['notes'] ?? null,
                'effective_from' => $newEffectiveFrom,
                'effective_to' => null,
                'is_active' => true,
            ]);
        });
    }
}
