<?php

use App\Jobs\SendNewPostNewsletter;
use App\Mail\NewBlogPostMail;
use App\Models\BlogPost;
use App\Models\BlogSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('dispatches the newsletter and stamps the post when a draft is published', function () {
    Queue::fake();
    $post = BlogPost::factory()->draft()->create();

    actingAs($this->admin)
        ->post(route('blogseo.posts.publish', $post))
        ->assertRedirect();

    Queue::assertPushed(SendNewPostNewsletter::class, fn ($job) => $job->post->is($post));
    expect($post->fresh()->newsletter_sent_at)->not->toBeNull();
});

it('dispatches the newsletter when a post is created already published', function () {
    Queue::fake();

    actingAs($this->admin)
        ->post(route('blogseo.posts.store'), [
            'title' => 'Launch Day',
            'slug' => 'launch-day',
            'content' => 'Hello world.',
            'locale' => 'ms',
            'status' => 'published',
        ])
        ->assertRedirect();

    Queue::assertPushed(SendNewPostNewsletter::class);
});

it('does not send a newsletter for a draft save', function () {
    Queue::fake();

    actingAs($this->admin)
        ->post(route('blogseo.posts.store'), [
            'title' => 'Draft Only',
            'slug' => 'draft-only',
            'content' => 'Nothing yet.',
            'locale' => 'ms',
            'status' => 'draft',
        ])
        ->assertRedirect();

    Queue::assertNotPushed(SendNewPostNewsletter::class);
});

it('never re-sends the newsletter for an already-notified post', function () {
    Queue::fake();
    $post = BlogPost::factory()->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'newsletter_sent_at' => now()->subDay(),
    ]);

    actingAs($this->admin)
        ->post(route('blogseo.posts.publish', $post))
        ->assertRedirect();

    Queue::assertNotPushed(SendNewPostNewsletter::class);
});

it('respects the newsletter_enabled config switch', function () {
    config(['blog.newsletter_enabled' => false]);
    Queue::fake();
    $post = BlogPost::factory()->draft()->create();

    actingAs($this->admin)
        ->post(route('blogseo.posts.publish', $post))
        ->assertRedirect();

    Queue::assertNotPushed(SendNewPostNewsletter::class);
});

it('emails only active subscribers in the post language', function () {
    Mail::fake();
    $post = BlogPost::factory()->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'locale' => 'ms',
    ]);

    $active = BlogSubscriber::create([
        'email' => 'ms-active@example.com',
        'locale' => 'ms',
        'token' => 'tok-active',
        'confirmed_at' => now(),
    ]);
    BlogSubscriber::create([
        'email' => 'ms-gone@example.com',
        'locale' => 'ms',
        'token' => 'tok-gone',
        'confirmed_at' => now(),
        'unsubscribed_at' => now(),
    ]);
    BlogSubscriber::create([
        'email' => 'en-active@example.com',
        'locale' => 'en',
        'token' => 'tok-en',
        'confirmed_at' => now(),
    ]);

    (new SendNewPostNewsletter($post))->handle();

    Mail::assertQueued(NewBlogPostMail::class, fn ($mail) => $mail->hasTo('ms-active@example.com'));
    Mail::assertNotQueued(NewBlogPostMail::class, fn ($mail) => $mail->hasTo('ms-gone@example.com'));
    Mail::assertNotQueued(NewBlogPostMail::class, fn ($mail) => $mail->hasTo('en-active@example.com'));
    Mail::assertQueued(NewBlogPostMail::class, 1);
});

it('renders the new-post email with a working unsubscribe link', function () {
    $post = BlogPost::factory()->malay()->create([
        'title' => 'Berita Terkini Kami',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $subscriber = BlogSubscriber::create([
        'email' => 'reader@example.com',
        'locale' => 'ms',
        'token' => 'unsub-token-123',
        'confirmed_at' => now(),
    ]);

    $rendered = (new NewBlogPostMail($post, $subscriber))->render();

    expect($rendered)
        ->toContain('Berita Terkini Kami')
        ->toContain(route('blog.unsubscribe', 'unsub-token-123'));
});
