<?php

namespace App\Console\Commands;

use App\Services\Blog\Ai\IssueCapturer;
use Illuminate\Console\Command;

class BlogCaptureIssues extends Command
{
    protected $signature = 'blog:capture-issues';

    protected $description = 'Capture current-affairs headlines from news feeds and score them for Islamic commentary articles';

    public function handle(IssueCapturer $capturer): int
    {
        $result = $capturer->capture();

        $this->info("Captured {$result['captured']} new issue(s), scored {$result['scored']}.");
        if ($result['failed_feeds'] !== []) {
            $this->warn('Failed feeds: '.implode(', ', $result['failed_feeds']));
        }

        return self::SUCCESS;
    }
}
