<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreSubscriberRequest;
use App\Models\BlogSubscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class SubscriberController extends Controller
{
    /**
     * Capture a newsletter sign-up from an article.
     */
    public function store(StoreSubscriberRequest $request): RedirectResponse
    {
        $email = Str::lower(trim($request->validated('email')));

        $subscriber = BlogSubscriber::query()->firstOrNew(['email' => $email]);

        // Re-subscribing after opting out should reactivate the record rather
        // than fail on the unique index or silently do nothing.
        $subscriber->fill([
            'name' => $request->validated('name') ?: $subscriber->name,
            'locale' => app()->getLocale(),
            'blog_post_id' => $request->integer('blog_post_id') ?: $subscriber->blog_post_id,
            'source' => $request->validated('source') ?: 'blog',
            'confirmed_at' => $subscriber->confirmed_at ?? now(),
            'unsubscribed_at' => null,
            'token' => $subscriber->token ?: Str::random(48),
            'ip_address' => $request->ip(),
        ])->save();

        return back()
            ->with('newsletter_status', 'subscribed')
            ->withFragment('newsletter');
    }

    /**
     * One-click unsubscribe via the token embedded in newsletter emails.
     */
    public function unsubscribe(string $token): View
    {
        $subscriber = BlogSubscriber::query()->where('token', $token)->firstOrFail();

        if ($subscriber->is_active) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return view('blog.unsubscribed', ['subscriber' => $subscriber]);
    }
}
