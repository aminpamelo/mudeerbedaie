<?php

namespace App\Http\Controllers\BlogSeo;

use App\Console\Commands\BlogAutoWriteArticles;
use App\Http\Controllers\Controller;
use App\Jobs\WriteBlogArticleJob;
use App\Models\BlogIssue;
use App\Services\Blog\Ai\IssueCapturer;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Isu Semasa" — the AI article pipeline: captured news headlines scored for
 * Islamic commentary, one-click (or daily automatic) draft articles with
 * hadith/ayat + a BeDaie book CTA, for the editor to review and publish.
 */
class IssueController extends Controller
{
    public function index(Request $request, SettingsService $settings): Response
    {
        $view = in_array($request->query('view'), ['suggested', 'all', 'written'], true) ? $request->query('view') : 'suggested';

        $issues = BlogIssue::query()
            ->with('post:id,title,status')
            ->when($view === 'suggested', fn ($q) => $q->where(fn ($w) => $w->where('relevance', '>=', BlogIssue::MIN_RELEVANCE)->whereIn('status', [BlogIssue::STATUS_NEW, BlogIssue::STATUS_WRITING, BlogIssue::STATUS_FAILED])))
            ->when($view === 'all', fn ($q) => $q->where('status', '!=', BlogIssue::STATUS_DISMISSED))
            ->when($view === 'written', fn ($q) => $q->where('status', BlogIssue::STATUS_WRITTEN))
            ->orderByRaw('CASE WHEN relevance IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc($view === 'written' ? 'updated_at' : 'relevance')
            ->orderByDesc('published_at')
            ->limit(100)
            ->get()
            ->map(fn (BlogIssue $issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'summary' => $issue->summary,
                'source' => $issue->source,
                'url' => $issue->url,
                'published_at' => $issue->published_at?->toIso8601String(),
                'relevance' => $issue->relevance,
                'angle' => $issue->angle,
                'status' => $issue->status,
                'error' => $issue->error,
                'post' => $issue->post ? [
                    'id' => $issue->post->id,
                    'title' => $issue->post->title,
                    'status' => $issue->post->status,
                    'edit_url' => route('blogseo.posts.edit', $issue->post),
                ] : null,
            ]);

        return Inertia::render('Issues', [
            'issues' => $issues,
            'view' => $view,
            'counts' => [
                'suggested' => BlogIssue::query()->suggested()->count(),
                'written' => BlogIssue::where('status', BlogIssue::STATUS_WRITTEN)->count(),
                'all' => BlogIssue::where('status', '!=', BlogIssue::STATUS_DISMISSED)->count(),
            ],
            'autoDailyCount' => (int) $settings->get(BlogAutoWriteArticles::SETTING, 2),
            'lastCapturedAt' => BlogIssue::max('created_at'),
            'aiReady' => filled(config('openai.api_key')),
            'sources' => array_keys((array) config('blog.ai.feeds', [])),
        ]);
    }

    public function capture(IssueCapturer $capturer): RedirectResponse
    {
        $result = $capturer->capture();

        $message = "{$result['captured']} isu baru ditangkap.";
        if ($result['failed_feeds'] !== []) {
            $message .= ' Gagal: '.implode(', ', $result['failed_feeds']).'.';
        }

        return back()->with('success', $message);
    }

    public function write(Request $request, BlogIssue $issue): RedirectResponse
    {
        if ($issue->status === BlogIssue::STATUS_WRITING) {
            return back()->with('error', 'Artikel untuk isu ini sedang ditulis.');
        }

        $issue->update(['status' => BlogIssue::STATUS_WRITING, 'error' => null]);
        WriteBlogArticleJob::dispatch($issue, (int) $request->user()->id);

        return back()->with('success', 'AI sedang menulis artikel — siap dalam 1–2 minit sebagai draf.');
    }

    public function dismiss(BlogIssue $issue): RedirectResponse
    {
        $issue->update(['status' => $issue->status === BlogIssue::STATUS_DISMISSED ? BlogIssue::STATUS_NEW : BlogIssue::STATUS_DISMISSED]);

        return back();
    }

    public function settings(Request $request, SettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'auto_daily_count' => ['required', 'integer', 'min:0', 'max:5'],
        ], [
            'auto_daily_count.max' => 'Maksimum 5 artikel automatik sehari.',
        ]);

        $settings->set(BlogAutoWriteArticles::SETTING, (int) $validated['auto_daily_count'], 'number', 'blog', 'AI drafts auto-written per day from current issues (0 = off)');

        return back()->with('success', $validated['auto_daily_count'] > 0
            ? "Auto-tulis aktif: {$validated['auto_daily_count']} draf setiap pagi (7:30)."
            : 'Auto-tulis dimatikan.');
    }
}
