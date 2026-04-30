<?php

namespace App\Console\Commands\Cms;

use App\Models\Content;
use App\Services\Cms\CreatePlatformPostsForContent;
use Illuminate\Console\Command;

class BackfillPlatformPosts extends Command
{
    protected $signature = 'cms:backfill-platform-posts';

    protected $description = 'Create missing per-platform tracking rows for already-marked content (idempotent).';

    public function handle(CreatePlatformPostsForContent $service): int
    {
        $marked = Content::query()->where('is_marked_for_ads', true)->get();

        if ($marked->isEmpty()) {
            $this->info('No marked content found. Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info("Backfilling platform posts for {$marked->count()} marked content(s)...");

        $created = 0;
        foreach ($marked as $content) {
            $before = $content->platformPosts()->count();
            $service->handle($content);
            $after = $content->platformPosts()->count();
            $diff = $after - $before;
            $created += $diff;

            $this->line("  #{$content->id} {$content->title}: +{$diff} row(s) (now {$after})");
        }

        $this->info("Done. {$created} new row(s) created.");

        return self::SUCCESS;
    }
}
