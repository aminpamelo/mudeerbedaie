<?php

namespace App\Mail;

use App\Models\BlogPost;
use App\Models\BlogSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The "new article" newsletter sent to a single blog subscriber. Rendered in the
 * subscriber's own language and carrying their personal one-click unsubscribe
 * token, so the footer link always opts out the right recipient.
 */
class NewBlogPostMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BlogPost $post,
        public BlogSubscriber $subscriber,
    ) {
        $this->locale($subscriber->locale ?: (string) config('app.locale'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) $this->post->title,
        );
    }

    public function content(): Content
    {
        $image = $this->post->featuredImage?->url;

        if ($image && ! Str::startsWith($image, ['http://', 'https://'])) {
            $image = url($image);
        }

        return new Content(
            markdown: 'emails.blog.new-post',
            with: [
                'post' => $this->post,
                'subscriber' => $this->subscriber,
                'url' => route('blog.show', $this->post->slug),
                'imageUrl' => $image,
                'unsubscribeUrl' => $this->subscriber->token
                    ? route('blog.unsubscribe', $this->subscriber->token)
                    : null,
            ],
        );
    }
}
