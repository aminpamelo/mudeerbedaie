<?php

namespace App\Http\Controllers;

use App\Models\AiSalesPage;
use App\Services\Ai\SalesPageRenderer;
use Illuminate\Http\Response;

class PublicAiSalesPageController extends Controller
{
    public function show(string $slug, SalesPageRenderer $renderer): Response
    {
        $page = AiSalesPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $html = $renderer->published($page);

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
