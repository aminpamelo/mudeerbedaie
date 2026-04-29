<?php

use App\Models\Content;
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

it('eager-loads platform_posts on the show response when content is marked', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]); // observer auto-creates 5 rows

    $response = $this->getJson("/api/cms/contents/{$content->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $content->id)
        ->assertJsonStructure([
            'data' => [
                'platform_posts' => [
                    '*' => ['id', 'status', 'platform' => ['key', 'name']],
                ],
            ],
        ]);

    expect($response->json('data.platform_posts'))->toHaveCount(5);
});

it('returns empty platform_posts array for unmarked content', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);

    $response = $this->getJson("/api/cms/contents/{$content->id}");

    $response->assertSuccessful();
    expect($response->json('data.platform_posts'))->toBe([]);
});
