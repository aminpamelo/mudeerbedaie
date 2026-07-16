<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreCommissionTierTemplateRequest;
use App\Models\LiveHostCommissionTierTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Master commission tier templates — reusable, named tier ladders a PIC can
 * apply to any host on any platform. Applying copies the ladder into the host's
 * own schedule (see HostController::storeTierSchedule); templates are never a
 * live link, so editing one here never disturbs hosts already set up.
 */
class CommissionTierTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        $templates = LiveHostCommissionTierTemplate::query()
            ->with('createdBy:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (LiveHostCommissionTierTemplate $t) => $this->shape($t))
            ->all();

        return Inertia::render('commission-templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(StoreCommissionTierTemplateRequest $request): RedirectResponse
    {
        LiveHostCommissionTierTemplate::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'tiers' => $this->normalizeTiers($request->validated('tiers')),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Commission template created.');
    }

    public function update(StoreCommissionTierTemplateRequest $request, LiveHostCommissionTierTemplate $template): RedirectResponse
    {
        $template->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'tiers' => $this->normalizeTiers($request->validated('tiers')),
        ]);

        return back()->with('success', 'Commission template updated.');
    }

    public function destroy(Request $request, LiveHostCommissionTierTemplate $template): RedirectResponse
    {
        $this->authorizeManager($request);

        $template->delete();

        return back()->with('success', 'Commission template deleted.');
    }

    private function authorizeManager(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin_livehost', 'admin'], true), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(LiveHostCommissionTierTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'tiers' => $this->normalizeTiers($template->tiers ?? []),
            'tier_count' => count($template->tiers ?? []),
            'created_by' => $template->createdBy?->name,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Canonicalise a tier ladder: typed values, open-ended max as null, sorted
     * by tier_number. Applied on both write and read so the shape never drifts.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array{tier_number: int, min_gmv_myr: float, max_gmv_myr: float|null, internal_percent: float, l1_percent: float, l2_percent: float}>
     */
    private function normalizeTiers(array $tiers): array
    {
        return collect($tiers)
            ->map(fn (array $t) => [
                'tier_number' => (int) $t['tier_number'],
                'min_gmv_myr' => (float) $t['min_gmv_myr'],
                'max_gmv_myr' => isset($t['max_gmv_myr']) && $t['max_gmv_myr'] !== null ? (float) $t['max_gmv_myr'] : null,
                'internal_percent' => (float) $t['internal_percent'],
                'l1_percent' => (float) $t['l1_percent'],
                'l2_percent' => (float) $t['l2_percent'],
            ])
            ->sortBy('tier_number')
            ->values()
            ->all();
    }
}
