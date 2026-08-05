<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', BlogComment::STATUS_PENDING);
        $search = trim((string) $request->query('search', ''));

        $comments = BlogComment::query()
            ->with(['post:id,title,slug', 'user:id,name'])
            ->when($status, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($search, fn (Builder $q, $v) => $q->where(function (Builder $sub) use ($v): void {
                $sub->where('body', 'like', "%{$v}%")->orWhere('author_name', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BlogComment $c) => [
                'id' => $c->id,
                'author' => $c->display_name,
                'initials' => $c->initials,
                'body' => $c->body,
                'status' => $c->status,
                'isReply' => $c->parent_id !== null,
                'postId' => $c->blog_post_id,
                'postTitle' => $c->post?->title,
                'postUrl' => $c->post ? route('blog.show', $c->post->slug).'#comments' : null,
                'createdAt' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Comments', [
            'comments' => $comments,
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => [
                'pending' => BlogComment::query()->pending()->count(),
                'approved' => BlogComment::query()->approved()->count(),
                'spam' => BlogComment::where('status', BlogComment::STATUS_SPAM)->count(),
            ],
        ]);
    }

    public function approve(Request $request, BlogComment $comment): RedirectResponse
    {
        $comment->approve($request->user()->id);

        return back()->with('success', 'Comment approved.');
    }

    public function unapprove(BlogComment $comment): RedirectResponse
    {
        $comment->update(['status' => BlogComment::STATUS_PENDING, 'approved_at' => null]);

        return back()->with('success', 'Moved back to pending.');
    }

    public function spam(BlogComment $comment): RedirectResponse
    {
        $comment->update(['status' => BlogComment::STATUS_SPAM]);

        return back()->with('success', 'Marked as spam.');
    }

    public function destroy(BlogComment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }

    public function approveAll(Request $request): RedirectResponse
    {
        $ids = BlogComment::query()->pending()->pluck('id');

        BlogComment::whereIn('id', $ids)->update([
            'status' => BlogComment::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', $ids->count().' comment(s) approved.');
    }
}
