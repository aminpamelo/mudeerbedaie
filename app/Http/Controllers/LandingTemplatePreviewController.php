<?php

namespace App\Http\Controllers;

use App\Models\LandingPageTemplate;
use App\Services\Funnel\PuckRenderer;
use Illuminate\Http\Response;

/**
 * Renders a Landing Library template on its own (no funnel, no checkout)
 * for the preview frame in the Studio.
 */
class LandingTemplatePreviewController extends Controller
{
    public function __invoke(string $uuid, PuckRenderer $renderer): Response
    {
        $template = LandingPageTemplate::query()->where('uuid', $uuid)->firstOrFail();
        $content = $template->content ?? ['content' => [], 'root' => []];

        if ($renderer->isFullPageHtml($content)) {
            return response($renderer->extractFullPageHtml($content))
                ->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('funnel.landing-template-preview', [
            'template' => $template,
            'renderedContent' => $renderer->render($content, ['preview' => true]),
        ])->header('X-Robots-Tag', 'noindex');
    }
}
