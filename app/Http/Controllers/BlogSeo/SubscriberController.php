<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Models\BlogSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

        $subscribers = $this->baseQuery($status, $search)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BlogSubscriber $s) => [
                'id' => $s->id,
                'email' => $s->email,
                'name' => $s->name,
                'locale' => $s->locale,
                'source' => $s->source,
                'from' => $s->post?->title,
                'isActive' => $s->is_active,
                'createdAt' => $s->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Subscribers', [
            'subscribers' => $subscribers,
            'filters' => ['status' => $status, 'search' => $search],
            'stats' => [
                'active' => BlogSubscriber::query()->active()->count(),
                'unsubscribed' => BlogSubscriber::query()->unsubscribed()->count(),
                'thisMonth' => BlogSubscriber::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'topPosts' => $this->topConvertingPosts(),
        ]);
    }

    public function destroy(BlogSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    /**
     * CSV export — the deliberate bridge to the CRM. Blog subscribers are
     * anonymous emails whereas CRM Audiences are Student-backed, so they are
     * exported for considered import rather than silently creating accounts.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->baseQuery(
            (string) $request->query('status', 'active'),
            trim((string) $request->query('search', ''))
        )->get();

        $filename = 'blog-subscribers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Email', 'Name', 'Language', 'Source', 'Signed up from', 'Subscribed at', 'Unsubscribed at']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->email,
                    $row->name,
                    $row->locale,
                    $row->source,
                    $row->post?->title,
                    $row->created_at?->toDateTimeString(),
                    $row->unsubscribed_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function baseQuery(string $status, string $search): Builder
    {
        return BlogSubscriber::query()
            ->with('post:id,title')
            ->when($search, fn (Builder $q, $v) => $q->where(function (Builder $sub) use ($v): void {
                $sub->where('email', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%");
            }))
            ->when($status === 'active', fn (Builder $q) => $q->active())
            ->when($status === 'unsubscribed', fn (Builder $q) => $q->unsubscribed())
            ->latest();
    }

    /**
     * Which articles actually convert readers into subscribers.
     *
     * @return list<array{title: string, count: int}>
     */
    private function topConvertingPosts(): array
    {
        return BlogSubscriber::query()
            ->whereNotNull('blog_post_id')
            ->with('post:id,title')
            ->get()
            ->groupBy('blog_post_id')
            ->map(fn ($group) => [
                'title' => $group->first()->post?->title ?? '—',
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->all();
    }
}
