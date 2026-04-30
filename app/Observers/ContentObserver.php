<?php

namespace App\Observers;

use App\Models\Content;
use App\Services\Cms\CreatePlatformPostsForContent;

class ContentObserver
{
    public function __construct(
        protected CreatePlatformPostsForContent $createPlatformPosts,
    ) {}

    public function updated(Content $content): void
    {
        if (
            $content->wasChanged('is_marked_for_ads')
            && $content->is_marked_for_ads === true
        ) {
            $this->createPlatformPosts->handle($content);
        }
    }

    /**
     * Cascade soft-deletes to platform posts so they don't linger as orphans.
     * Hard deletes are already handled by the FK cascadeOnDelete constraint.
     */
    public function deleted(Content $content): void
    {
        if (method_exists($content, 'isForceDeleting') && $content->isForceDeleting()) {
            return;
        }

        $content->platformPosts()->delete();
    }

    /**
     * If a soft-deleted content is restored and still marked, recreate the
     * per-platform tracking rows so the queue reflects the current state.
     */
    public function restored(Content $content): void
    {
        if ($content->is_marked_for_ads) {
            $this->createPlatformPosts->handle($content);
        }
    }
}
