<?php

use App\Models\BlogAuthor;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('lists authors with post counts', function () {
    $author = BlogAuthor::factory()->create(['name' => 'Nurul Aina']);
    BlogPost::factory()->create(['blog_author_id' => $author->id, 'status' => 'published', 'published_at' => now()->subDay()]);
    BlogPost::factory()->create(['blog_author_id' => $author->id, 'status' => 'draft']);

    actingAs($this->admin)
        ->get('/blog-seo/authors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Authors', false)
            ->where('authors.0.name', 'Nurul Aina')
            ->where('authors.0.posts', 2)
            ->where('authors.0.published', 1)
        );
});

it('creates an author with an uploaded avatar', function () {
    Storage::fake('public');

    actingAs($this->admin)
        ->post('/blog-seo/authors', [
            'name' => 'Guest Writer',
            'avatar' => UploadedFile::fake()->image('face.jpg'),
        ])
        ->assertRedirect();

    $author = BlogAuthor::firstWhere('name', 'Guest Writer');

    expect($author)->not->toBeNull()
        ->and($author->slug)->toBe('guest-writer')
        ->and($author->avatar_path)->not->toBeNull();

    Storage::disk('public')->assertExists($author->avatar_path);
});

it('validates that a name is required', function () {
    actingAs($this->admin)
        ->post('/blog-seo/authors', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('updates an author and can remove the avatar', function () {
    Storage::fake('public');
    $author = BlogAuthor::factory()->create([
        'name' => 'Old Name',
        'avatar_path' => UploadedFile::fake()->image('old.jpg')->store('blog-authors', 'public'),
    ]);
    $oldPath = $author->avatar_path;

    actingAs($this->admin)
        ->put("/blog-seo/authors/{$author->id}", [
            'name' => 'New Name',
            'remove_avatar' => true,
        ])
        ->assertRedirect();

    $author->refresh();
    expect($author->name)->toBe('New Name')
        ->and($author->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($oldPath);
});

it('deletes an author and detaches their posts', function () {
    $author = BlogAuthor::factory()->create();
    $post = BlogPost::factory()->create(['blog_author_id' => $author->id]);

    actingAs($this->admin)
        ->delete("/blog-seo/authors/{$author->id}")
        ->assertRedirect();

    expect(BlogAuthor::find($author->id))->toBeNull();
    expect($post->fresh()->blog_author_id)->toBeNull();
});

it('assigns a blog author to a post from the editor and it drives the byline', function () {
    $author = BlogAuthor::factory()->create(['name' => 'Byline Person']);

    actingAs($this->admin)
        ->post('/blog-seo/posts', [
            'title' => 'Attributed Post',
            'slug' => 'attributed-post',
            'content' => 'Some content.',
            'blog_author_id' => $author->id,
            'locale' => 'ms',
            'status' => 'draft',
        ])
        ->assertRedirect();

    $post = BlogPost::firstWhere('slug', 'attributed-post');

    expect($post->blog_author_id)->toBe($author->id)
        ->and($post->author_name)->toBe('Byline Person');
});
