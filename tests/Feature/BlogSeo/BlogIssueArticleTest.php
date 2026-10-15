<?php

declare(strict_types=1);

use App\Jobs\WriteBlogArticleJob;
use App\Models\BlogIssue;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\User;
use App\Services\Blog\Ai\ArticleWriter;
use App\Services\Blog\Ai\IssueCapturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'openai.api_key' => 'sk-test',
        'blog.ai.feeds' => ['Berita Harian' => 'https://news.test/feed'],
    ]);
});

function aiJson(array $payload): CreateResponse
{
    return CreateResponse::fake(['choices' => [[
        'index' => 0,
        'message' => ['role' => 'assistant', 'content' => json_encode($payload)],
        'finish_reason' => 'stop',
    ]]]);
}

function rssFeed(array $items): string
{
    $xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>BH</title>';
    foreach ($items as [$title, $link, $date]) {
        $xml .= "<item><title><![CDATA[{$title}]]></title><link>{$link}</link><description>Ringkasan berita.</description><pubDate>{$date}</pubDate></item>";
    }

    return $xml.'</channel></rss>';
}

it('captures fresh headlines once, strips tracking params and lets AI score them', function () {
    Http::fake(['news.test/*' => Http::response(rssFeed([
        ['Remaja ketagih media sosial meningkat', 'https://bh.test/a?utm_source=rss&amp;utm_medium=rss', now()->subHours(3)->toRssString()],
        ['Pasukan bola menang 3-0', 'https://bh.test/b', now()->subHours(2)->toRssString()],
        ['Berita lama', 'https://bh.test/old', now()->subDays(10)->toRssString()],
    ]))]);

    OpenAI::fake([aiJson(['items' => [
        ['id' => 1, 'relevance' => 9, 'angle' => 'Menjaga masa dan hati daripada lalai.'],
        ['id' => 2, 'relevance' => 1, 'angle' => '-'],
    ]])]);

    $result = app(IssueCapturer::class)->capture();

    expect($result['captured'])->toBe(2)
        ->and(BlogIssue::count())->toBe(2)
        ->and(BlogIssue::find(1)->url)->toBe('https://bh.test/a')
        ->and(BlogIssue::find(1)->relevance)->toBe(9)
        ->and(BlogIssue::query()->suggested()->pluck('id')->all())->toBe([1]);

    // Re-capturing the same feed adds nothing (deduped by URL).
    expect(app(IssueCapturer::class)->capture()['captured'])->toBe(0);
});

it('writes a draft article with references, source credit and a book CTA', function () {
    $book = Product::factory()->create(['name' => 'Buku Hati Yang Tenang', 'slug' => 'buku-hati-tenang', 'status' => 'active', 'show_on_storefront' => true]);
    $issue = BlogIssue::factory()->create(['title' => 'Remaja ketagih media sosial', 'url' => 'https://bh.test/a', 'source' => 'Berita Harian']);
    $editor = User::factory()->admin()->create();

    OpenAI::fake([aiJson([
        'title' => 'Ketagihan Media Sosial: Pandangan Islam',
        'excerpt' => 'Kupasan isu ketagihan media sosial.',
        'meta_description' => 'Bagaimana Islam melihat ketagihan media sosial dalam kalangan remaja dan langkah praktikal untuk keluarga.',
        'focus_keyword' => 'ketagihan media sosial',
        'tags' => ['Remaja', 'Media Sosial'],
        'content' => "# Tajuk diulang\n\n## Isu\n\nRemaja hari ini...\n\n## Pengajaran\n\nRasulullah SAW bersabda...",
        'references' => [
            ['type' => 'hadith', 'citation' => 'Riwayat al-Tirmizi', 'text' => 'Antara tanda baiknya Islam seseorang...'],
            ['type' => 'quran', 'citation' => 'Surah al-Asr 103:1-3', 'text' => 'Demi masa...'],
        ],
        'book_id' => $book->id,
        'cta_text' => 'Ingin hati lebih tenang? Buku ini membantu.',
    ])]);

    $post = app(ArticleWriter::class)->write($issue, $editor->id);

    expect($post->status)->toBe(BlogPost::STATUS_DRAFT)
        ->and($post->locale)->toBe('ms')
        ->and($post->category->name)->toBe('Isu Semasa')
        ->and($post->content)->not->toContain('# Tajuk diulang')
        ->and($post->content)->toContain('[Berita Harian](https://bh.test/a)')
        ->and($post->content)->toContain(route('storefront.product', 'buku-hati-tenang'))
        ->and($post->content)->toContain('Ingin hati lebih tenang?')
        ->and($post->ai_meta['references'])->toHaveCount(2)
        ->and($post->ai_meta['needs_review'])->toBeTrue()
        ->and($post->products->pluck('id')->all())->toBe([$book->id])
        ->and($post->tags->pluck('name')->sort()->values()->all())->toBe(['Media Sosial', 'Remaja'])
        ->and($issue->fresh()->status)->toBe(BlogIssue::STATUS_WRITTEN)
        ->and($issue->fresh()->blog_post_id)->toBe($post->id);
});

