<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Command;

/**
 * Promotes scheduled blog posts to published once their time arrives, then lets
 * each one fan out the new-article newsletter. Meant to run every minute so a
 * post scheduled for 09:00 actually goes live at 09:00.
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish scheduled blog posts whose time has arrived and send the new-article newsletter';

    public function handle(): int
    {
        $due = BlogPost::query()
            ->where('status', BlogPost::STATUS_SCHEDULED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->update(['status' => BlogPost::STATUS_PUBLISHED]);
            $post->dispatchNewsletterIfDue();
            $this->line("Published: {$post->title}");
        }

        if ($due->isNotEmpty()) {
            cache()->forget('seo.sitemap.xml');
        }

        $this->info("Published {$due->count()} scheduled post(s).");

        return self::SUCCESS;
    }
}
