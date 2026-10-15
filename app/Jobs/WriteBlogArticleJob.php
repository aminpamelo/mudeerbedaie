<?php

namespace App\Jobs;

use App\Models\BlogIssue;
use App\Services\Blog\Ai\ArticleWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes one AI draft article for a captured issue (a few-minute OpenAI call,
 * so it runs on the queue). Failures are recorded on the issue for the UI.
 */
class WriteBlogArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(public BlogIssue $issue, public ?int $userId = null) {}

    public function handle(ArticleWriter $writer): void
    {
        try {
            $writer->write($this->issue, $this->userId);
        } catch (\Throwable $e) {
            $this->issue->update(['status' => BlogIssue::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);
        }
    }
}