it('marks the issue failed when the AI returns an incomplete article', function () {
    $issue = BlogIssue::factory()->create(['status' => BlogIssue::STATUS_WRITING]);
    OpenAI::fake([aiJson(['title' => ''])]);

    (new WriteBlogArticleJob($issue))->handle(app(ArticleWriter::class));

    expect($issue->fresh()->status)->toBe(BlogIssue::STATUS_FAILED)
        ->and($issue->fresh()->error)->not->toBeEmpty()
        ->and(BlogPost::count())->toBe(0);
});

it('auto-writes the most relevant fresh issues, honouring the daily setting', function () {
    Queue::fake();
    BlogIssue::factory()->create(['relevance' => 9]);
    BlogIssue::factory()->create(['relevance' => 7]);
    BlogIssue::factory()->create(['relevance' => 8]);
    BlogIssue::factory()->create(['relevance' => 3]);

    $this->artisan('blog:auto-write --count=2')->assertSuccessful();

    Queue::assertPushed(WriteBlogArticleJob::class, 2);
    expect(BlogIssue::where('status', BlogIssue::STATUS_WRITING)->orderByDesc('relevance')->pluck('relevance')->all())->toBe([9, 8]);

    $this->artisan('blog:auto-write --count=0')->assertSuccessful();
    Queue::assertPushed(WriteBlogArticleJob::class, 2);
});

it('lets staff browse issues, queue a write, dismiss and set auto-write', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $issue = BlogIssue::factory()->create(['title' => 'Isu banjir dan sikap tolong-menolong']);

    $this->actingAs($admin)->get('/blog-seo/issues')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Issues', false)->where('issues.0.title', 'Isu banjir dan sikap tolong-menolong'));

    $this->actingAs($admin)->post("/blog-seo/issues/{$issue->id}/write")->assertRedirect();
    Queue::assertPushed(WriteBlogArticleJob::class, fn ($job) => $job->issue->is($issue) && $job->userId === $admin->id);
    expect($issue->fresh()->status)->toBe(BlogIssue::STATUS_WRITING);

    $other = BlogIssue::factory()->create();
    $this->actingAs($admin)->post("/blog-seo/issues/{$other->id}/dismiss");
    expect($other->fresh()->status)->toBe(BlogIssue::STATUS_DISMISSED);

    $this->actingAs($admin)->put('/blog-seo/issues/settings', ['auto_daily_count' => 3])->assertSessionHasNoErrors();
    expect((int) app(\App\Services\SettingsService::class)->get('blog_ai_auto_daily_count'))->toBe(3);

    $this->actingAs($admin)->put('/blog-seo/issues/settings', ['auto_daily_count' => 9])->assertSessionHasErrors('auto_daily_count');
});

it('keeps the issue pipeline staff-only', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']))
        ->get('/blog-seo/issues')
        ->assertForbidden();
});
