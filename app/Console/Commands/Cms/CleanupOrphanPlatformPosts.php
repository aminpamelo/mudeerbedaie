<?php

namespace App\Console\Commands\Cms;

use App\Models\CmsContentPlatformPost;
use Illuminate\Console\Command;

class CleanupOrphanPlatformPosts extends Command
{
    protected $signature = 'cms:cleanup-orphan-platform-posts {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Delete platform posts whose underlying content was soft-deleted (idempotent).';

    public function handle(): int
    {
        $orphanQuery = CmsContentPlatformPost::query()->whereDoesntHave('content');
        $count = (clone $orphanQuery)->count();

        if ($count === 0) {
            $this->info('No orphan platform posts found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[DRY RUN] Would delete {$count} orphan platform post(s).");

            return self::SUCCESS;
        }

        $this->info("Deleting {$count} orphan platform post(s)...");
        $orphanQuery->delete();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
