<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreCommentRequest;
use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    /**
     * Post a comment or a reply. Comments land in moderation unless the site
     * config turns that off, so nothing appears publicly unreviewed.
     */
    public function store(StoreCommentRequest $request, BlogPost $post): RedirectResponse
    {
        abort_unless($post->is_published && $post->allow_comments, 404);

        $parentId = $request->integer('parent_id') ?: null;

        // A reply must belong to the same article — otherwise a crafted parent_id
        // could graft a comment thread onto a different post.
        if ($parentId !== null) {
            $parentBelongs = BlogComment::query()
                ->whereKey($parentId)
                ->where('blog_post_id', $post->id)
                ->exists();

            abort_unless($parentBelongs, 404);
        }

        $moderated = (bool) config('blog.moderate_comments');

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'author_name' => $request->user()->name,
            'parent_id' => $parentId,
            'body' => $request->validated('body'),
            'status' => $moderated ? BlogComment::STATUS_PENDING : BlogComment::STATUS_APPROVED,
            'approved_at' => $moderated ? null : now(),
            'ip_address' => $request->ip(),
        ]);

        return back()
            ->with('comment_status', $moderated ? 'pending' : 'published')
            ->withFragment('comments');
    }
}
