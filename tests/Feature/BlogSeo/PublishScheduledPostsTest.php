<?php

use App\Jobs\SendNewPostNewsletter;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Queue;

it('publishes due scheduled posts and sends the newsletter', function () {
    Queue::fake();

    $due = BlogPost::factory()->create([
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
    ]);
    $future = BlogPost::factory()->create([
        'status' => 'scheduled',
        'published_at' => now()->addDay(),
    ]);

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    expect($due->fresh()->status)->toBe('published')
        ->and($due->fresh()->newsletter_sent_at)->not->toBeNull()
        ->and($future->fresh()->status)->toBe('scheduled');

    Queue::assertPushed(SendNewPostNewsletter::class, fn ($job) => $job->post->is($due));
    Queue::assertPushed(SendNewPostNewsletter::class, 1);
});

it('makes a published post visible on the public blog', function () {
    $post = BlogPost::factory()->malay()->create([
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
    ]);

    // Locale-gated public blog 404s it while scheduled...
    $this->withSession(['locale' => 'ms'])->get(route('blog.show', $post->slug))->assertNotFound();

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    // ...and serves it once the command promotes it to published.
    $this->withSession(['locale' => 'ms'])->get(route('blog.show', $post->slug))->assertOk();
});

it('does nothing when no posts are due', function () {
    Queue::fake();
    BlogPost::factory()->create(['status' => 'scheduled', 'published_at' => now()->addWeek()]);
    BlogPost::factory()->draft()->create();

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    Queue::assertNotPushed(SendNewPostNewsletter::class);
});
