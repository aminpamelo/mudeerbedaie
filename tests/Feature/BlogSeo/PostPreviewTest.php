<?php

use App\Models\BlogPost;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('lets an admin preview a draft post through the real article view', function () {
    $post = BlogPost::factory()->draft()->create(['title' => 'Hidden Draft Headline']);

    actingAs($this->admin)
        ->get(route('blogseo.posts.preview', $post))
        ->assertOk()
        ->assertSee('Hidden Draft Headline')
        ->assertSee(__('blog.preview_status_draft'))
        ->assertSee("/blog-seo/posts/{$post->id}/edit");
});

it('tells the admin when a scheduled post will be published', function () {
    $publishAt = now()->addYears(5)->setTime(9, 0);
    $post = BlogPost::factory()->scheduled()->create(['published_at' => $publishAt]);

    actingAs($this->admin)
        ->get(route('blogseo.posts.preview', $post))
        ->assertOk()
        ->assertSee((string) $publishAt->year)
        ->assertSee("/blog-seo/posts/{$post->id}/edit");
});

it('does not inflate the view count when previewing', function () {
    $post = BlogPost::factory()->draft()->create(['view_count' => 7]);

    actingAs($this->admin)
        ->get(route('blogseo.posts.preview', $post))
        ->assertOk();

    expect($post->fresh()->view_count)->toBe(7);
});

it('blocks guests from the preview', function () {
    $post = BlogPost::factory()->draft()->create();

    $this->get(route('blogseo.posts.preview', $post))
        ->assertRedirect();
});

it('forbids non-admin users from the preview', function () {
    $post = BlogPost::factory()->draft()->create();
    $user = User::factory()->create(['role' => 'user']);

    actingAs($user)
        ->get(route('blogseo.posts.preview', $post))
        ->assertForbidden();
});

it('still 404s a draft on the public blog url', function () {
    $post = BlogPost::factory()->draft()->create();

    actingAs($this->admin)
        ->get(route('blog.show', $post->slug))
        ->assertNotFound();
});

it('renders a published post publicly without the preview banner', function () {
    // The guest storefront resolves to Malay (see SetLocale), and the public
    // blog is locale-gated — so the fixture must be a Malay post to be visible.
    $post = BlogPost::factory()->malay()->create([
        'title' => 'Public Live Post',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->withSession(['locale' => 'ms'])
        ->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('Public Live Post')
        ->assertDontSee(__('blog.preview_admin_only', [], 'ms'));
});
