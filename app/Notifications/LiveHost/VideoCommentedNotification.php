<?php

namespace App\Notifications\LiveHost;

use App\Models\LiveHostMenteeDailyVideo;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "Your mentor left feedback on your video" — fired to the host when a staff
 * member (PIC/admin/assistant) comments on one of the host's logged videos.
 * Deep-links straight to that video's thread in the Pocket.
 */
class VideoCommentedNotification extends BasePocketNotification
{
    public function __construct(
        public LiveHostMenteeDailyVideo $video,
        public User $author,
        public string $excerpt,
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        $title = $this->video->title ?: 'video anda';

        return "{$this->author->name} beri maklum balas pada \"{$title}\"";
    }

    protected function body(): string
    {
        return Str::limit($this->excerpt, 120);
    }

    protected function actionUrl(): string
    {
        return route('live-host.videos.index', ['video' => $this->video->id]);
    }
}
