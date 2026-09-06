<?php

namespace App\Jobs;

use App\Mail\NewBlogPostMail;
use App\Models\BlogPost;
use App\Models\BlogSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Broadcasts a freshly published article to the newsletter list. Each recipient
 * is queued as its own mail, so a single bad address never sinks the batch and
 * every message retries independently. Recipients are matched to the post's
 * language, so a Malay article only reaches Malay subscribers and vice versa.
 */
class SendNewPostNewsletter implements ShouldQueue
{
    use Queueable;

    public function __construct(public BlogPost $post) {}

    public function handle(): void
    {
        BlogSubscriber::query()
            ->active()
            ->where('locale', $this->post->locale)
            ->whereNotNull('email')
            ->chunkById(200, function ($subscribers): void {
                foreach ($subscribers as $subscriber) {
                    Mail::to($subscriber->email)->queue(new NewBlogPostMail($this->post, $subscriber));
                }
            });
    }
}
