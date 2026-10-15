<?php

namespace App\Console\Commands;

use App\Jobs\WriteBlogArticleJob;
use App\Models\BlogIssue;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class BlogAutoWriteArticles extends Command
{
    /** Setting key: drafts to auto-write per day (0 = off). */
    public const SETTING = 'blog_ai_auto_daily_count';

    protected $signature = 'blog:auto-write {--count= : Override how many drafts to write}';

    protected $description = 'Auto-write AI draft articles from the most relevant fresh current issues';

    public function handle(SettingsService $settings): int
    {
        $count = $this->option('count') !== null
            ? (int) $this->option('count')
            : (int) $settings->get(self::SETTING, 2);

        if ($count <= 0) {
            $this->info('Auto-write is off.');

            return self::SUCCESS;
        }

        $issues = BlogIssue::query()
            ->suggested()
            ->where('created_at', '>=', now()->subDays(2))
            ->orderByDesc('relevance')
            ->orderByDesc('published_at')
            ->limit($count)
            ->get();

        foreach ($issues as $issue) {
            $issue->update(['status' => BlogIssue::STATUS_WRITING, 'error' => null]);
            WriteBlogArticleJob::dispatch($issue);
        }

        $this->info("Queued {$issues->count()} article draft(s).");

        return self::SUCCESS;
    }
}
