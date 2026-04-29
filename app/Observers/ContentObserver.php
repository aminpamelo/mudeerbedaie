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
}
