<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Services\LiveHost\VideoReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only Video Report for the CMS module — lets the content team monitor the
 * host × category video matrix and read the feedback threads. Commenting stays
 * on the Live Host Desk; this surface is for monitoring only. Shares the exact
 * read model via {@see VideoReportService}.
 */
class CmsVideoReportController extends Controller
{
    public function __construct(private readonly VideoReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $window = $this->reports->window($request);
        $programs = $this->reports->programs($request);
        $matrix = $this->reports->matrix($programs['selected'], $window);

        return response()->json([
            'programs' => $matrix['programs'],
            'categories' => $matrix['categories'],
            'filters' => [
                'program' => $programs['selectedId'],
                'programOptions' => $programs['all']->map(fn ($p) => ['id' => $p->id, 'title' => $p->title])->values(),
            ],
            'window' => $window['meta'],
        ]);
    }

    public function cell(Request $request): JsonResponse
    {
        $mentee = LiveHostMentee::query()
            ->with('menteeUser:id,name')
            ->findOrFail($request->integer('mentee'));

        return response()->json($this->reports->cell(
            $mentee,
            (string) $request->string('category'),
            $this->reports->window($request),
            $request->user(),
        ));
    }
}
