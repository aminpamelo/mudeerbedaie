<?php

use App\Models\CmsContentPlatformPost;
use App\Models\Content;
use App\Models\ContentStat;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create(['role' => 'admin']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $this->actingAs($this->user, 'sanctum');
});

it('returns the report envelope with expected sections', function () {
    Content::factory()->count(3)->create();

    $response = $this->getJson('/api/cms/reports/content');

    $response->assertSuccessful()->assertJsonStructure([
        'data' => [
            'period' => ['start_date', 'end_date'],
            'kpis' => [
                'total_content',
                'posted_in_period',
                'marked_in_period',
                'marked_rate',
                'total_views',
                'total_engagement',
            ],
            'funnel' => [
                '*' => ['key', 'label', 'count'],
            ],
            'by_platform' => [
                '*' => ['key', 'name', 'views', 'likes', 'comments'],
            ],
            'contents',
            'top_performers' => [
                'by_total_views',
                'by_cross_platform',
                'by_engagement_rate',
            ],
        ],
    ]);
});

it('aggregates tiktok stats and cross-platform stats per content', function () {
    // Create unmarked, then mark via update so the observer creates platform posts.
    $content = Content::factory()->create([
        'stage' => 'posted',
        'is_marked_for_ads' => false,
        'posted_at' => now()->subDays(2),
    ]);
    $content->update(['is_marked_for_ads' => true, 'marked_at' => now()]);

    ContentStat::create([
        'content_id' => $content->id,
        'views' => 10_000,
        'likes' => 500,
        'comments' => 50,
        'shares' => 10,
        'fetched_at' => now(),
        'source' => 'manual',
    ]);

    $platformPost = CmsContentPlatformPost::query()
        ->where('content_id', $content->id)
        ->first();

    $platformPost->update([
        'status' => 'posted',
        'stats' => ['views' => 2_000, 'likes' => 100, 'comments' => 5],
    ]);

    $response = $this->getJson('/api/cms/reports/content');

    $response->assertSuccessful();

    $rows = collect($response->json('data.contents'));
    $row = $rows->firstWhere('id', $content->id);

    expect($row)->not->toBeNull();
    expect($row['tiktok']['views'])->toBe(10_000);
    expect($row['cross_post']['views'])->toBe(2_000);
    expect($row['totals']['views'])->toBe(12_000);
    expect($row['is_marked'])->toBeTrue();
    expect($row['cross_post']['posted'])->toBe(1);
    expect($row['cross_post']['total'])->toBe(5);
});

it('exposes the funnel with marked, cross-posted, and has-ads counts', function () {
    Content::factory()->count(2)->create(['stage' => 'idea']);
    Content::factory()->create(['stage' => 'posted', 'is_marked_for_ads' => true]);

    $response = $this->getJson('/api/cms/reports/content');

    $funnel = collect($response->json('data.funnel'));
    expect($funnel->firstWhere('key', 'idea')['count'])->toBe(2);
    expect($funnel->firstWhere('key', 'marked')['count'])->toBe(1);
    expect($funnel->firstWhere('key', 'cross_posted'))->not->toBeNull();
    expect($funnel->firstWhere('key', 'has_ads'))->not->toBeNull();
});

it('streams a csv export', function () {
    Content::factory()->count(2)->create();

    $response = $this->get('/api/cms/reports/content/export');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->streamedContent())->toContain('ID,Title,Stage');
});

it('respects start_date and end_date filters', function () {
    $oldContent = Content::factory()->create(['created_at' => now()->subYear()]);
    $newContent = Content::factory()->create(['created_at' => now()]);

    $response = $this->getJson('/api/cms/reports/content?start_date='.now()->subDays(7)->toDateString().'&end_date='.now()->toDateString());

    $rows = collect($response->json('data.contents'));
    expect($rows->pluck('id'))->toContain($newContent->id);
    expect($rows->pluck('id'))->not->toContain($oldContent->id);
});
