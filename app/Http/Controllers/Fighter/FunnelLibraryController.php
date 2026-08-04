<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FunnelLibraryController extends Controller
{
    /**
     * The HQ funnels a fighter may copy — those admins flagged
     * `available_to_fighters`. Read-only browse view.
     */
    public function index(Request $request): Response
    {
        $funnels = Funnel::query()
            ->availableToFighters()
            ->withCount(['steps'])
            ->latest()
            ->get()
            ->map(fn (Funnel $funnel): array => [
                'uuid' => $funnel->uuid,
                'name' => $funnel->name,
                'description' => $funnel->description,
                'type' => $funnel->type,
                'steps_count' => $funnel->steps_count,
                'public_url' => $funnel->getPublicUrl(),
            ])
            ->values();

        return Inertia::render('FunnelLibrary', [
            'funnels' => $funnels,
        ]);
    }

    /**
     * Clone an available HQ funnel into a funnel owned by the current fighter,
     * then drop them into the builder to customise it.
     */
    public function copy(Request $request, Funnel $funnel): HttpResponse
    {
        abort_unless($funnel->available_to_fighters, 404);

        $copy = $funnel->copyForFighter((int) $request->user()->id);

        Log::info('Fighter copied a funnel from the library', [
            'source_funnel_id' => $funnel->id,
            'new_funnel_id' => $copy->id,
            'fighter_id' => $request->user()->id,
        ]);

        return Inertia::location($copy->getBuilderUrl().'?from=fighter');
    }
}
