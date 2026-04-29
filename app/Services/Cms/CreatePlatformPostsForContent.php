<?php

namespace App\Services\Cms;

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;

class CreatePlatformPostsForContent
{
    public function handle(Content $content): void
    {
        CmsPlatform::enabled()->each(function (CmsPlatform $platform) use ($content): void {
            CmsContentPlatformPost::firstOrCreate(
                [
                    'content_id' => $content->id,
                    'platform_id' => $platform->id,
                ],
                [
                    'status' => 'pending',
                ]
            );
        });
    }
}
